import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/format.dart';
import '../../models/pawn.dart';
import '../../providers/pawns_provider.dart';
import '../../widgets/customer_form_sheet.dart';
import '../../widgets/customer_picker.dart';
import '../sale/karat.dart';

class NewPawnScreen extends ConsumerStatefulWidget {
  const NewPawnScreen({super.key});

  @override
  ConsumerState<NewPawnScreen> createState() => _NewPawnScreenState();
}

class _NewPawnScreenState extends ConsumerState<NewPawnScreen> {
  final _formKey = GlobalKey<FormState>();

  int? _customerId;
  String? _customerName;
  String? _customerPhone;
  String? _customerCode;

  final List<PawnItemInput> _pawnItems = [];
  final List<_PawnItemDisplay> _pawnItemsDisplay = [];

  final _principalController = TextEditingController();
  final _interestRateController = TextEditingController();
  final _notesController = TextEditingController();

  DateTime? _dueDate;

  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _principalController.dispose();
    _interestRateController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  double get _totalWeight =>
      _pawnItemsDisplay.fold(0.0, (sum, item) => sum + item.weight);

  Future<void> _pickCustomer() async {
    final customer = await showCustomerPicker(context);

    if (customer == null) {
      return;
    }

    setState(() {
      _customerId = customer.id;
      _customerName = customer.name;
      _customerPhone = customer.phone;
      _customerCode = customer.code;
      _error = null;
    });
  }

  Future<void> _addNewCustomer() async {
    final customer = await showCustomerFormSheet(context);

    if (customer == null) {
      return;
    }

    setState(() {
      _customerId = customer.id;
      _customerName = customer.name;
      _customerPhone = customer.phone;
      _customerCode = customer.code;
      _error = null;
    });
  }

  void _clearCustomer() {
    setState(() {
      _customerId = null;
      _customerName = null;
      _customerPhone = null;
      _customerCode = null;
    });
  }

  Future<void> _addPawnItem() async {
    final result = await showPawnItemForm(context);

    if (result == null || !mounted) {
      return;
    }

    setState(() {
      _pawnItems.add(result.input);
      _pawnItemsDisplay.add(
        _PawnItemDisplay(
          description: result.input.description,
          karat: result.input.karat,
          weight: result.input.grossWeight,
        ),
      );
      _error = null;
    });
  }

  void _removeItem(int index) {
    setState(() {
      _pawnItems.removeAt(index);
      _pawnItemsDisplay.removeAt(index);
    });
  }

  Future<void> _pickDueDate() async {
    final now = DateTime.now();
    final date = await showDatePicker(
      context: context,
      initialDate: _dueDate ?? now.add(const Duration(days: 30)),
      firstDate: now,
      lastDate: now.add(const Duration(days: 365 * 3)),
    );

    if (date == null) {
      return;
    }

    setState(() => _dueDate = date);
  }

