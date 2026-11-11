import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/config/app_config.dart';
import '../../core/utils/format.dart';
import '../../models/pawn.dart';
import '../../models/sale.dart' show paymentMethodLabel, paymentMethods;
import '../../providers/auth_provider.dart';
import '../../providers/inventory_provider.dart';
import '../../providers/pawns_provider.dart';
import '../../services/document_service.dart';
import '../../widgets/state_handler.dart';

class PawnDetailScreen extends ConsumerStatefulWidget {
  const PawnDetailScreen({super.key, required this.pawnId});

  final int pawnId;

  @override
  ConsumerState<PawnDetailScreen> createState() => _PawnDetailScreenState();
}

class _PawnDetailScreenState extends ConsumerState<PawnDetailScreen> {
  bool _sharing = false;

  Future<void> _reload() async {
    ref.invalidate(pawnDetailProvider(widget.pawnId));
  }

  Future<void> _recordPayment() async {
    final payment = await _showPawnPaymentForm(context);

    if (payment == null) {
      return;
    }

    try {
      await ref.read(pawnServiceProvider).addPayment(
        widget.pawnId,
        amount: payment.amount,
        method: payment.method,
        note: payment.note,
      );

      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('পেমেন্ট জমা নেওয়া হয়েছে।')));
      ref.invalidate(pawnDetailProvider(widget.pawnId));
      ref.invalidate(pawnsControllerProvider);
    } on ApiException catch (error) {
      _showError(error.fieldError('amount') ?? error.message);
    }
  }

  Future<void> _redeem() async {
    final redeem = await _showPawnRedeemForm(
      context,
      totalPayable: ref.read(pawnDetailProvider(widget.pawnId)).value?.summary
          .totalPayable,
    );

    if (redeem == null) {
      return;
    }

    try {
      await ref.read(pawnServiceProvider).redeem(
        widget.pawnId,
        method: redeem.method,
        note: redeem.note,
      );

      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('বন্ধক খালাস সম্পন্ন হয়েছে — পণ্য ফেরত দেওয়া হয়েছে।')),
      );
      ref.invalidate(pawnDetailProvider(widget.pawnId));
      ref.invalidate(pawnsControllerProvider);
    } on ApiException catch (error) {
      _showError(error.fieldError('amount') ?? error.message);
    }
  }

  Future<void> _renew() async {
    final renew = await _showPawnRenewForm(context);

    if (renew == null) {
      return;
    }

    try {
      await ref.read(pawnServiceProvider).renew(
        widget.pawnId,
        termDays: renew.termDays,
        note: renew.note,
      );

      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('বন্ধক টিকিট নবায়ন সম্পন্ন হয়েছে।')));
      ref.invalidate(pawnDetailProvider(widget.pawnId));
      ref.invalidate(pawnsControllerProvider);
    } on ApiException catch (error) {
      _showError(error.fieldError('term_days') ?? error.message);
    }
  }

  Future<void> _shareTicket() async {
    setState(() => _sharing = true);

    try {
      final pawn = ref.read(pawnDetailProvider(widget.pawnId)).value;

      await ref.read(documentServiceProvider).downloadAndShare(
        apiBaseUrl: AppConfig.apiBaseUrl,
        webPath: '/pawns/${widget.pawnId}/pdf',
        fileName: pawnTicketFileName(
          widget.pawnId,
          pawn?.date,
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
    final detail = ref.watch(pawnDetailProvider(widget.pawnId));
    final auth = ref.watch(authControllerProvider);
    final user = auth is AuthAuthenticated ? auth.user : null;
    final canManage = user?.can('manage pawns') ?? false;

    return Scaffold(
      appBar: AppBar(
        title: Text(detail.value?.pawnNo ?? 'বন্ধক টিকিট'),
        actions: [
          IconButton(
            tooltip: 'বন্ধক টিকিট PDF শেয়ার',
            onPressed: _sharing ? null : _shareTicket,
            icon: _sharing
                ? const SizedBox(
                    height: 18,
                    width: 18,
                    child: CircularProgressIndicator(strokeWidth: 2.2),
                  )
                : const Icon(Icons.share_outlined),
          ),
        ],
      ),
      body: StateHandler<Pawn>(
        value: detail,
        onRetry: _reload,
        loadingMessage: 'বন্ধক লোড হচ্ছে…',
        builder: (context, pawn) {
          final theme = Theme.of(context);
          final summary = pawn.summary;
          final closed = pawn.isRedeemed || pawn.isForfeited;

          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              children: [
                _SummaryCard(pawn: pawn),
                if (canManage && !closed && summary.totalPayable > 0) ...[
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: _redeem,
                          icon: const Icon(Icons.check_circle_outline),
                          label: Text('খালাস · ${formatMoney(summary.totalPayable)}'),
                        ),
                      ),
                      const SizedBox(width: 8),
                      OutlinedButton(
                        onPressed: _renew,
                        child: const Text('নবায়ন'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  OutlinedButton.icon(
                    onPressed: _recordPayment,
                    icon: const Icon(Icons.payments_outlined),
                    label: const Text('টাকা জমা দিন'),
                  ),
                ],
                if (pawn.notes?.isNotEmpty == true) ...[
                  const SizedBox(height: 12),
                  Text(
                    'নোট: ${pawn.notes}',
                    style: theme.textTheme.bodySmall,
                  ),
                ],
                const _SectionHeader('বন্ধকী পণ্যসমূহ'),
                for (final item in pawn.items)
                  ListTile(
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    title: Text(item.description),
                    subtitle: Text(
                      '${item.karat}K · মোট ${item.grossWeight.toStringAsFixed(3)} g'
                      '${item.stoneWeight > 0 ? ' · পাথর ${item.stoneWeight.toStringAsFixed(3)} g' : ''}'
                      ' · নিট ${item.netWeight.toStringAsFixed(3)} g · '
                      'আনুমানিক ${formatMoney(item.estimatedValue)}',
                    ),
                    trailing: item.photoUrl == null
                        ? null
                        : Tooltip(
                            message: 'গ্রহণের সময় ছবি তোলা',
                            child: const Icon(Icons.photo_outlined),
                          ),
                  ),
                const _SectionHeader('পরিশোধের ইতিহাস'),
                if (pawn.payments.isEmpty)
                  Text(
                    'এখনো কোনো পেমেন্ট নেই',
                    style: theme.textTheme.bodySmall,
                  )
                else
                  for (final payment in pawn.payments)
                    ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        [
                          pawnPaymentTypeLabel(payment.type),
                          payment.method.isEmpty
                              ? null
                              : paymentMethodLabel(payment.method),
                          payment.userName ?? '',
                        ].where((part) => part?.isNotEmpty == true).join(' · '),
                      ),
                      subtitle: Text(
                        [
                          formatDate(payment.date),
                          if (payment.reference?.isNotEmpty == true)
                            payment.reference!,
                          if (payment.note?.isNotEmpty == true) payment.note!,
                        ].join(' · '),
                      ),
                      trailing: Text(
                        formatMoney(payment.amount),
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ),
                const _SectionHeader('বর্তমান স্থিতি (সার্ভার নির্ধারিত)'),
                _SummaryRow(
                  label: 'অবশিষ্ট মূলধন',
                  value: summary.outstandingPrincipal,
                ),
                _SummaryRow(label: 'পরিশোধিত মূলধন', value: summary.principalPaid),
                _SummaryRow(
                  label: 'সুদের হার',
                  text: 'মাসিক ${summary.interestRate.toStringAsFixed(2)}%',
                ),
                _SummaryRow(
                  label: 'মোট অর্জিত সুদ',
                  value: summary.interestAccrued,
                ),
                _SummaryRow(label: 'পরিশোধিত সুদ', value: summary.interestPaid),
                _SummaryRow(
                  label: 'বকেয়া সুদ',
                  value: summary.interestDue,
                ),
                Divider(color: theme.colorScheme.outlineVariant),
                _SummaryRow(label: 'সর্বমোট পরিশোধিত', value: summary.totalPaid),
                _SummaryRow(
                  label: 'খালাস করতে মোট প্রদেয়',
                  value: summary.totalPayable,
                  bold: true,
                ),
                if (summary.periods.isNotEmpty) ...[
                  const _SectionHeader(
                    'সুদের বিস্তারিত হিসাব',
                  ),
                  for (final period in summary.periods)
                    ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        '${formatDate(period.from)} → ${formatDate(period.to)}',
                      ),
                      subtitle: Text(
                        '${period.days} দিন · মূলধন '
                        '${formatMoney(period.principal)} এর ওপর',
                      ),
                      trailing: Text(
                        formatMoney(period.interest),
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.pawn});

  final Pawn pawn;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final statusLabel = pawn.isRedeemed
        ? 'খালাসকৃত'
        : pawn.isForfeited
        ? 'বাজেয়াপ্ত'
        : pawn.isOverdue
        ? 'মেয়াদোত্তীর্ণ'
        : 'চলতি';

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
                        pawn.pawnNo,
                        style: theme.textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      Text(
                        [
                          formatDate(pawn.date),
                          '',
                          pawn.userName ?? '',
                        ].where((part) => part.isNotEmpty).join(' · '),
                        style: theme.textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                Chip(
                  visualDensity: VisualDensity.compact,
                  label: Text(statusLabel),
                  backgroundColor: pawn.isRedeemed
                      ? theme.colorScheme.primaryContainer
                      : pawn.isOpen
                      ? (pawn.isOverdue
                            ? theme.colorScheme.errorContainer
                            : theme.colorScheme.secondaryContainer)
                      : theme.colorScheme.errorContainer,
                  labelStyle: TextStyle(
                    color: pawn.isRedeemed
                        ? theme.colorScheme.onPrimaryContainer
                        : pawn.isOpen && !pawn.isOverdue
                        ? theme.colorScheme.onSecondaryContainer
                        : theme.colorScheme.onErrorContainer,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text(
              pawn.customer == null
                  ? '—'
                  : '${pawn.customer!.name} · ${pawn.customer!.phone}',
              style: theme.textTheme.bodyMedium,
            ),
            const SizedBox(height: 12),
            Text(
              'মূলধন ${formatMoney(pawn.principal)} · '
              'মাসিক ${pawn.interestRate.toStringAsFixed(2)}%',
              style: theme.textTheme.bodyMedium,
            ),
            const SizedBox(height: 4),
            Text(
              pawn.dueDate == null
                  ? 'মেয়াদ নির্ধারিত নেই'
                  : 'মেয়াদ: ${formatDate(pawn.dueDate)}',
              style: theme.textTheme.bodyMedium?.copyWith(
                color: pawn.isOverdue ? theme.colorScheme.error : null,
              ),
            ),
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

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({
    required this.label,
    this.value,
    this.text,
    this.bold = false,
  });

  final String label;
  final double? value;
  final String? text;
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
          Text(
            text ?? formatMoney(value ?? 0),
            style: style,
          ),
        ],
      ),
    );
  }
}

typedef _PaymentChoice = ({double amount, String? method, String? note});

Future<_PaymentChoice?> _showPawnPaymentForm(BuildContext context) {
  return showModalBottomSheet<_PaymentChoice>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => const _PawnPaymentFormSheet(),
  );
}

class _PawnPaymentFormSheet extends StatefulWidget {
  const _PawnPaymentFormSheet();

  @override
  State<_PawnPaymentFormSheet> createState() => _PawnPaymentFormSheetState();
}

class _PawnPaymentFormSheetState extends State<_PawnPaymentFormSheet> {
  final _amountController = TextEditingController();
  final _noteController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  String? _method;

  @override
  void dispose() {
    _amountController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  void _submit() {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    Navigator.of(context).pop((
      amount: double.tryParse(_amountController.text.trim()) ?? 0,
      method: _method,
      note: _noteController.text.trim().isEmpty
          ? null
          : _noteController.text.trim(),
    ));
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('টাকা জমা দিন', style: Theme.of(context).textTheme.titleLarge),
              Text(
                'টাকা জমা হলে সার্ভার স্বয়ংক্রিয়ভাবে সুদ ও মূলধনে সমন্বয় করবে।',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _amountController,
                autofocus: true,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                validator: (value) {
                  final amount = double.tryParse(value ?? '');

                  if (amount == null || amount <= 0) {
                    return 'পরিমাণ শূন্যের বেশি হতে হবে';
                  }

                  return null;
                },
                decoration: const InputDecoration(
                  labelText: 'পরিমাণ (টাকা)',
                  prefixIcon: Icon(Icons.payments_outlined),
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _method,
                decoration: const InputDecoration(
                  labelText: 'পেমেন্ট মাধ্যম (ঐচ্ছিক)',
                  prefixIcon: Icon(Icons.credit_card_outlined),
                ),
                items: [
                  const DropdownMenuItem(child: Text('— নির্বাচন করুন —')),
                  for (final method in paymentMethods)
                    DropdownMenuItem(
                      value: method,
                      child: Text(paymentMethodLabel(method)),
                    ),
                ],
                onChanged: (value) => setState(() => _method = value),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _noteController,
                decoration: const InputDecoration(
                  labelText: 'নোট / মন্তব্য (ঐচ্ছিক)',
                  prefixIcon: Icon(Icons.note_outlined),
                ),
              ),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: _submit,
                child: const Text('পেমেন্ট নিশ্চিত করুন'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

typedef _RedeemChoice = ({String? method, String? note});

Future<_RedeemChoice?> _showPawnRedeemForm(
  BuildContext context, {
  double? totalPayable,
}) {
  return showModalBottomSheet<_RedeemChoice>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => _PawnRedeemFormSheet(totalPayable: totalPayable),
  );
}

class _PawnRedeemFormSheet extends StatefulWidget {
  const _PawnRedeemFormSheet({this.totalPayable});

  final double? totalPayable;

  @override
  State<_PawnRedeemFormSheet> createState() => _PawnRedeemFormSheetState();
}

class _PawnRedeemFormSheetState extends State<_PawnRedeemFormSheet> {
  final _noteController = TextEditingController();
  String? _method;

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  void _submit() {
    Navigator.of(context).pop((
      method: _method,
      note: _noteController.text.trim().isEmpty
          ? null
          : _noteController.text.trim(),
    ));
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('বন্ধক খালাস', style: theme.textTheme.titleLarge),
            Text(
              'খালাস করতে মোট প্রদেয় টাকা পরিশোধ করতে হবে: '
              '${widget.totalPayable == null ? '—' : formatMoney(widget.totalPayable!)}',
              style: theme.textTheme.bodySmall,
            ),
            const SizedBox(height: 16),
            DropdownButtonFormField<String>(
              initialValue: _method,
              decoration: const InputDecoration(
                labelText: 'পেমেন্ট মাধ্যম (ঐচ্ছিক)',
                prefixIcon: Icon(Icons.credit_card_outlined),
              ),
              items: [
                const DropdownMenuItem(child: Text('— নির্বাচন করুন —')),
                for (final method in paymentMethods)
                  DropdownMenuItem(
                    value: method,
                    child: Text(paymentMethodLabel(method)),
                  ),
              ],
              onChanged: (value) => setState(() => _method = value),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _noteController,
              decoration: const InputDecoration(
                labelText: 'মন্তব্য (ঐচ্ছিক)',
                prefixIcon: Icon(Icons.note_outlined),
              ),
            ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _submit,
              child: const Text('খালাস নিশ্চিত করুন'),
            ),
          ],
        ),
      ),
    );
  }
}

typedef _RenewChoice = ({int? termDays, String? note});

Future<_RenewChoice?> _showPawnRenewForm(BuildContext context) {
  return showModalBottomSheet<_RenewChoice>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => const _PawnRenewFormSheet(),
  );
}

class _PawnRenewFormSheet extends StatefulWidget {
  const _PawnRenewFormSheet();

  @override
  State<_PawnRenewFormSheet> createState() => _PawnRenewFormSheetState();
}

class _PawnRenewFormSheetState extends State<_PawnRenewFormSheet> {
  final _termDaysController = TextEditingController();
  final _noteController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  @override
  void dispose() {
    _termDaysController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  void _submit() {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    Navigator.of(context).pop((
      termDays: int.tryParse(_termDaysController.text.trim()),
      note: _noteController.text.trim().isEmpty
          ? null
          : _noteController.text.trim(),
    ));
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('বন্ধক নবায়ন', style: Theme.of(context).textTheme.titleLarge),
              Text(
                'বন্ধকের মেয়াদ বৃদ্ধি করা হবে। সর্বোচ্চ ৩৬৫ দিন মেয়াদ বাড়ানো যাবে।',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _termDaysController,
                autofocus: true,
                keyboardType: TextInputType.number,
                validator: (value) {
                  final termDays = int.tryParse(value ?? '');

                  if (termDays == null || termDays <= 0 || termDays > 365) {
                    return 'মেয়াদ অবশ্যই ১ থেকে ৩৬৫ দিনের মধ্যে হতে হবে';
                  }

                  return null;
                },
                decoration: const InputDecoration(
                  labelText: 'নতুন মেয়াদ (দিন)',
                  prefixIcon: Icon(Icons.calendar_month_outlined),
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _noteController,
                decoration: const InputDecoration(
                  labelText: 'নোট (ঐচ্ছিক)',
                  prefixIcon: Icon(Icons.note_outlined),
                ),
              ),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: _submit,
                child: const Text('নবায়ন নিশ্চিত করুন'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
