import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/theme/app_colors.dart';
import '../../core/utils/format.dart';
import '../../models/sale.dart';
import '../../providers/sales_provider.dart';
import '../../widgets/customer_picker.dart';
import '../../widgets/item_picker.dart';
import 'sale_forms.dart';

class NewSaleScreen extends ConsumerStatefulWidget {
  const NewSaleScreen({super.key});

  @override
  ConsumerState<NewSaleScreen> createState() => _NewSaleScreenState();
}

class _NewSaleScreenState extends ConsumerState<NewSaleScreen> {
  final _formKey = GlobalKey<FormState>();

  int? _customerId;
  String? _customerName;
  String? _customerPhone;
  String? _customerCode;

  final List<SaleLineInput> _saleItems = [];
  final List<_SaleLineDisplay> _saleLinesDisplay = [];

  final List<SalePaymentInput> _payments = [];
  final List<SaleExchangeInput> _exchanges = [];

  final _discountController = TextEditingController();
  final _notesController = TextEditingController();

  bool _submitting = false;
  String? _error;

  @override
  void dispose() {
    _discountController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  double get _totalWeight =>
      _saleLinesDisplay.fold(0.0, (sum, item) => sum + item.weight);

  double get _totalExchangeWeight =>
      _exchanges.fold(0.0, (sum, ex) => sum + ex.weight);

  double get _totalPayments =>
      _payments.fold(0.0, (sum, p) => sum + p.amount);

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

  Future<void> _addCatalogueItem() async {
    final item = await showItemPicker(context);

    if (item == null || !mounted) {
      return;
    }

    if (_saleLinesDisplay.any((d) => d.itemId == item.id)) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${item.tagNo} পণ্যটি ইতিমধ্যে এই বিক্রয়ে যোগ করা হয়েছে।'),
        ),
      );
      return;
    }

    setState(() {
      _saleItems.add(SaleLineInput(itemId: item.id));
      _saleLinesDisplay.add(
        _SaleLineDisplay(
          name: item.name,
          tagNo: item.tagNo,
          weight: item.netWeight,
          karat: item.karat,
          makingType: item.makingType,
          makingValue: item.makingValue,
          stonePrice: item.stonePrice > 0 ? item.stonePrice : null,
          isManual: false,
          itemId: item.id,
        ),
      );
      _error = null;
    });
  }

  Future<void> _addManualLine() async {
    final result = await showSaleLineForm(context);

    if (result == null || !mounted) {
      return;
    }

    final input = result.input;

    setState(() {
      _saleItems.add(input);
      _saleLinesDisplay.add(
        _SaleLineDisplay(
          name: input.name ?? 'হাতে লেখা পণ্য',
          tagNo: null,
          weight: input.weight ?? 0,
          karat: input.karat ?? 22,
          makingType: input.makingType,
          makingValue: input.makingValue,
          stonePrice: (input.stonePrice != null && input.stonePrice! > 0)
              ? input.stonePrice
              : null,
          isManual: true,
          itemId: null,
        ),
      );
      _error = null;
    });
  }

  void _removeItem(int index) {
    setState(() {
      _saleItems.removeAt(index);
      _saleLinesDisplay.removeAt(index);
    });
  }

  Future<void> _addExchange() async {
    final exchange = await showExchangeForm(context);

    if (exchange == null) {
      return;
    }

    setState(() => _exchanges.add(exchange));
  }

  void _removeExchange(int index) {
    setState(() => _exchanges.removeAt(index));
  }

  Future<void> _addPayment() async {
    final payment = await showSalePaymentForm(context);

    if (payment == null) {
      return;
    }

    setState(() => _payments.add(payment));
  }

  void _removePayment(int index) {
    setState(() => _payments.removeAt(index));
  }

  Future<void> _submit() async {
    if (_saleItems.isEmpty) {
      setState(() => _error = 'ইনভয়েসে অন্তত একটি পণ্য যোগ করুন।');
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('ইনভয়েসে অন্তত একটি পণ্য যোগ করুন।'),
          backgroundColor: AppColors.error,
        ),
      );
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      final sale = await ref.read(saleServiceProvider).create(
        customerId: _customerId,
        items: _saleItems,
        payments: _payments,
        exchanges: _exchanges,
        discount: double.tryParse(_discountController.text.trim()),
        notes: _notesController.text.trim(),
      );

      if (!mounted) {
        return;
      }

      ref.invalidate(salesControllerProvider);
      ref.invalidate(saleDetailProvider);

      context.pushReplacement('/sale/${sale.id}');
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _submitting = false;
        _error = error.fieldError('items') ??
            error.fieldError('payments') ??
            error.fieldError('exchanges') ??
            error.fieldError('discount') ??
            error.message;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_error ?? 'বিক্রয় সংরক্ষণ ব্যর্থ হয়েছে।'),
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
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('নতুন বিক্রয়'),
        actions: [
          if (_saleItems.isNotEmpty)
            Padding(
              padding: const EdgeInsets.only(right: 16),
              child: Center(
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.primary.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    '${_saleItems.length} টি পণ্য',
                    style: theme.textTheme.labelMedium?.copyWith(
                      color: theme.colorScheme.primary,
                      fontWeight: FontWeight.w700,
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
              title: 'ক্রেতার তথ্য',
              subtitle: 'নগদ খরিদ্দার অথবা তালিকাভুক্ত নির্দিষ্ট গ্রাহক',
            ),
            const SizedBox(height: 8),
            _CustomerCard(
              customerId: _customerId,
              customerName: _customerName,
              customerPhone: _customerPhone,
              customerCode: _customerCode,
              onPick: _pickCustomer,
              onClear: _clearCustomer,
            ),
            const SizedBox(height: 24),

            // 2. Items Section
            _SectionHeader(
              icon: Icons.diamond_outlined,
              title: 'বিক্রয়কৃত অলংকার / পণ্য',
              subtitle: 'স্টক থেকে নির্বাচন করুন অথবা সরাসরি লিখে যোগ করুন',
              badgeText: _saleItems.isNotEmpty ? '${_saleItems.length}' : null,
            ),
            const SizedBox(height: 10),

            // Add Item Action Buttons
            Row(
              children: [
                Expanded(
                  child: FilledButton.tonalIcon(
                    onPressed: _addCatalogueItem,
                    icon: const Icon(Icons.qr_code_scanner, size: 20),
                    label: const Text('স্টক থেকে যোগ'),
                    style: FilledButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 13),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _addManualLine,
                    icon: const Icon(Icons.edit_note, size: 20),
                    label: const Text('হাতে লেখা পণ্য'),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 13),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),

            // Item List or Empty State
            if (_saleItems.isEmpty)
              _EmptyBox(
                icon: Icons.shopping_bag_outlined,
                title: 'কোনো পণ্য যোগ করা হয়নি',
                message:
                    'উপরে "স্টক থেকে যোগ" অথবা "হাতে লেখা পণ্য" বোতামে চেপে ইনভয়েসে পণ্য যুক্ত করুন।',
              )
            else
              for (var i = 0; i < _saleLinesDisplay.length; i++)
                _SaleItemCard(
                  item: _saleLinesDisplay[i],
                  index: i + 1,
                  onDelete: () => _removeItem(i),
                ),
            const SizedBox(height: 24),

            // 3. Old Gold Exchange Section
            _SectionHeader(
              icon: Icons.swap_horiz,
              title: 'পুরাতন স্বর্ণ বিনিময় (ঐচ্ছিক)',
              subtitle: 'গ্রাহকের পুরাতন স্বর্ণ জমা নিয়ে মূল্য সমন্বয়',
              badgeText: _exchanges.isNotEmpty ? '${_exchanges.length}' : null,
              action: TextButton.icon(
                onPressed: _addExchange,
                icon: const Icon(Icons.add_circle_outline, size: 18),
                label: const Text('স্বর্ণ যোগ করুন'),
              ),
            ),
            const SizedBox(height: 8),

            if (_exchanges.isEmpty)
              _EmptyBox(
                icon: Icons.scale_outlined,
                title: 'কোনো পুরাতন স্বর্ণ নেই',
                message:
                    'গ্রাহক যদি পুরাতন সোনা দিয়ে নতুন গয়না বদল করতে চান, তবে এখানে যোগ করুন।',
                compact: true,
              )
            else
              for (var i = 0; i < _exchanges.length; i++)
                _ExchangeCard(
                  exchange: _exchanges[i],
                  index: i + 1,
                  onDelete: () => _removeExchange(i),
                ),
            const SizedBox(height: 24),

            // 4. Payment Collection Section
            _SectionHeader(
              icon: Icons.payments_outlined,
              title: 'পেমেন্ট গ্রহণ (ঐচ্ছিক)',
              subtitle: 'নগদ, ব্যাংক বা মোবাইল ব্যাংকিংয়ে প্রাপ্ত টাকা',
              badgeText: _payments.isNotEmpty ? '${_payments.length}' : null,
              action: TextButton.icon(
                onPressed: _addPayment,
                icon: const Icon(Icons.add_card, size: 18),
                label: const Text('পেমেন্ট যোগ'),
              ),
            ),
            const SizedBox(height: 8),

            if (_payments.isEmpty)
              _EmptyBox(
                icon: Icons.account_balance_wallet_outlined,
                title: 'কোনো পেমেন্ট যোগ করা হয়নি',
                message:
                    'এখনই আংশিক বা পুরো টাকা জমা নিতে "+ পেমেন্ট যোগ" বাটনে চাপুন (বাকি রাখতে চাইলে বাদ দিন)।',
                compact: true,
              )
            else ...[
              for (var i = 0; i < _payments.length; i++)
                _PaymentCard(
                  payment: _payments[i],
                  onDelete: () => _removePayment(i),
                ),
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: AppColors.success.withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.success.withValues(alpha: 0.2)),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'মোট প্রাপ্ত জমা:',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: AppColors.success,
                        ),
                      ),
                      Text(
                        formatMoney(_totalPayments),
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: AppColors.success,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
            const SizedBox(height: 24),

            // 5. Discount & Notes Section
            _SectionHeader(
              icon: Icons.receipt_outlined,
              title: 'ছাড় ও বিশেষ নোট',
              subtitle: 'বিশেষ ডিসকাউন্ট বা ইনভয়েস মন্তব্য',
            ),
            const SizedBox(height: 10),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    TextFormField(
                      controller: _discountController,
                      keyboardType:
                          const TextInputType.numberWithOptions(decimal: true),
                      decoration: const InputDecoration(
                        labelText: 'বিশেষ ছাড় (টাকা)',
                        hintText: '০.০০',
                        prefixIcon: Icon(Icons.local_offer_outlined),
                        prefixText: '৳ ',
                      ),
                      onChanged: (_) => setState(() {}),
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _notesController,
                      maxLines: 2,
                      decoration: const InputDecoration(
                        labelText: 'ইনভয়েস নোট বা মন্তব্য (ঐচ্ছিক)',
                        hintText: 'যেমন: বিশেষ অর্ডার বা কাস্টমার ডেলিভারি নির্দেশিকা',
                        prefixIcon: Icon(Icons.edit_note),
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),

            // 6. Live Summary Overview Card
            _SummaryCard(
              itemCount: _saleItems.length,
              totalWeight: _totalWeight,
              exchangeCount: _exchanges.length,
              totalExchangeWeight: _totalExchangeWeight,
              totalPayments: _totalPayments,
              discount: double.tryParse(_discountController.text.trim()) ?? 0,
            ),
          ],
        ),
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: theme.colorScheme.surface,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
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
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.error,
                          fontWeight: FontWeight.w600,
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
                        _saleItems.isEmpty
                            ? 'পণ্য নির্বাচন করুন'
                            : '${_saleItems.length} টি পণ্য যুক্ত হয়েছে',
                        style: theme.textTheme.labelMedium?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                      Text(
                        _saleItems.isEmpty
                            ? '০.০০০ গ্রাম'
                            : '${_totalWeight.toStringAsFixed(3)} গ্রাম (${formatWeightInVori(_totalWeight)})',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: theme.colorScheme.primary,
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
                      : const Icon(Icons.receipt_long, size: 20),
                  label: Text(_submitting ? 'সংরক্ষণ হচ্ছে…' : 'বিক্রয় সম্পন্ন করুন'),
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

class _SaleLineDisplay {
  const _SaleLineDisplay({
    required this.name,
    this.tagNo,
    required this.weight,
    required this.karat,
    this.makingType,
    this.makingValue,
    this.stonePrice,
    this.isManual = false,
    this.itemId,
  });

  final String name;
  final String? tagNo;
  final double weight;
  final int karat;
  final String? makingType;
  final double? makingValue;
  final double? stonePrice;
  final bool isManual;
  final int? itemId;
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
    final theme = Theme.of(context);

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.all(7),
          decoration: BoxDecoration(
            color: theme.colorScheme.primary.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, size: 18, color: theme.colorScheme.primary),
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
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  if (badgeText != null) ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                      decoration: BoxDecoration(
                        color: theme.colorScheme.primary,
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
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
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
    required this.onClear,
  });

  final int? customerId;
  final String? customerName;
  final String? customerPhone;
  final String? customerCode;
  final VoidCallback onPick;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    if (customerId == null) {
      return Card(
        child: InkWell(
          onTap: onPick,
          borderRadius: BorderRadius.circular(16),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 22,
                  backgroundColor: theme.colorScheme.onSurfaceVariant.withValues(alpha: 0.12),
                  child: Icon(
                    Icons.person_outline,
                    color: theme.colorScheme.onSurfaceVariant,
                    size: 24,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'নগদ খরিদ্দার (সাধারণ গ্রাহক)',
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'গ্রাহকের নাম বা খতিয়ানে রাখতে নির্বাচন করুন',
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ),
                ),
                FilledButton.tonal(
                  onPressed: onPick,
                  style: FilledButton.styleFrom(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                    minimumSize: const Size(0, 36),
                  ),
                  child: const Text('গ্রাহক খুঁজুন'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Card(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: theme.colorScheme.primary.withValues(alpha: 0.5), width: 1.4),
      ),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            CircleAvatar(
              radius: 22,
              backgroundColor: theme.colorScheme.primary,
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
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Row(
                    children: [
                      if (customerPhone != null && customerPhone!.isNotEmpty) ...[
                        Icon(Icons.phone_outlined,
                            size: 13, color: theme.colorScheme.onSurfaceVariant),
                        const SizedBox(width: 4),
                        Text(
                          customerPhone!,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurfaceVariant,
                          ),
                        ),
                        const SizedBox(width: 8),
                      ],
                      if (customerCode != null && customerCode!.isNotEmpty)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                          decoration: BoxDecoration(
                            color: theme.colorScheme.surfaceContainerHighest,
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text(
                            customerCode!,
                            style: theme.textTheme.labelSmall?.copyWith(
                              fontWeight: FontWeight.w600,
                              color: theme.colorScheme.onSurfaceVariant,
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

class _SaleItemCard extends StatelessWidget {
  const _SaleItemCard({
    required this.item,
    required this.index,
    required this.onDelete,
  });

  final _SaleLineDisplay item;
  final int index;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: item.isManual
                        ? Colors.blue.withValues(alpha: 0.12)
                        : AppColors.success.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    item.isManual ? 'হাতে লেখা' : 'ট্যাগ: ${item.tagNo ?? ''}',
                    style: TextStyle(
                      color: item.isManual ? Colors.blue.shade800 : AppColors.success,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.primary.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    '${item.karat}K',
                    style: TextStyle(
                      color: theme.colorScheme.primary,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                const Spacer(),
                IconButton(
                  tooltip: 'পণ্য সরান',
                  icon: const Icon(Icons.delete_outline, color: AppColors.error, size: 20),
                  onPressed: onDelete,
                  visualDensity: VisualDensity.compact,
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              item.name,
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 6,
              children: [
                _SpecPill(
                  icon: Icons.scale_outlined,
                  label: '${item.weight.toStringAsFixed(3)} গ্রাম (${formatWeightInVori(item.weight)})',
                ),
                if (item.makingValue != null && item.makingValue! > 0)
                  _SpecPill(
                    icon: Icons.build_outlined,
                    label: _formatMaking(item.makingType, item.makingValue!),
                  ),
                if (item.stonePrice != null && item.stonePrice! > 0)
                  _SpecPill(
                    icon: Icons.diamond_outlined,
                    label: 'পাথর: ${formatMoney(item.stonePrice)}',
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _formatMaking(String? type, double value) {
    return switch (type) {
      'per_gram' => 'মজুরি: প্রতি গ্রাম ৳${value.toStringAsFixed(0)}',
      'percent' => 'মজুরি: ${value.toStringAsFixed(1)}%',
      _ => 'মজুরি: ৳${value.toStringAsFixed(0)}',
    };
  }
}

class _ExchangeCard extends StatelessWidget {
  const _ExchangeCard({
    required this.exchange,
    required this.index,
    required this.onDelete,
  });

  final SaleExchangeInput exchange;
  final int index;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: Colors.amber.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(Icons.swap_horiz, color: Colors.amber, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    exchange.description,
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${exchange.weight.toStringAsFixed(3)} গ্রাম (${formatWeightInVori(exchange.weight)}) · ${exchange.karat}K',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
            IconButton(
              tooltip: 'বিনিময় সরান',
              icon: const Icon(Icons.delete_outline, color: AppColors.error, size: 20),
              onPressed: onDelete,
            ),
          ],
        ),
      ),
    );
  }
}

class _PaymentCard extends StatelessWidget {
  const _PaymentCard({
    required this.payment,
    required this.onDelete,
  });

  final SalePaymentInput payment;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: AppColors.success.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(
                Icons.account_balance_wallet_outlined,
                color: AppColors.success,
                size: 20,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    paymentMethodLabel(payment.method),
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  if (payment.reference != null && payment.reference!.isNotEmpty)
                    Text(
                      'রেফারেন্স: ${payment.reference}',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                ],
              ),
            ),
            Text(
              formatMoney(payment.amount),
              style: theme.textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
                color: AppColors.success,
              ),
            ),
            IconButton(
              tooltip: 'পেমেন্ট সরান',
              icon: const Icon(Icons.delete_outline, color: AppColors.error, size: 20),
              onPressed: onDelete,
            ),
          ],
        ),
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({
    required this.itemCount,
    required this.totalWeight,
    required this.exchangeCount,
    required this.totalExchangeWeight,
    required this.totalPayments,
    required this.discount,
  });

  final int itemCount;
  final double totalWeight;
  final int exchangeCount;
  final double totalExchangeWeight;
  final double totalPayments;
  final double discount;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(Icons.analytics_outlined,
                    size: 20, color: theme.colorScheme.primary),
                const SizedBox(width: 8),
                Text(
                  'ইনভয়েস সংক্ষিপ্ত হিসাব',
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            _SummaryRow(
              label: 'মোট অলংকার সংখ্যা:',
              value: '$itemCount টি',
            ),
            const SizedBox(height: 8),
            _SummaryRow(
              label: 'মোট স্বর্ণের ওজন:',
              value: '${totalWeight.toStringAsFixed(3)} গ্রাম (${formatWeightInVori(totalWeight)})',
            ),
            if (exchangeCount > 0) ...[
              const SizedBox(height: 8),
              _SummaryRow(
                label: 'পুরাতন স্বর্ণ সমন্বয়:',
                value: '$exchangeCount টি (${totalExchangeWeight.toStringAsFixed(3)} গ্রাম)',
              ),
            ],
            if (totalPayments > 0) ...[
              const SizedBox(height: 8),
              _SummaryRow(
                label: 'মোট পরিশোধিত জমা:',
                value: formatMoney(totalPayments),
                color: AppColors.success,
              ),
            ],
            if (discount > 0) ...[
              const SizedBox(height: 8),
              _SummaryRow(
                label: 'বিশেষ ছাড়:',
                value: formatMoney(discount),
                color: AppColors.warning,
              ),
            ],
            const Divider(height: 20),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(
                  Icons.info_outline,
                  size: 16,
                  color: theme.colorScheme.onSurfaceVariant,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'স্বর্ণের সর্বশেষ দর, ভ্যাট ও বকেয়ার সঠিক হিসাব সার্ভার থেকে স্বয়ংক্রিয়ভাবে যুক্ত হবে।',
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
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
    this.color,
  });

  final String label;
  final String value;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: theme.textTheme.bodyMedium?.copyWith(
            color: theme.colorScheme.onSurfaceVariant,
          ),
        ),
        Text(
          value,
          style: theme.textTheme.bodyMedium?.copyWith(
            fontWeight: FontWeight.w700,
            color: color ?? theme.colorScheme.onSurface,
          ),
        ),
      ],
    );
  }
}

class _SpecPill extends StatelessWidget {
  const _SpecPill({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(6),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(width: 5),
          Text(
            label,
            style: theme.textTheme.bodySmall?.copyWith(
              fontWeight: FontWeight.w600,
              color: theme.colorScheme.onSurface,
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptyBox extends StatelessWidget {
  const _EmptyBox({
    required this.icon,
    required this.title,
    required this.message,
    this.compact = false,
  });

  final IconData icon;
  final String title;
  final String message;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.3),
      child: Padding(
        padding: EdgeInsets.symmetric(
          horizontal: 16,
          vertical: compact ? 14 : 22,
        ),
        child: Row(
          children: [
            CircleAvatar(
              radius: compact ? 18 : 22,
              backgroundColor: theme.colorScheme.primary.withValues(alpha: 0.1),
              child: Icon(
                icon,
                color: theme.colorScheme.primary,
                size: compact ? 20 : 24,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: theme.textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    message,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