  Future<void> _submit() async {
    final customerId = _customerId;

    if (customerId == null) {
      setState(() => _error = 'প্রতিটি বন্ধকের জন্য গ্রাহক নির্বাচন আবশ্যক।');
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('অনুগ্রহ করে গ্রাহক নির্বাচন করুন।'),
          backgroundColor: AppColors.error,
        ),
      );
      return;
    }

    if (_pawnItems.isEmpty) {
      setState(() => _error = 'অন্তত একটি বন্ধকী স্বর্ণালংকার যোগ করুন।');
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('অন্তত একটি বন্ধকী স্বর্ণালংকার যোগ করুন।'),
          backgroundColor: AppColors.error,
        ),
      );
      return;
    }

    final principal = double.tryParse(_principalController.text.trim()) ?? 0;
    if (principal <= 0) {
      setState(() => _error = 'প্রদত্ত মূলধন / ঋণ টাকার পরিমাণ সঠিকভাবে দিন।');
      return;
    }

    final interestRate = double.tryParse(_interestRateController.text.trim());

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      final pawn = await ref.read(pawnServiceProvider).create(
        customerId: customerId,
        principal: principal,
        items: _pawnItems,
        interestRate: interestRate,
        dueDate: _dueDate == null ? null : formatIsoDate(_dueDate!),
        notes: _notesController.text.trim(),
      );

      if (!mounted) {
        return;
      }

      ref.invalidate(pawnsControllerProvider);
      ref.invalidate(pawnDetailProvider);

      context.pushReplacement('/pawn/${pawn.id}');
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _submitting = false;
        _error = error.fieldError('items') ??
            error.fieldError('principal') ??
            error.fieldError('customer_id') ??
            error.fieldError('interest_rate') ??
            error.fieldError('due_date') ??
            error.message;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_error ?? 'বন্ধক সংরক্ষণ ব্যর্থ হয়েছে।'),
          backgroundColor: AppColors.error,
        ),
      );
    } catch (e) {
      if (!mounted) {
        return;
      }

      setState(() {
        _submitting = false;
        _error = 'একটি ত্রুটি ঘটেছে: $e';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final principal = double.tryParse(_principalController.text.trim()) ?? 0;

    return Scaffold(
      appBar: AppBar(
        title: const Text('নতুন বন্ধক গ্রহণ'),
        actions: [
          if (_pawnItems.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(right: 16),
              child: Center(
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    '${_pawnItems.length} টি পণ্য',
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 120),
          children: [
            // 1. Customer Section
            _SectionHeader(
              icon: Icons.person_outline,
              title: 'গ্রাহকের তথ্য *',
              subtitle: 'বন্ধক গ্রহণকারী গ্রাহক নির্বাচন বা তৈরি করুন',
            ),
            const SizedBox(height: 8),
            _CustomerCard(
              customerId: _customerId,
              customerName: _customerName,
              customerPhone: _customerPhone,
              customerCode: _customerCode,
              onPick: _pickCustomer,
              onAddNew: _addNewCustomer,
              onClear: _clearCustomer,
            ),
            const SizedBox(height: 24),

            // 2. Loan Amount & Terms
            _SectionHeader(
              icon: Icons.account_balance_wallet_outlined,
              title: 'মূলধন ও ঋণের শর্তাবলী',
              subtitle: 'ঋণের পরিমাণ, সুদের হার ও পরিশোধের মেয়াদ',
            ),
            const SizedBox(height: 10),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    TextFormField(
                      controller: _principalController,
                      keyboardType:
                          const TextInputType.numberWithOptions(decimal: true),
                      textInputAction: TextInputAction.next,
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 17,
                        color: AppColors.ink,
                      ),
                      decoration: const InputDecoration(
                        labelText: 'প্রদত্ত মূলধন / ঋণ (টাকা) *',
                        hintText: 'যেমন: ২০০০০',
                        prefixIcon: Icon(Icons.payments_outlined),
                        prefixText: '৳ ',
                      ),
                      onChanged: (_) => setState(() {}),
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _interestRateController,
                      keyboardType:
                          const TextInputType.numberWithOptions(decimal: true),
                      textInputAction: TextInputAction.next,
                      style: const TextStyle(color: AppColors.ink, fontWeight: FontWeight.w600),
                      decoration: const InputDecoration(
                        labelText: 'মাসিক সুদের হার (%) (খালি থাকলে ডিফল্ট)',
                        hintText: 'যেমন: ৩.৫০',
                        prefixIcon: Icon(Icons.percent),
                        suffixText: '% প্রতি মাস',
                      ),
                      onChanged: (_) => setState(() {}),
                    ),
                    const SizedBox(height: 14),
                    InkWell(
                      onTap: _pickDueDate,
                      borderRadius: BorderRadius.circular(14),
                      child: InputDecorator(
                        decoration: const InputDecoration(
                          labelText: 'পরিশোধের শেষ তারিখ (ঐচ্ছিক)',
                          prefixIcon: Icon(Icons.event_outlined),
                          suffixIcon: Icon(Icons.calendar_month, color: AppColors.primary),
                        ),
                        child: Text(
                          _dueDate == null
                              ? 'তারিখ নির্বাচন করুন (ডিফল্ট: ১ মাস)'
                              : formatDate(_dueDate),
                          style: TextStyle(
                            color: _dueDate == null
                                ? AppColors.inkSubtle
                                : AppColors.ink,
                            fontWeight: _dueDate == null
                                ? FontWeight.normal
                                : FontWeight.w600,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),

            // 3. Pawn Items Section
            _SectionHeader(
              icon: Icons.diamond_outlined,
              title: 'বন্ধকী স্বর্ণালংকার',
              subtitle: 'জমা রাখা অলংকারের বিবরণ, ক্যারেট ও ওজন',
              badgeText: _pawnItems.isNotEmpty ? '${_pawnItems.length}' : null,
              action: FilledButton.tonalIcon(
                onPressed: _addPawnItem,
                icon: const Icon(Icons.add, size: 18),
                label: const Text('পণ্য যোগ'),
                style: FilledButton.styleFrom(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  minimumSize: const Size(0, 36),
                ),
              ),
            ),
            const SizedBox(height: 10),

            if (_pawnItems.isEmpty)
              Card(
                color: AppColors.surfaceVariant,
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Row(
                    children: [
                      CircleAvatar(
                        radius: 22,
                        backgroundColor: AppColors.primary.withValues(alpha: 0.1),
                        child: const Icon(Icons.scale, color: AppColors.primary, size: 24),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'কোনো স্বর্ণালংকার যোগ করা হয়নি',
                              style: TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 14,
                                color: AppColors.ink,
                              ),
                            ),
                            const SizedBox(height: 2),
                            const Text(
                              'বন্ধক রাখতে উপরে "+ পণ্য যোগ" বাটনে চাপুন।',
                              style: TextStyle(color: AppColors.inkMuted, fontSize: 13),
                            ),
                          ],
                        ),
                      ),
                      FilledButton.tonal(
                        onPressed: _addPawnItem,
                        child: const Text('যোগ করুন'),
                      ),
                    ],
                  ),
                ),
              )
            else
              for (var i = 0; i < _pawnItemsDisplay.length; i++)
                _PawnItemCard(
                  item: _pawnItemsDisplay[i],
                  index: i + 1,
                  onDelete: () => _removeItem(i),
                ),
            const SizedBox(height: 24),

            // 4. Notes Section
            _SectionHeader(
              icon: Icons.note_outlined,
              title: 'মন্তব্য ও নোট (ঐচ্ছিক)',
              subtitle: 'বন্ধক রসিদের বিশেষ মন্তব্য',
            ),
            const SizedBox(height: 10),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: TextFormField(
                  controller: _notesController,
                  maxLines: 2,
                  style: const TextStyle(color: AppColors.ink),
                  decoration: const InputDecoration(
                    labelText: 'নোট বা মন্তব্য',
                    hintText: 'যেমন: গ্রাহক বিশেষ শর্তে বন্ধক রেখেছেন',
                    prefixIcon: Icon(Icons.edit_note),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 24),

            // 5. Live Summary Card
            _PawnSummaryCard(
              principal: principal,
              itemCount: _pawnItems.length,
              totalWeight: _totalWeight,
              interestRate: double.tryParse(_interestRateController.text.trim()),
              dueDate: _dueDate,
            ),
          ],
        ),
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: AppColors.surface,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.08),
              offset: const Offset(0, -3),
              blurRadius: 10,
            ),
          ],
        ),
        padding: EdgeInsets.fromLTRB(
          16,
          12,
          16,
          12 + MediaQuery.paddingOf(context).bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (_error != null)
              Container(
                margin: const EdgeInsets.only(bottom: 10),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(
                  color: AppColors.error.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: AppColors.error.withValues(alpha: 0.3)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.error_outline, size: 18, color: AppColors.error),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _error!,
                        style: const TextStyle(
                          color: AppColors.error,
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        _pawnItems.isEmpty
                            ? 'অলংকার যোগ করুন'
                            : '${_pawnItems.length} টি পণ্য · ${_totalWeight.toStringAsFixed(3)} g',
                        style: const TextStyle(
                          color: AppColors.inkMuted,
                          fontSize: 13,
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                      Text(
                        principal <= 0 ? '৳ ০.০০' : formatMoney(principal),
                        style: const TextStyle(
                          fontSize: 19,
                          fontWeight: FontWeight.w800,
                          color: AppColors.primary,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                FilledButton.icon(
                  onPressed: _submitting ? null : _submit,
                  style: FilledButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
                    minimumSize: const Size(160, 48),
                  ),
                  icon: _submitting
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.handyman_outlined, size: 20),
                  label: Text(_submitting ? 'সংরক্ষণ হচ্ছে…' : 'বন্ধক নিশ্চিত করুন'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

// -------------------------------------------------------------
// Helper Widgets
// -------------------------------------------------------------

class _PawnItemDisplay {
  const _PawnItemDisplay({
    required this.description,
    required this.karat,
    required this.weight,
  });

  final String description;
  final int karat;
  final double weight;
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({
    required this.icon,
    required this.title,
    required this.subtitle,
    this.badgeText,
    this.action,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final String? badgeText;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.all(7),
          decoration: BoxDecoration(
            color: AppColors.primary.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, size: 18, color: AppColors.primary),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 15,
                      color: AppColors.ink,
                    ),
                  ),
                  if (badgeText != null) ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                      decoration: BoxDecoration(
                        color: AppColors.primary,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        badgeText!,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: const TextStyle(
                  color: AppColors.inkMuted,
                  fontSize: 13,
                ),
              ),
            ],
          ),
        ),
        ?action,
      ],
    );
  }
}

