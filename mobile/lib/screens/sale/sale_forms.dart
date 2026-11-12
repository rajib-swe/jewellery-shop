import 'package:flutter/material.dart';

import '../../models/sale.dart';
import 'karat.dart';

Future<({String label, SaleLineInput input})?> showSaleLineForm(
  BuildContext context,
) {
  return showModalBottomSheet<({String label, SaleLineInput input})>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => const _SaleLineFormSheet(),
  );
}

class _SaleLineFormSheet extends StatefulWidget {
  const _SaleLineFormSheet();

  @override
  State<_SaleLineFormSheet> createState() => _SaleLineFormSheetState();
}

class _SaleLineFormSheetState extends State<_SaleLineFormSheet> {
  final _nameController = TextEditingController();
  final _weightController = TextEditingController();
  final _makingValueController = TextEditingController();
  final _stonePriceController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  int _karat = 22;
  String _makingType = 'fixed';

  static const _makingTypes = <(String, String)>[
    ('fixed', 'নির্দিষ্ট'),
    ('per_gram', 'প্রতি গ্রাম'),
    ('percent', 'শতকরা (%)'),
  ];

  @override
  void dispose() {
    _nameController.dispose();
    _weightController.dispose();
    _makingValueController.dispose();
    _stonePriceController.dispose();
    super.dispose();
  }

