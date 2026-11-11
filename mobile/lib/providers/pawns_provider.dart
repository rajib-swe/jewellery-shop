import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/pawn.dart';
import '../services/pawn_service.dart';
import 'auth_provider.dart';

final pawnServiceProvider = Provider<PawnService>((ref) {
  return PawnService(dio: ref.watch(dioProvider));
});

final pawnDetailProvider = FutureProvider.family<Pawn, int>((ref, pawnId) {
  return ref.watch(pawnServiceProvider).show(pawnId);
});

typedef PawnFilterStatus = String;

const String pawnFilterOverdue = 'overdue';

final pawnsControllerProvider =
    AsyncNotifierProvider<PawnsController, PawnsPageState>(
      PawnsController.new,
    );

class PawnsPageState {
  const PawnsPageState({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
    required this.status,
    required this.search,
    this.isLoadingMore = false,
  });

  final List<Pawn> items;
  final int currentPage;
  final int lastPage;
  final int total;
  final PawnFilterStatus status;
  final String search;
  final bool isLoadingMore;

  bool get hasMore => currentPage < lastPage;

  PawnsPageState copyWith({
    List<Pawn>? items,
    int? currentPage,
    int? lastPage,
    int? total,
    bool? isLoadingMore,
  }) {
    return PawnsPageState(
      items: items ?? this.items,
      currentPage: currentPage ?? this.currentPage,
      lastPage: lastPage ?? this.lastPage,
      total: total ?? this.total,
      status: status,
      search: search,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
    );
  }

  factory PawnsPageState.fromPage(
    PawnPage page, {
    required String status,
    required String search,
  }) {
    return PawnsPageState(
      items: page.items,
      currentPage: page.currentPage,
      lastPage: page.lastPage,
      total: page.total,
      status: status,
      search: search,
    );
  }
}

/// The 'overdue' filter maps to the API's boolean `overdue` flag because the
/// pawn status enum itself only has active/redeemed/forfeited.
class PawnsController extends AsyncNotifier<PawnsPageState> {
  String _search = '';
  String _status = '';
  int _generation = 0;

  PawnService get _service => ref.read(pawnServiceProvider);

  @override
  Future<PawnsPageState> build() async =>
      PawnsPageState.fromPage(
        await _service.list(search: _search, status: _apiStatus(_status)),
        status: _status,
        search: _search,
      );

  Future<void> search(String query) async {
    final next = query.trim();

    if (next == _search) {
      return;
    }

    _search = next;
    await _loadFirstPage();
  }

  Future<void> setStatus(PawnFilterStatus status) async {
    if (status == _status) {
      return;
    }

    _status = status;
    await _loadFirstPage();
  }

  Future<void> refresh() => _loadFirstPage();

  Future<void> loadMore() async {
    final current = state.value;

    if (current == null || current.isLoadingMore || !current.hasMore) {
      return;
    }

    final generation = _generation;
    state = AsyncData(current.copyWith(isLoadingMore: true));

    try {
      final page = await _service.list(
        search: _search,
        status: _apiStatus(_status),
        overdue: _status == pawnFilterOverdue,
        page: current.currentPage + 1,
      );

      if (generation != _generation) {
        return;
      }

      final latest = state.value ?? current;
      state = AsyncData(
        latest.copyWith(
          items: [...latest.items, ...page.items],
          currentPage: page.currentPage,
          lastPage: page.lastPage,
          total: page.total,
          isLoadingMore: false,
        ),
      );
    } catch (error, stackTrace) {
      if (generation != _generation) {
        return;
      }

      state = AsyncError(error, stackTrace);
    }
  }

  Future<void> _loadFirstPage() async {
    final generation = ++_generation;
    state = const AsyncLoading();

    try {
      final page = await _service.list(
        search: _search,
        status: _apiStatus(_status),
        overdue: _status == pawnFilterOverdue,
      );

      if (generation != _generation) {
        return;
      }

      state = AsyncData(
        PawnsPageState.fromPage(page, status: _status, search: _search),
      );
    } catch (error, stackTrace) {
      if (generation != _generation) {
        return;
      }

      state = AsyncError(error, stackTrace);
    }
  }

  String? _apiStatus(String filter) =>
      filter == pawnFilterOverdue ? null : filter;
}