class _CustomerCard extends StatelessWidget {
  const _CustomerCard({
    required this.customerId,
    required this.customerName,
    required this.customerPhone,
    required this.customerCode,
    required this.onPick,
    required this.onAddNew,
    required this.onClear,
  });

  final int? customerId;
  final String? customerName;
  final String? customerPhone;
  final String? customerCode;
  final VoidCallback onPick;
  final VoidCallback onAddNew;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    if (customerId == null) {
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            children: [
              Row(
                children: [
                  CircleAvatar(
                    radius: 22,
                    backgroundColor: AppColors.outlineLight,
                    child: const Icon(
                      Icons.person_outline,
                      color: AppColors.inkMuted,
                      size: 24,
                    ),
                  ),
                  const SizedBox(width: 14),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'গ্রাহক নির্বাচন করুন *',
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 15,
                            color: AppColors.ink,
                          ),
                        ),
                        SizedBox(height: 2),
                        Text(
                          'বন্ধক রসিদ তৈরির জন্য গ্রাহক আবশ্যক',
                          style: TextStyle(
                            color: AppColors.inkMuted,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: onPick,
                      icon: const Icon(Icons.person_search, size: 18),
                      label: const Text('গ্রাহক খুঁজুন'),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 10),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton.tonalIcon(
                      onPressed: onAddNew,
                      icon: const Icon(Icons.person_add, size: 18),
                      label: const Text('নতুন গ্রাহক'),
                      style: FilledButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 10),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      );
    }

    return Card(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: AppColors.primary.withValues(alpha: 0.5), width: 1.4),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            CircleAvatar(
              radius: 22,
              backgroundColor: AppColors.primary,
              child: Text(
                customerName != null && customerName!.isNotEmpty
                    ? customerName![0].toUpperCase()
                    : 'G',
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    customerName ?? 'অজ্ঞাত',
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 15,
                      color: AppColors.ink,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Row(
                    children: [
                      if (customerPhone != null && customerPhone!.isNotEmpty) ...[
                        const Icon(Icons.phone_outlined,
                            size: 13, color: AppColors.inkMuted),
                        const SizedBox(width: 4),
                        Text(
                          customerPhone!,
                          style: const TextStyle(
                            color: AppColors.inkMuted,
                            fontSize: 13,
                          ),
                        ),
                        const SizedBox(width: 8),
                      ],
                      if (customerCode != null && customerCode!.isNotEmpty)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                          decoration: BoxDecoration(
                            color: AppColors.surfaceVariant,
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text(
                            customerCode!,
                            style: const TextStyle(
                              fontWeight: FontWeight.w600,
                              color: AppColors.inkMuted,
                              fontSize: 11,
                            ),
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            IconButton(
              tooltip: 'গ্রাহক পরিবর্তন',
              icon: const Icon(Icons.sync_alt, size: 20),
              onPressed: onPick,
            ),
            IconButton(
              tooltip: 'গ্রাহক বাদ দিন',
              icon: const Icon(Icons.close, size: 20),
              onPressed: onClear,
            ),
          ],
        ),
      ),
    );
  }
}

class _PawnItemCard extends StatelessWidget {
  const _PawnItemCard({
    required this.item,
    required this.index,
    required this.onDelete,
  });

  final _PawnItemDisplay item;
  final int index;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(Icons.diamond_outlined, color: AppColors.primary, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.description,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                      color: AppColors.ink,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                        decoration: BoxDecoration(
                          color: AppColors.primary.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          '${item.karat}K',
                          style: const TextStyle(
                            color: AppColors.primary,
                            fontSize: 11,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        '${item.weight.toStringAsFixed(3)} গ্রাম (${formatWeightInVori(item.weight)})',
                        style: const TextStyle(
                          color: AppColors.inkMuted,
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            IconButton(
              tooltip: 'পণ্য সরান',
              icon: const Icon(Icons.delete_outline, color: AppColors.error, size: 20),
              onPressed: onDelete,
            ),
          ],
        ),
      ),
    );
  }
}

class _PawnSummaryCard extends StatelessWidget {
  const _PawnSummaryCard({
    required this.principal,
    required this.itemCount,
    required this.totalWeight,
    required this.interestRate,
    required this.dueDate,
  });

