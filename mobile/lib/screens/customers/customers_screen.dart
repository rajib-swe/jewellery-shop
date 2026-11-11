import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/theme/app_colors.dart';
import '../../core/utils/format.dart';
import '../../models/customer.dart';
import '../../providers/customers_provider.dart';
import '../../widgets/customer_form_sheet.dart';
import '../../widgets/state_handler.dart';

class CustomersScreen extends ConsumerStatefulWidget {
  const CustomersScreen({super.key});

  @override
  ConsumerState<CustomersScreen> createState() => _CustomersScreenState();
}

class _CustomersScreenState extends ConsumerState<CustomersScreen> {
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();

  Timer? _debounce;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _scrollController
      ..removeListener(_onScroll)
      ..dispose();
    _searchController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.extentAfter < 320) {
      ref.read(customersControllerProvider.notifier).loadMore();
    }
  }

  void _onSearchChanged(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (_scrollController.hasClients) {
        _scrollController.jumpTo(0);
      }
      ref.read(customersControllerProvider.notifier).search(value);
    });
  }

  Future<void> _openAddCustomer() async {
    final newCust = await showCustomerFormSheet(context);
    if (newCust != null && mounted) {
      ref.read(customersControllerProvider.notifier).refresh();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('গ্রাহক "${newCust.name}" সফলভাবে যুক্ত করা হয়েছে।'),
          backgroundColor: AppColors.success,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final customers = ref.watch(customersControllerProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('গ্রাহক তালিকা'),
        actions: [
          IconButton(
            tooltip: 'নতুন গ্রাহক যোগ করুন',
            icon: const Icon(Icons.person_add_outlined),
            onPressed: _openAddCustomer,
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              textInputAction: TextInputAction.search,
              onChanged: _onSearchChanged,
              style: const TextStyle(color: AppColors.ink, fontWeight: FontWeight.w500),
              decoration: InputDecoration(
                hintText: 'নাম, মোবাইল, এনআইডি বা কোড খুঁজুন…',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchController.text.isEmpty
                    ? null
                    : IconButton(
                        tooltip: 'অনুসন্ধান মুছুন',
                        onPressed: () {
                          _searchController.clear();
                          _onSearchChanged('');
                          setState(() {});
                        },
                        icon: const Icon(Icons.close),
                      ),
              ),
            ),
          ),
          Expanded(
            child: StateHandler<CustomersPageState>(
              value: customers,
              onRetry: () =>
                  ref.read(customersControllerProvider.notifier).refresh(),
              isEmpty: (page) => page.items.isEmpty,
              emptyMessage: 'কোনো গ্রাহক পাওয়া যায়নি',
              emptyIcon: Icons.people_outline,
              builder: (context, page) => RefreshIndicator(
                onRefresh: () =>
                    ref.read(customersControllerProvider.notifier).refresh(),
                child: ListView.separated(
                  controller: _scrollController,
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.only(bottom: 80),
                  itemCount: page.items.length + (page.hasMore ? 1 : 0),
                  separatorBuilder: (context, index) =>
                      const Divider(height: 1),
                  itemBuilder: (context, index) {
                    if (index >= page.items.length) {
                      return const Padding(
                        padding: EdgeInsets.symmetric(vertical: 20),
                        child: Center(
                          child: SizedBox(
                            height: 22,
                            width: 22,
                            child: CircularProgressIndicator(strokeWidth: 2.4),
                          ),
                        ),
                      );
                    }

                    final customer = page.items[index];

                    return _CustomerTile(customer: customer);
                  },
                ),
              ),
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _openAddCustomer,
        icon: const Icon(Icons.person_add),
        label: const Text('নতুন গ্রাহক'),
      ),
    );
  }
}

class _CustomerTile extends StatelessWidget {
  const _CustomerTile({required this.customer});

  final Customer customer;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: CircleAvatar(
        backgroundColor: AppColors.primary.withValues(alpha: 0.12),
        foregroundColor: AppColors.primary,
        child: Text(
          customer.initials,
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
      title: Text(
        customer.name,
        style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.ink),
      ),
      subtitle: Text(
        customer.phone.isEmpty ? 'ফোন নম্বর নেই' : customer.phone,
        style: const TextStyle(color: AppColors.inkMuted),
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            customer.code,
            style: const TextStyle(
              fontSize: 12,
              color: AppColors.inkMuted,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            formatMoney(customer.openingBalance),
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.ink,
              fontSize: 13,
            ),
          ),
        ],
      ),
      onTap: () => context.push('/customers/${customer.id}'),
    );
  }
}
