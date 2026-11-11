import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/utils/format.dart';
import '../../models/pawn.dart';
import '../../providers/auth_provider.dart';
import '../../providers/pawns_provider.dart';
import '../../widgets/state_handler.dart';

class PawnScreen extends ConsumerStatefulWidget {
  const PawnScreen({super.key});

  @override
  ConsumerState<PawnScreen> createState() => _PawnScreenState();
}

class _PawnScreenState extends ConsumerState<PawnScreen> {
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();

  Timer? _debounce;

  static const _filters = <(String, String)>[
    ('', 'সকল'),
    ('active', 'চলতি'),
    (pawnFilterOverdue, 'মেয়াদোত্তীর্ণ'),
    ('redeemed', 'খালাসকৃত'),
    ('forfeited', 'বাজেয়াপ্ত'),
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
      ref.read(pawnsControllerProvider.notifier).loadMore();
    }
  }

  void _onSearchChanged(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () {
      if (_scrollController.hasClients) {
        _scrollController.jumpTo(0);
      }
      ref.read(pawnsControllerProvider.notifier).search(value);
    });
  }

  Future<void> _refresh() =>
      ref.read(pawnsControllerProvider.notifier).refresh();

  @override
  Widget build(BuildContext context) {
    final pawns = ref.watch(pawnsControllerProvider);
    final auth = ref.watch(authControllerProvider);
    final user = auth is AuthAuthenticated ? auth.user : null;
    final canCreate = user?.can('manage pawns') ?? false;
    final activeStatus = pawns.value?.status ?? '';

    return Scaffold(
      appBar: AppBar(
        title: const Text('বন্ধক'),
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
              onPressed: () => context.push('/pawn/new'),
              icon: const Icon(Icons.add),
              label: const Text('নতুন বন্ধক'),
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
                hintText: 'বন্ধক নং বা গ্রাহক খুঁজুন',
                prefixIcon: const Icon(Icons.search),
                suffixIcon: _searchController.text.isEmpty
                    ? null
                    : IconButton(
                        tooltip: 'অনুসন্ধান মুছুন',
                        onPressed: () {
                          _searchController.clear();
                          _onSearchChanged('');
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
                      selected: activeStatus == value,
                      onSelected: (_) => ref
                          .read(pawnsControllerProvider.notifier)
                          .setStatus(value),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: StateHandler<PawnsPageState>(
              value: pawns,
              onRetry: _refresh,
              isEmpty: (page) => page.items.isEmpty,
              emptyMessage: 'কোনো বন্ধক পাওয়া যায়নি',
              emptyIcon: Icons.handyman_outlined,
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

                    return _PawnTile(pawn: page.items[index]);
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

class _PawnTile extends StatelessWidget {
  const _PawnTile({required this.pawn});

  final Pawn pawn;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final outstanding = pawn.summary.totalPayable;
    final tint = pawn.isRedeemed || pawn.isForfeited || pawn.isOverdue;

    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      title: Text(
        pawn.pawnNo,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(fontWeight: FontWeight.w600),
      ),
      subtitle: Text(
        [
          pawn.customer?.name ?? '—',
          formatDate(pawn.date),
          'মূলধন ${formatMoney(pawn.principal)}',
        ].join(' · '),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
      trailing: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            formatMoney(outstanding),
            style: theme.textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            pawn.isRedeemed
                ? 'খালাসকৃত'
                : pawn.isForfeited
                ? 'বাজেয়াপ্ত'
                : pawn.isOverdue
                ? 'মেয়াদোত্তীর্ণ'
                : 'মাসিক ${pawn.interestRate.toStringAsFixed(0)}%',
            style: theme.textTheme.bodySmall?.copyWith(
              color: tint ? theme.colorScheme.error : theme.colorScheme.outline,
            ),
          ),
        ],
      ),
      onTap: () => context.push('/pawn/${pawn.id}'),
    );
  }
}