  final double principal;
  final int itemCount;
  final double totalWeight;
  final double? interestRate;
  final DateTime? dueDate;

  @override
  Widget build(BuildContext context) {
    return Card(
      color: AppColors.surfaceVariant,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Row(
              children: [
                Icon(Icons.analytics_outlined, size: 20, color: AppColors.primary),
                SizedBox(width: 8),
                Text(
                  'বন্ধকের সারসংক্ষেপ',
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 15,
                    color: AppColors.ink,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            _SummaryRow(
              label: 'প্রদত্ত ঋণ / মূলধন:',
              value: principal > 0 ? formatMoney(principal) : '৳ ০.০০',
              isBold: true,
              valueColor: AppColors.primary,
            ),
            const SizedBox(height: 8),
            _SummaryRow(
              label: 'মোট বন্ধকী পণ্য:',
              value: '$itemCount টি',
            ),
            const SizedBox(height: 8),
            _SummaryRow(
              label: 'মোট স্বর্ণের ওজন:',
              value: '${totalWeight.toStringAsFixed(3)} গ্রাম (${formatWeightInVori(totalWeight)})',
            ),
            if (interestRate != null) ...[
              const SizedBox(height: 8),
              _SummaryRow(
                label: 'মাসিক সুদের হার:',
                value: '$interestRate%',
              ),
            ],
            if (dueDate != null) ...[
              const SizedBox(height: 8),
              _SummaryRow(
                label: 'পরিশোধের শেষ তারিখ:',
                value: formatDate(dueDate),
              ),
            ],
            const Divider(height: 20),
            const Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.info_outline,
                  size: 16,
                  color: AppColors.inkMuted,
                ),
                SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'বন্ধক গ্রহণের পর স্বয়ংক্রিয়ভাবে রসিদ ও টিকিট নম্বর তৈরি হবে।',
                    style: TextStyle(
                      color: AppColors.inkMuted,
                      fontSize: 12,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  const _SummaryRow({
    required this.label,
    required this.value,
    this.isBold = false,
    this.valueColor,
  });

  final String label;
  final String value;
  final bool isBold;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: const TextStyle(
            color: AppColors.inkMuted,
            fontSize: 13,
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontWeight: isBold ? FontWeight.w800 : FontWeight.w700,
            color: valueColor ?? AppColors.ink,
            fontSize: isBold ? 15 : 13,
          ),
        ),
      ],
    );
  }
}

Future<({String label, PawnItemInput input})?> showPawnItemForm(
  BuildContext context,
) {
  return showModalBottomSheet<({String label, PawnItemInput input})>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => const _PawnItemFormSheet(),
  );
}

class _PawnItemFormSheet extends StatefulWidget {
  const _PawnItemFormSheet();

