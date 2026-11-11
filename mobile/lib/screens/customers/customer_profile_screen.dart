import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/utils/format.dart';
import '../../models/customer.dart';
import '../../providers/customers_provider.dart';
import '../../widgets/state_handler.dart';

class CustomerProfileScreen extends ConsumerWidget {
  const CustomerProfileScreen({super.key, required this.customerId});

  final int customerId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final profile = ref.watch(customerProfileProvider(customerId));

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          tooltip: 'গ্রাহক তালিকায় ফিরে যান',
          onPressed: () => context.go('/customers'),
          icon: const Icon(Icons.arrow_back),
        ),
        title: const Text('গ্রাহকের তথ্য'),
      ),
      body: StateHandler<CustomerProfile>(
        value: profile,
        onRetry: () => ref.invalidate(customerProfileProvider(customerId)),
        builder: (context, data) => _ProfileBody(profile: data),
      ),
    );
  }
}

class _ProfileBody extends StatelessWidget {
  const _ProfileBody({required this.profile});

  final CustomerProfile profile;

  @override
  Widget build(BuildContext context) {
    final history = profile.history;

    return DefaultTabController(
      length: 4,
      child: Column(
        children: [
          _ProfileHeader(profile: profile),
          TabBar(
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            tabs: [
              const Tab(text: 'সারসংক্ষেপ'),
              Tab(text: 'বিক্রয় (${history.sales.length})'),
              Tab(text: 'বন্ধক (${history.pawns.length})'),
              Tab(text: 'পেমেন্ট (${history.payments.length})'),
            ],
          ),
          Expanded(
            child: TabBarView(
              children: [
                _OverviewTab(customer: profile.customer, history: history),
                _SalesTab(sales: history.sales),
                _PawnsTab(pawns: history.pawns),
                _PaymentsTab(payments: history.payments),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ProfileHeader extends StatelessWidget {
  const _ProfileHeader({required this.profile});

  final CustomerProfile profile;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final customer = profile.customer;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              CircleAvatar(
                radius: 28,
                backgroundColor: theme.colorScheme.primary.withValues(
                  alpha: 0.14,
                ),
                foregroundColor: theme.colorScheme.primary,
                child: Text(
                  customer.initials,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      customer.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      customer.code,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                    if (customer.phone.isNotEmpty)
                      Text(customer.phone, style: theme.textTheme.bodySmall),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    'বকেয়া ব্যালেন্স',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    formatMoney(profile.history.dueBalance),
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: profile.history.dueBalance > 0
                          ? theme.colorScheme.error
                          : null,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _OverviewTab extends StatelessWidget {
  const _OverviewTab({required this.customer, required this.history});

  final Customer customer;
  final CustomerHistory history;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Row(
          children: [
            Expanded(
              child: _MiniCard(
                icon: Icons.account_balance_wallet_outlined,
                label: 'বকেয়া ব্যালেন্স',
                value: formatMoney(history.dueBalance),
                isError: history.dueBalance > 0,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _MiniCard(
                icon: Icons.badge_outlined,
                label: 'এনআইডি',
                value: customer.nid ?? '—',
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Card(
          child: Column(
            children: [
              _InfoRow(
                icon: Icons.my_location_outlined,
                label: 'ঠিকানা',
                value: customer.address ?? '—',
              ),
              const Divider(height: 1),
              _InfoRow(
                icon: Icons.notes_outlined,
                label: 'নোট',
                value: customer.notes ?? '—',
              ),
              const Divider(height: 1),
              _InfoRow(
                icon: Icons.savings_outlined,
                label: 'প্রারম্ভিক জের',
                value: formatMoney(customer.openingBalance),
              ),
              const Divider(height: 1),
              _InfoRow(
                icon: Icons.event_available_outlined,
                label: 'নিবন্ধন তারিখ',
                value: formatDate(customer.createdAt),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _MiniCard extends StatelessWidget {
  const _MiniCard({
    required this.icon,
    required this.label,
    required this.value,
    this.isError = false,
  });

  final IconData icon;
  final String label;
  final String value;
  final bool isError;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = isError ? theme.colorScheme.error : theme.colorScheme.primary;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 20, color: color),
            const SizedBox(height: 8),
            Text(
              label,
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
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return ListTile(
      leading: Icon(icon, size: 20),
      title: Text(
        label,
        style: theme.textTheme.bodySmall?.copyWith(
          color: theme.colorScheme.outline,
        ),
      ),
      subtitle: Text(value, style: theme.textTheme.bodyMedium),
      dense: true,
    );
  }
}

class _SalesTab extends StatelessWidget {
  const _SalesTab({required this.sales});

  final List<CustomerSale> sales;

  @override
  Widget build(BuildContext context) {
    if (sales.isEmpty) {
      return const _EmptyTab(
        icon: Icons.receipt_long_outlined,
        message: 'এই গ্রাহকের কোনো বিক্রয় নেই',
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: sales.length,
      separatorBuilder: (context, index) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final sale = sales[index];

        return _RecordCard(
          title: sale.invoiceNo,
          status: sale.status,
          subtitle: formatDate(sale.date),
          amounts: [
            _Amount(label: 'মোট', value: sale.total),
            _Amount(label: 'পরিশোধিত', value: sale.paid),
            _Amount(label: 'বকেয়া', value: sale.due, emphasize: sale.due > 0),
          ],
        );
      },
    );
  }
}

class _PawnsTab extends StatelessWidget {
  const _PawnsTab({required this.pawns});

  final List<CustomerPawn> pawns;

  @override
  Widget build(BuildContext context) {
    if (pawns.isEmpty) {
      return const _EmptyTab(
        icon: Icons.handshake_outlined,
        message: 'এই গ্রাহকের কোনো বন্ধক নেই',
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: pawns.length,
      separatorBuilder: (context, index) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final pawn = pawns[index];

        return _RecordCard(
          title: pawn.pawnNo,
          status: pawn.status,
          subtitle:
              '${formatDate(pawn.date)} → মেয়াদ ${formatDate(pawn.dueDate)}',
          amounts: [
            _Amount(label: 'মূলধন', value: pawn.principal),
            _Amount(label: 'সুদ', value: pawn.interestDue),
            _Amount(
              label: 'প্রদেয়',
              value: pawn.totalPayable,
              emphasize: true,
            ),
          ],
        );
      },
    );
  }
}

class _PaymentsTab extends StatelessWidget {
  const _PaymentsTab({required this.payments});

  final List<CustomerPayment> payments;

  @override
  Widget build(BuildContext context) {
    if (payments.isEmpty) {
      return const _EmptyTab(
        icon: Icons.payments_outlined,
        message: 'এই গ্রাহকের কোনো পেমেন্ট রেকর্ড নেই',
      );
    }

    final theme = Theme.of(context);

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: payments.length,
      separatorBuilder: (context, index) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final payment = payments[index];
        final reference = payment.reference;

        return _RecordCard(
          title: payment.invoiceNo,
          status: payment.method,
          subtitle: formatDate(payment.date),
          amounts: [
            _Amount(label: 'পরিমাণ', value: payment.amount, emphasize: true),
          ],
          footer: reference == null
              ? null
              : Text(
                  'রেফারেন্স: $reference',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
        );
      },
    );
  }
}

class _RecordCard extends StatelessWidget {
  const _RecordCard({
    required this.title,
    required this.status,
    required this.subtitle,
    required this.amounts,
    this.footer,
  });

  final String title;
  final String status;
  final String subtitle;
  final List<_Amount> amounts;
  final Widget? footer;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    title,
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                _StatusChip(status: status),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              subtitle,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                for (final amount in amounts)
                  Expanded(child: _AmountTile(amount: amount)),
              ],
            ),
            if (footer != null) ...[const SizedBox(height: 8), footer!],
          ],
        ),
      ),
    );
  }
}

class _Amount {
  const _Amount({
    required this.label,
    required this.value,
    this.emphasize = false,
  });

  final String label;
  final double value;
  final bool emphasize;
}

class _AmountTile extends StatelessWidget {
  const _AmountTile({required this.amount});

  final _Amount amount;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          amount.label,
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
        const SizedBox(height: 2),
        FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Text(
            formatMoney(amount.value),
            style: theme.textTheme.bodyMedium?.copyWith(
              fontWeight: amount.emphasize ? FontWeight.w700 : FontWeight.w500,
              color: amount.emphasize ? theme.colorScheme.error : null,
            ),
          ),
        ),
      ],
    );
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.status});

  final String status;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final normalized = status.toLowerCase();
    final Color color;

    switch (normalized) {
      case 'completed':
      case 'redeemed':
        color = const Color(0xFF2E7D5B);
      case 'void':
      case 'forfeited':
        color = theme.colorScheme.error;
      case 'overdue':
        color = const Color(0xFFA66A00);
      default:
        color = theme.colorScheme.secondary;
    }

    final label = switch (normalized) {
      'completed' => 'সম্পন্ন',
      'void' => 'বাতিল',
      'active' => 'চলতি',
      'overdue' => 'মেয়াদোত্তীর্ণ',
      'redeemed' => 'খালাসকৃত',
      'forfeited' => 'বাজেয়াপ্ত',
      'cash' => 'নগদ',
      'bkash' => 'বিকাশ',
      'nagad' => 'নগদ (মোবাইল)',
      'card' => 'কার্ড',
      'bank' => 'ব্যাংক',
      _ => normalized.isEmpty ? '—' : normalized,
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: theme.textTheme.labelSmall?.copyWith(
          color: color,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

class _EmptyTab extends StatelessWidget {
  const _EmptyTab({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 44, color: theme.colorScheme.onSurfaceVariant.withValues(alpha: 0.5)),
            const SizedBox(height: 12),
            Text(
              message,
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
