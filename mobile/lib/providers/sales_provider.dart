import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/sale.dart';
import '../services/sale_service.dart';
import 'auth_provider.dart';

final saleServiceProvider = Provider<SaleService>((ref) {
  return SaleService(dio: ref.watch(dioProvider));
});

final saleDetailProvider = FutureProvider.family<Sale, int>((ref, saleId) {
  return ref.watch(saleServiceProvider).show(saleId);
});

typedef SaleFilterStatus = String;

final salesControllerProvider =
    AsyncNotifierProvider<SalesController, SalesPageState>(
      SalesController.new,
    );

class SalesPageState {
  const SalesPageState({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
    required this.status,
    required this.search,
    this.isLoadingMore = false,
  });

  final List<Sale> items;
  final int currentPage;
  final int lastPage;
  final int total;
  final SaleFilterStatus status;
  final String search;
  final bool isLoadingMore;

  bool get hasMore => currentPage < lastPage;

  SalesPageState copyWith({
    List<Sale>? items,
    int? currentPage,
    int? lastPage,
    int? total,
    bool? isLoadingMore,
  }) {
    return SalesPageState(
      items: items ?? this.items,
      currentPage: currentPage ?? this.currentPage,
      lastPage: lastPage ?? this.lastPage,
      total: total ?? this.total,
      status: status,
      search: search,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
    );
  }

  factory SalesPageState.fromPage(
    SalePage page, {
    required String status,
    required String search,
  }) {
    return SalesPageState(
      items: page.items,
      currentPage: page.currentPage,
      lastPage: page.lastPage,
      total: page.total,
      status: status,
      search: search,
    );
  }
}

class SalesController extends AsyncNotifier<SalesPageState> {
  String _search = '';
  String _status = '';
  int _generation = 0;

  SaleService get _service => ref.read(saleServiceProvider);

  @override
  Future<SalesPageState> build() async {
    final page = await _service.list(search: _search, status: _status);

    return SalesPageState.fromPage(
      page,
      status: _status,
      search: _search,
    );
  }

  Future<void> search(String query) async {
    final next = query.trim();

    if (next == _search) {
      return;
    }

    _search = next;
    await _loadFirstPage();
  }

  Future<void> setStatus(SaleFilterStatus status) async {
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
        status: _status,
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
      final page = await _service.list(search: _search, status: _status);

      if (generation != _generation) {
        return;
      }

      state = AsyncData(
        SalesPageState.fromPage(page, status: _status, search: _search),
      );
    } catch (error, stackTrace) {
      if (generation != _generation) {
        return;
      }

      state = AsyncError(error, stackTrace);
    }
  }
}
