import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/utils/format.dart';
import '../../models/sale.dart';
import '../../providers/auth_provider.dart';
import '../../providers/sales_provider.dart';
import '../../widgets/state_handler.dart';

class SaleScreen extends ConsumerStatefulWidget {
  const SaleScreen({super.key});

  @override
  ConsumerState<SaleScreen> createState() => _SaleScreenState();
}

class _SaleScreenState extends ConsumerState<SaleScreen> {
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();

  Timer? _debounce;

  static const _filters = <(String?, String)>[
    (null, 'সকল'),
    ('completed', 'সম্পন্ন'),
    ('void', 'বাতিল'),
  ];

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
      ref.read(salesControllerProvider.notifier).loadMore();
    }
  }

  void _onSearchChanged(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (_scrollController.hasClients) {
        _scrollController.jumpTo(0);
      }
      ref.read(salesControllerProvider.notifier).search(value);
    });
  }

  Future<void> _refresh() =>
      ref.read(salesControllerProvider.notifier).refresh();

  @override
  Widget build(BuildContext context) {
    final sales = ref.watch(salesControllerProvider);
    final auth = ref.watch(authControllerProvider);
    final user = auth is AuthAuthenticated ? auth.user : null;
    final canCreate = user?.can('manage sales') ?? false;
    final activeStatus = sales.value?.status ?? '';

    return Scaffold(
      appBar: AppBar(
        title: const Text('বিক্রয়'),
        actions: [
          IconButton(
            tooltip: 'রিফ্রেশ',
            onPressed: _refresh,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      floatingActionButton: canCreate
          ? FloatingActionButton.extended(
              onPressed: () => context.push('/sale/new'),
              icon: const Icon(Icons.add),
              label: const Text('নতুন বিক্রয়'),
            )
          : null,
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              textInputAction: TextInputAction.search,
              onChanged: _onSearchChanged,
              decoration: InputDecoration(
                hintText: 'ইনভয়েস বা গ্রাহক খুঁজুন',
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
          SizedBox(
            height: 44,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                for (final (value, label) in _filters)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: FilterChip(
                      label: Text(label),
                      selected: activeStatus == (value ?? ''),
                      onSelected: (_) => ref
                          .read(salesControllerProvider.notifier)
                          .setStatus(value ?? ''),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: StateHandler<SalesPageState>(
              value: sales,
              onRetry: _refresh,
              isEmpty: (page) => page.items.isEmpty,
              emptyMessage: 'কোনো বিক্রয় পাওয়া যায়নি',
              emptyIcon: Icons.receipt_long_outlined,
              builder: (context, page) => RefreshIndicator(
                onRefresh: _refresh,
                child: ListView.separated(
                  controller: _scrollController,
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.only(bottom: 96),
                  itemCount: page.items.length + (page.hasMore ? 1 : 0),
                  separatorBuilder: (context, index) => const Divider(height: 1),
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

                    return _SaleTile(sale: page.items[index]);
                  },
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SaleTile extends StatelessWidget {
  const _SaleTile({required this.sale});

  final Sale sale;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final saleDue = sale.due;

    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      title: Text(
        sale.invoiceNo,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
      subtitle: Text(
        [
          sale.customer?.name ?? 'সাধারণ ক্রেতা',
          formatDate(sale.date),
          sale.userName ?? '',
        ].where((part) => part.isNotEmpty).join(' · '),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            formatMoney(sale.total),
            style: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            sale.isVoided
                ? 'বাতিল'
                : saleDue > 0
                ? 'বকেয়া ${formatMoney(saleDue)}'
                : 'পরিশোধিত',
            style: theme.textTheme.bodySmall?.copyWith(
              color: sale.isVoided || saleDue > 0
                  ? theme.colorScheme.error
                  : theme.colorScheme.outline,
            ),
          ),
        ],
      ),
      onTap: () => context.push('/sale/${sale.id}'),
    );
  }
}
