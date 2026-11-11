import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/utils/format.dart';
import '../../models/dashboard_summary.dart';
import '../../models/gold_rate.dart';
import '../../providers/auth_provider.dart';
import '../../providers/dashboard_provider.dart';
import '../../widgets/state_handler.dart';
import 'gold_rate_sheet.dart';

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  Future<void> _refresh(WidgetRef ref) async {
    ref.invalidate(dashboardProvider);
    ref.invalidate(latestGoldRatesProvider);

    try {
      await ref.read(dashboardProvider.future);
    } catch (_) {
      // The provider surfaces the error in the body.
    }

    try {
      await ref.read(latestGoldRatesProvider.future);
    } catch (_) {
      // The gold rate card surfaces the error.
    }
  }

  Future<void> _openRateSheet(BuildContext context, WidgetRef ref) async {
    final rate = await showGoldRateSheet(context);

    if (rate == null || !context.mounted) {
      return;
    }

    ref.invalidate(latestGoldRatesProvider);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          '${rate.karat}K স্বর্ণের দর প্রতি গ্রাম ${formatMoney(rate.ratePerGram)} সংরক্ষণ করা হয়েছে।',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    final user = auth is AuthAuthenticated ? auth.user : null;
    final canManageRates = user?.isAdminOrManager ?? false;
    final dashboard = ref.watch(dashboardProvider);
    final rates = ref.watch(latestGoldRatesProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('ড্যাশবোর্ড'),
        actions: [
          if (canManageRates)
            IconButton(
              tooltip: 'স্বর্ণের দাম আপডেট',
              onPressed: () => _openRateSheet(context, ref),
              icon: const Icon(Icons.edit_outlined),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: () => _refresh(ref),
        child: dashboard.when(
          loading: () =>
              StateHandlerView.loading(message: 'আজকের হিসাব লোড হচ্ছে…'),
          error: (error, _) => StateHandlerView.error(
            message: error.toString(),
            onRetry: () => _refresh(ref),
          ),
          data: (summary) => ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            children: [
              _Greeting(name: user?.name ?? '', summary: summary),
              const SizedBox(height: 16),
              _MetricGrid(summary: summary),
              const SizedBox(height: 16),
              _GoldRateCard(
                rates: rates,
                canManage: canManageRates,
                onUpdate: () => _openRateSheet(context, ref),
                onRetry: () => ref.invalidate(latestGoldRatesProvider),
              ),
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }
}

class _Greeting extends StatelessWidget {
  const _Greeting({required this.name, required this.summary});

  final String name;
  final DashboardSummary summary;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final displayName = name.trim().isEmpty ? 'কাউন্টার' : name.trim();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'স্বাগতম, $displayName',
          style: theme.textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          formatDate(summary.date),
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
      ],
    );
  }
}

class _MetricGrid extends StatelessWidget {
  const _MetricGrid({required this.summary});

  final DashboardSummary summary;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final today = summary.today;
    final pawns = summary.pawns;

    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 720 ? 4 : 2;

        final cards = [
          _MetricCard(
            icon: Icons.receipt_long_outlined,
            label: 'আজকের বিক্রয়',
            value: formatMoney(today.salesTotal),
            footnote: '${today.invoiceCount} টি ইনভয়েস',
            color: theme.colorScheme.primary,
          ),
          _MetricCard(
            icon: Icons.account_balance_wallet_outlined,
            label: 'ক্যাশ ব্যালেন্স',
            value: formatMoney(today.cashBalance),
            footnote:
                '+${formatMoney(today.cashIn)} / -${formatMoney(today.cashOut)}',
            color: const Color(0xFF2E7D5B),
          ),
          _MetricCard(
            icon: Icons.handshake_outlined,
            label: 'চলতি বন্ধক',
            value: '${pawns.activeCount}',
            footnote: 'বকেয়া মূলধন ${formatMoney(pawns.outstandingPrincipal)}',
            color: theme.colorScheme.secondary,
          ),
          _MetricCard(
            icon: Icons.calendar_month_outlined,
            label: 'মেয়াদোত্তীর্ণ বন্ধক',
            value: '${pawns.overdueCount}',
            footnote: 'বকেয়া সুদ ${formatMoney(pawns.interestDue)}',
            color: pawns.overdueCount > 0
                ? theme.colorScheme.error
                : const Color(0xFF2E7D5B),
          ),
        ];

        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            mainAxisSpacing: 12,
            crossAxisSpacing: 12,
            mainAxisExtent: 148,
          ),
          itemCount: cards.length,
          itemBuilder: (context, index) => cards[index],
        );
      },
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.icon,
    required this.label,
    required this.value,
    required this.footnote,
    required this.color,
  });

  final IconData icon;
  final String label;
  final String value;
  final String footnote;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, size: 20, color: color),
            ),
            const SizedBox(height: 8),
            Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 2),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerLeft,
              child: Text(
                value,
                style: theme.textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(height: 2),
            Text(
              footnote,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _GoldRateCard extends StatelessWidget {
  const _GoldRateCard({
    required this.rates,
    required this.canManage,
    required this.onUpdate,
    required this.onRetry,
  });

  final AsyncValue<List<GoldRate>> rates;
  final bool canManage;
  final VoidCallback onUpdate;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  Icons.trending_up,
                  size: 20,
                  color: theme.colorScheme.primary,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'স্বর্ণের দাম',
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                if (canManage)
                  TextButton.icon(
                    onPressed: onUpdate,
                    icon: const Icon(Icons.edit_outlined, size: 18),
                    label: const Text('আপডেট'),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            rates.when(
              loading: () => const Padding(
                padding: EdgeInsets.symmetric(vertical: 12),
                child: LinearProgressIndicator(),
              ),
              error: (error, _) => Row(
                children: [
                  Expanded(
                    child: Text(
                      'স্বর্ণের দর লোড করা যায়নি। $error',
                      style: theme.textTheme.bodySmall,
                    ),
                  ),
                  TextButton(onPressed: onRetry, child: const Text('আবার চেষ্টা করুন')),
                ],
              ),
              data: (list) {
                if (list.isEmpty) {
                  return Text(
                    'এখনো কোনো স্বর্ণের দর নির্ধারণ করা হয়নি।',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  );
                }

                return Column(
                  children: [
                    for (var index = 0; index < list.length; index++) ...[
                      if (index > 0) const Divider(height: 20),
                      _GoldRateRow(rate: list[index]),
                    ],
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _GoldRateRow extends StatelessWidget {
  const _GoldRateRow({required this.rate});

  final GoldRate rate;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Row(
      children: [
        Chip(
          label: Text('${rate.karat}K'),
          visualDensity: VisualDensity.compact,
        ),
        const Spacer(),
        Column(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Text(
              '${formatMoney(rate.ratePerGram)} / গ্রাম',
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w600,
              ),
            ),
            Text(
              'কার্যকর: ${formatDate(rate.effectiveDate)}',
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ],
    );
  }
}
