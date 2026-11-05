import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../models/customer.dart';
import '../services/customer_service.dart';
import 'auth_provider.dart';

final customerServiceProvider = Provider<CustomerService>((ref) {
  return CustomerService(dio: ref.watch(dioProvider));
});

final customerProfileProvider = FutureProvider.family<CustomerProfile, int>((
  ref,
  customerId,
) async {
  final service = ref.watch(customerServiceProvider);
  final customer = await service.show(customerId);
  final history = await service.history(customerId);

  return CustomerProfile(customer: customer, history: history);
});

final customersControllerProvider =
    AsyncNotifierProvider<CustomersController, CustomersPageState>(
      CustomersController.new,
    );

class CustomersPageState {
  const CustomersPageState({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
    this.isLoadingMore = false,
  });

  final List<Customer> items;
  final int currentPage;
  final int lastPage;
  final int total;
  final bool isLoadingMore;

  bool get hasMore => currentPage < lastPage;

  CustomersPageState copyWith({
    List<Customer>? items,
    int? currentPage,
    int? lastPage,
    int? total,
    bool? isLoadingMore,
  }) {
    return CustomersPageState(
      items: items ?? this.items,
      currentPage: currentPage ?? this.currentPage,
      lastPage: lastPage ?? this.lastPage,
      total: total ?? this.total,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
    );
  }

  factory CustomersPageState.fromPage(CustomerPage page) {
    return CustomersPageState(
      items: page.items,
      currentPage: page.currentPage,
      lastPage: page.lastPage,
      total: page.total,
    );
  }
}

class CustomersController extends AsyncNotifier<CustomersPageState> {
  String _search = '';
  int _generation = 0;

  CustomerService get _service => ref.read(customerServiceProvider);

  @override
  Future<CustomersPageState> build() async {
    return CustomersPageState.fromPage(await _service.list(search: _search));
  }

  Future<void> search(String query) async {
    final next = query.trim();

    if (next == _search) {
      return;
    }

    _search = next;
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
      final page = await _service.list(search: _search);

      if (generation != _generation) {
        return;
      }

      state = AsyncData(CustomersPageState.fromPage(page));
    } catch (error, stackTrace) {
      if (generation != _generation) {
        return;
      }

      state = AsyncError(error, stackTrace);
    }
  }
}
