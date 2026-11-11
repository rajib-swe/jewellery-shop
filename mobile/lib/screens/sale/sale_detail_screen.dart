import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/config/app_config.dart';
import '../../core/utils/format.dart';
import '../../models/sale.dart';
import '../../providers/auth_provider.dart';
import '../../providers/inventory_provider.dart';
import '../../providers/sales_provider.dart';
import '../../services/document_service.dart';
import '../../widgets/state_handler.dart';
import 'sale_forms.dart';

class SaleDetailScreen extends ConsumerStatefulWidget {
  const SaleDetailScreen({super.key, required this.saleId});

  final int saleId;

  @override
  ConsumerState<SaleDetailScreen> createState() => _SaleDetailScreenState();
}

class _SaleDetailScreenState extends ConsumerState<SaleDetailScreen> {
  bool _sharing = false;

  Future<void> _reload() async {
    ref.invalidate(saleDetailProvider(widget.saleId));
  }

  Future<void> _recordPayment() async {
    final payment = await showSalePaymentForm(context);

    if (payment == null) {
      return;
    }

    try {
      final sale = await ref.read(saleServiceProvider).addPayment(
        widget.saleId,
        method: payment.method,
        amount: payment.amount,
        reference: payment.reference,
      );

      if (!mounted) {
        return;
      }

      ref.invalidate(saleDetailProvider(widget.saleId));
      ref.invalidate(salesControllerProvider);

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${formatMoney(payment.amount)} পেমেন্ট সম্পন্ন — অবশিষ্ট বকেয়া '
            '${formatMoney(sale.due)}।',
          ),
        ),
      );
    } on ApiException catch (error) {
      _showError(error.fieldError('amount') ?? error.message);
    }
  }

  Future<void> _voidSale() async {
    final reasonController = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('এই বিক্রয়টি বাতিল করবেন?'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'বিক্রয় বাতিল করলে পণ্যগুলো পুনরায় স্টকে ফেরত যাবে এবং '
              'সমস্ত পেমেন্ট বাতিল হবে। এটি আর ফিরিয়ে আনা যাবে না।',
            ),
            const SizedBox(height: 12),
            TextField(
              controller: reasonController,
              decoration: const InputDecoration(
                labelText: 'বাতিলের কারণ (ঐচ্ছিক)',
                prefixIcon: Icon(Icons.rule_outlined),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('বাতিল'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
              foregroundColor: Theme.of(context).colorScheme.onError,
            ),
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('বিক্রয় বাতিল করুন'),
          ),
        ],
      ),
    );

    if (confirmed != true) {
      return;
    }

    try {
      await ref
          .read(saleServiceProvider)
          .voidSale(widget.saleId, reason: reasonController.text.trim());

      if (!mounted) {
        return;
      }

      ref.invalidate(saleDetailProvider(widget.saleId));
      ref.invalidate(salesControllerProvider);
    } on ApiException catch (error) {
      _showError(error.fieldError('reason') ?? error.message);
    }
  }

  Future<void> _shareInvoice() async {
    setState(() => _sharing = true);

    try {
      final sale = ref.read(saleDetailProvider(widget.saleId)).value;

      await ref.read(documentServiceProvider).downloadAndShare(
        apiBaseUrl: AppConfig.apiBaseUrl,
        webPath: '/sales/${widget.saleId}/pdf',
        fileName: saleInvoiceFileName(
          widget.saleId,
          sale?.date,
        ),
      );
    } on ApiException catch (error) {
      _showError(error.message);
    } catch (error) {
      _showError(error.toString());
    } finally {
      if (mounted) {
        setState(() => _sharing = false);
      }
    }
  }

  void _showError(String message) {
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final detail = ref.watch(saleDetailProvider(widget.saleId));
    final auth = ref.watch(authControllerProvider);
    final user = auth is AuthAuthenticated ? auth.user : null;
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(detail.value?.invoiceNo ?? 'বিক্রয়'),
        actions: [
          IconButton(
            tooltip: 'ইনভয়েস PDF শেয়ার',
            onPressed: _sharing ? null : _shareInvoice,
            icon: _sharing
                ? const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(strokeWidth: 2.2),
                  )
                : const Icon(Icons.share_outlined),
          ),
          if (user?.can('void sales') ?? false)
            IconButton(
              tooltip: 'বিক্রয় বাতিল',
              onPressed: _voidSale,
              icon: const Icon(Icons.block_outlined),
            ),
        ],
      ),
      body: StateHandler<Sale>(
        value: detail,
        onRetry: _reload,
        loadingMessage: 'ইনভয়েস লোড হচ্ছে…',
        builder: (context, sale) {
          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              children: [
                _SummaryCard(sale: sale),
                if (!sale.isVoided && sale.due > 0) ...[
                  const SizedBox(height: 12),
                  if (user?.can('manage sales') ?? false)
                    FilledButton.icon(
                      onPressed: _recordPayment,
                      icon: const Icon(Icons.payments_outlined),
                      label: Text('পেমেন্ট জমা নিন · বকেয়া ${formatMoney(sale.due)}'),
                    ),
                ],
                if (sale.voidReason != null || sale.notes?.isNotEmpty == true)
                  _InfoCard(
                    title: 'নোট',
                    lines: [
                      if (sale.notes?.isNotEmpty == true) sale.notes!,
                    ],
                    trailing: sale.isVoided
                        ? Text(
                            'বাতিলকৃত${sale.voidedAt == null ? '' : ' · ${formatDate(sale.voidedAt)}'}'
                            '\n${sale.voidReason ?? ''}'.trim(),
                            style: TextStyle(color: theme.colorScheme.error),
                          )
                        : null,
                  )
                else if (sale.isVoided && sale.voidedAt != null)
                  _InfoCard(
                    title: 'বাতিলকৃত',
                    lines: [
                      '${formatDate(sale.voidedAt)} · ${sale.voidReason ?? ''}'.trim(),
                    ],
                    lineColor: theme.colorScheme.error,
                  ),
                if (sale.exchanges.isNotEmpty) ...[
                  const _SectionHeader('পুরাতন স্বর্ণ বিনিময়'),
                  for (final exchange in sale.exchanges)
                    ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      title: Text(exchange.description),
                      subtitle: Text(
                        '${exchange.karat}K · ${exchange.weight.toStringAsFixed(3)} g · '
                        'দর ${formatMoney(exchange.rate)}',
                      ),
                      trailing: Text(
                        '- ${formatMoney(exchange.amount)}',
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ),
                ],
                const _SectionHeader('পণ্য তালিকা'),
                for (final line in sale.items) ...[
                  ListTile(
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      [line.tagNo, line.name]
                          .where((part) => part.isNotEmpty)
                          .join(' · '),
                    ),
                    subtitle: Text(
                      '${line.karat}K · ${line.weight.toStringAsFixed(3)} g · '
                      'দর ${formatMoney(line.rate)} · মজুরি ${formatMoney(line.making)}'
                      '${line.stonePrice > 0 ? ' · পাথর ${formatMoney(line.stonePrice)}' : ''}',
                    ),
                    trailing: Text(
                      formatMoney(line.lineTotal),
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                  ),
                ],
                const _SectionHeader('পেমেন্ট বিবরণ'),
                if (sale.payments.isEmpty)
                  Text(
                    'কোনো পেমেন্ট নেই',
                    style: theme.textTheme.bodySmall,
                  )
                else
                  for (final payment in sale.payments)
                    ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        '${paymentMethodLabel(payment.method)} · '
                        '${payment.userName ?? '—'}',
                      ),
                      subtitle: Text(
                        [
                          formatDate(payment.createdAt),
                          if (payment.reference?.isNotEmpty == true)
                            payment.reference!,
                        ].join(' · '),
                      ),
                      trailing: Text(
                        formatMoney(payment.amount),
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ),
                const _SectionHeader('সর্বমোট হিসাব (সার্ভার নির্ধারিত)'),
                _TotalRow(label: 'সাবটোটাল', value: sale.subtotal),
                if (sale.discount > 0)
                  _TotalRow(label: 'ছাড়', value: -sale.discount),
                if (sale.vat > 0) _TotalRow(label: 'ভ্যাট', value: sale.vat),
                if (sale.exchangeAmount > 0)
                  _TotalRow(label: 'পুরাতন স্বর্ণ বিনিময়', value: -sale.exchangeAmount),
                Divider(color: theme.colorScheme.outlineVariant),
                _TotalRow(label: 'মোট', value: sale.total, bold: true),
                _TotalRow(label: 'পরিশোধিত', value: sale.paid),
                _TotalRow(
                  label: 'বকেয়া',
                  value: sale.due,
                  bold: sale.due > 0,
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.sale});

  final Sale sale;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final status = sale.isVoided ? 'Voided' : saleStatusLabel(sale.status);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        sale.invoiceNo,
                        style: theme.textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      Text(
                        [
                          formatDate(sale.date),
                          sale.userName ?? '',
                        ].where((part) => part.isNotEmpty).join(' · '),
                        style: theme.textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                Chip(
                  visualDensity: VisualDensity.compact,
                  label: Text(status),
                  backgroundColor: sale.isVoided
                      ? theme.colorScheme.errorContainer
                      : theme.colorScheme.primaryContainer,
                  labelStyle: TextStyle(
                    color: sale.isVoided
                        ? theme.colorScheme.onErrorContainer
                        : theme.colorScheme.onPrimaryContainer,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              sale.customer == null
                  ? 'সাধারণ ক্রেতা · কোনো গ্রাহক নেই'
                  : '${sale.customer!.name} · ${sale.customer!.phone}',
              style: theme.textTheme.bodyMedium,
            ),
            const SizedBox(height: 12),
            Text(
              sale.isVoided
                  ? 'এই ইনভয়েসটি বাতিল করা হয়েছে।'
                  : sale.due > 0
                  ? 'অবশিষ্ট বকেয়া ${formatMoney(sale.due)}'
                  : 'সম্পূর্ণ পরিশোধিত',
              style: theme.textTheme.titleMedium?.copyWith(
                color: sale.isVoided || sale.due > 0
                    ? theme.colorScheme.error
                    : theme.colorScheme.primary,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({
    required this.title,
    required this.lines,
    this.trailing,
    this.lineColor,
  });

  final String title;
  final List<String> lines;
  final Color? lineColor;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card.filled(
      margin: const EdgeInsets.only(top: 12),
      color: (lineColor ?? theme.colorScheme.primary)
          .withValues(alpha: 0.08),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: theme.textTheme.titleSmall),
                  if (trailing == null) ...[
                    const SizedBox(height: 4),
                    for (final line in lines)
                      Text(line, style: theme.textTheme.bodySmall),
                  ],
                ],
              ),
            ),
            ?trailing,
          ],
        ),
      ),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader(this.title);

  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 20, bottom: 4),
      child: Text(
        title,
        style: Theme.of(
          context,
        ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600),
      ),
    );
  }
}

class _TotalRow extends StatelessWidget {
  const _TotalRow({
    required this.label,
    required this.value,
    this.bold = false,
  });

  final String label;
  final double value;
  final bool bold;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final style = bold
        ? theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600)
        : theme.textTheme.bodyMedium;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style)),
          Text(formatMoney(value), style: style),
        ],
      ),
    );
  }
}