  void _submit() {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    final name = _nameController.text.trim();
    final weight = double.tryParse(_weightController.text.trim()) ?? 0;
    final makingValue = double.tryParse(_makingValueController.text.trim()) ?? 0;
    final stonePrice = double.tryParse(_stonePriceController.text.trim()) ?? 0;

    Navigator.of(context).pop((
      label: '$name · ${weight.toStringAsFixed(3)} g · ${_karat}K',
      input: SaleLineInput(
        name: name,
        karat: _karat,
        weight: weight,
        makingType: _makingType,
        makingValue: makingValue,
        stonePrice: stonePrice,
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
              Text(
                'হাতে লেখা পণ্য',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              Text(
                'ওজন গ্রামে দিন; সার্ভার নির্ধারিত স্বর্ণের দর প্রয়োগ করবে।',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _nameController,
                autofocus: true,
                keyboardType: TextInputType.text,
                textInputAction: TextInputAction.next,
                textCapitalization: TextCapitalization.words,
                validator: (value) =>
                    (value == null || value.trim().isEmpty)
                        ? 'পণ্যের নাম আবশ্যক'
                        : null,
                decoration: const InputDecoration(
                  labelText: 'পণ্যের নাম *',
                  hintText: 'যেমন: সোনার চেইন, আংটি, বালা',
                  prefixIcon: Icon(Icons.label_outline),
                ),
              ),
              const SizedBox(height: 12),
              KaratChoiceField(
                selected: _karat,
                onChanged: (karat) => setState(() => _karat = karat),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _weightController,
                autofocus: false,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                validator: (value) {
                  final weight = double.tryParse(value ?? '');
                  if (weight == null || weight <= 0) {
                    return 'ওজন শূন্যের বেশি হতে হবে';
                  }

                  return null;
                },
                decoration: const InputDecoration(
                  labelText: 'ওজন (গ্রাম)',
                  prefixIcon: Icon(Icons.scale_outlined),
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _makingType,
                decoration: const InputDecoration(
                  labelText: 'মজুরির ধরন',
                  prefixIcon: Icon(Icons.build_outlined),
                ),
                items: [
                  for (final (value, label) in _makingTypes)
                    DropdownMenuItem(value: value, child: Text(label)),
                ],
                onChanged: (value) =>
                    setState(() => _makingType = value ?? 'fixed'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _makingValueController,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(
                  labelText: switch (_makingType) {
                    'per_gram' => 'প্রতি গ্রাম মজুরি (টাকা)',
                    'percent' => 'মজুরি শতকরা (%)',
                    _ => 'নির্দিষ্ট মজুরি (টাকা)',
                  },
                  prefixIcon: const Icon(Icons.build_outlined),
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _stonePriceController,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(
                  labelText: 'পাথরের মূল্য (টাকা)',
                  prefixIcon: Icon(Icons.diamond_outlined),
                ),
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

Future<SalePaymentInput?> showSalePaymentForm(BuildContext context) {
  return showModalBottomSheet<SalePaymentInput>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => const _SalePaymentFormSheet(),
  );
}

class _SalePaymentFormSheet extends StatefulWidget {
  const _SalePaymentFormSheet();

  @override
  State<_SalePaymentFormSheet> createState() => _SalePaymentFormSheetState();
}

class _SalePaymentFormSheetState extends State<_SalePaymentFormSheet> {
  final _amountController = TextEditingController();
  final _referenceController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  String _method = 'cash';

  @override
  void dispose() {
    _amountController.dispose();
    _referenceController.dispose();
    super.dispose();
  }

  void _submit() {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    final amount = double.tryParse(_amountController.text.trim()) ?? 0;

    Navigator.of(context).pop(
      SalePaymentInput(
        method: _method,
        amount: amount,
        reference: _referenceController.text.trim().isEmpty
            ? null
            : _referenceController.text.trim(),
      ),
    );
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
              Text(
                'পেমেন্ট',
                style: Theme.of(context).textTheme.titleLarge,
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
                  labelText: 'পেমেন্ট মাধ্যম',
                  prefixIcon: Icon(Icons.credit_card_outlined),
                ),
                items: [
                  for (final method in paymentMethods)
                    DropdownMenuItem(
                      value: method,
                      child: Text(paymentMethodLabel(method)),
                    ),
                ],
                onChanged: (value) => setState(() => _method = value ?? 'cash'),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _referenceController,
                decoration: const InputDecoration(
                  labelText: 'রেফারেন্স (ঐচ্ছিক)',
                  prefixIcon: Icon(Icons.tag_outlined),
                ),
              ),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: _submit,
                child: const Text('পেমেন্ট যোগ করুন'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

Future<SaleExchangeInput?> showExchangeForm(BuildContext context) {
  return showModalBottomSheet<SaleExchangeInput>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => const _ExchangeFormSheet(),
  );
}

class _ExchangeFormSheet extends StatefulWidget {
  const _ExchangeFormSheet();

  @override
  State<_ExchangeFormSheet> createState() => _ExchangeFormSheetState();
}

class _ExchangeFormSheetState extends State<_ExchangeFormSheet> {
  final _descriptionController = TextEditingController();
  final _weightController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  int _karat = 22;

  @override
  void dispose() {
    _descriptionController.dispose();
    _weightController.dispose();
    super.dispose();
  }

  void _submit() {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    final description = _descriptionController.text.trim();
    final weight = double.tryParse(_weightController.text.trim()) ?? 0;

    Navigator.of(context).pop(
      SaleExchangeInput(
        description: description,
        karat: _karat,
        weight: weight,
      ),
    );
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
              Text(
                'পুরাতন স্বর্ণ বিনিময়',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              Text(
                'সার্ভার আজকের দরে পুরাতন স্বর্ণের মূল্য হিসাব করে '
                'ইনভয়েস থেকে বাদ দেবে।',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _descriptionController,
                autofocus: true,
                keyboardType: TextInputType.text,
                textInputAction: TextInputAction.next,
                textCapitalization: TextCapitalization.words,
                validator: (value) =>
                    (value == null || value.trim().isEmpty)
                        ? 'বিবরণ আবশ্যক'
                        : null,
                decoration: const InputDecoration(
                  labelText: 'বিবরণ *',
                  hintText: 'যেমন: পুরাতন সোনার চেইন বা আংটি',
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
                controller: _weightController,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                validator: (value) {
                  final weight = double.tryParse(value ?? '');
                  if (weight == null || weight <= 0) {
                    return 'ওজন শূন্যের বেশি হতে হবে';
                  }

                  return null;
                },
                decoration: const InputDecoration(
                  labelText: 'ওজন (গ্রাম)',
                  prefixIcon: Icon(Icons.scale_outlined),
                ),
              ),
              const SizedBox(height: 20),
              FilledButton(
                onPressed: _submit,
                child: const Text('বিনিময় যোগ করুন'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