  @override
  State<_PawnItemFormSheet> createState() => _PawnItemFormSheetState();
}

class _PawnItemFormSheetState extends State<_PawnItemFormSheet> {
  final _descriptionController = TextEditingController();
  final _grossWeightController = TextEditingController();
  final _stoneWeightController = TextEditingController();
  final _estimatedValueController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  int _karat = 22;

  @override
  void dispose() {
    _descriptionController.dispose();
    _grossWeightController.dispose();
    _stoneWeightController.dispose();
    _estimatedValueController.dispose();
    super.dispose();
  }

  void _submit() {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    final description = _descriptionController.text.trim();
    final grossWeight = double.tryParse(_grossWeightController.text.trim()) ?? 0;
    final stoneWeight = double.tryParse(_stoneWeightController.text.trim()) ?? 0;
    final estimatedValue = double.tryParse(_estimatedValueController.text.trim()) ?? 0;

    Navigator.of(context).pop((
      label: '$description · ${grossWeight.toStringAsFixed(3)} g · ${_karat}K',
      input: PawnItemInput(
        description: description,
        karat: _karat,
        grossWeight: grossWeight,
        stoneWeight: stoneWeight,
        estimatedValue: estimatedValue,
      ),
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
              const Text(
                'বন্ধকী স্বর্ণালংকার যোগ',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.ink),
              ),
              const Text(
                'অলংকারের বিবরণ, ওজন ও আনুমানিক মূল্য দিন।',
                style: TextStyle(fontSize: 13, color: AppColors.inkMuted),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _descriptionController,
                autofocus: true,
                style: const TextStyle(color: AppColors.ink, fontWeight: FontWeight.w600),
                validator: (value) =>
                    (value == null || value.trim().isEmpty)
                        ? 'অলংকারের বিবরণ আবশ্যক'
                        : null,
                decoration: const InputDecoration(
                  labelText: 'অলংকারের বিবরণ (যেমন: সোনার চেইন) *',
                  prefixIcon: Icon(Icons.description_outlined),
                ),
              ),
              const SizedBox(height: 12),
              KaratChoiceField(
                selected: _karat,
                onChanged: (karat) => setState(() => _karat = karat),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _grossWeightController,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                style: const TextStyle(color: AppColors.ink, fontWeight: FontWeight.w600),
                validator: (value) {
                  final weight = double.tryParse(value ?? '');
                  if (weight == null || weight <= 0) {
                    return 'মোট ওজন শূন্যের বেশি হতে হবে';
                  }
                  return null;
                },
                decoration: const InputDecoration(
                  labelText: 'মোট ওজন (গ্রাম) *',
                  hintText: 'যেমন: ১১.৬৬৪ (১ ভরি)',
                  prefixIcon: Icon(Icons.scale_outlined),
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _stoneWeightController,
                      keyboardType:
                          const TextInputType.numberWithOptions(decimal: true),
                      style: const TextStyle(color: AppColors.ink),
                      decoration: const InputDecoration(
                        labelText: 'পাথরের ওজন (গ্রাম)',
                        hintText: '০.০০০',
                        prefixIcon: Icon(Icons.grain_outlined),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextFormField(
                      controller: _estimatedValueController,
                      keyboardType:
                          const TextInputType.numberWithOptions(decimal: true),
                      style: const TextStyle(color: AppColors.ink, fontWeight: FontWeight.w600),
                      validator: (value) {
                        final val = double.tryParse(value ?? '');
                        if (val == null || val < 0) {
                          return 'আনুমানিক মূল্য দিন';
                        }
                        return null;
                      },
                      decoration: const InputDecoration(
                        labelText: 'আনুমানিক মূল্য *',
                        hintText: '০.০০',
                        prefixIcon: Icon(Icons.payments_outlined),
                        prefixText: '৳ ',
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: _submit,
                child: const Text('পণ্য যোগ করুন'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
