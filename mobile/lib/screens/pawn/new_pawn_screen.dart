import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api/api_exception.dart';
import '../../core/utils/format.dart';
import '../../models/pawn.dart';
import '../../providers/pawns_provider.dart';
import '../../widgets/customer_picker.dart';
import '../../widgets/state_handler.dart';
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

  final List<PawnItemInput> _pawnItems = [];
  final List<String> _pawnItemLabels = [];

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

  Future<void> _pickCustomer() async {
    final customer = await showCustomerPicker(context);

    if (customer == null) {
      return;
    }

    setState(() {
      _customerId = customer.id;
      _customerName = '${customer.name} (${customer.phone})';
    });
  }

  Future<void> _addPawnItem() async {
    final item = await showPawnItemForm(context);

    if (item == null) {
      return;
    }

    setState(() {
      _pawnItems.add(item.input);
      _pawnItemLabels.add(item.label);
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
      return;
    }

    if (_pawnItems.isEmpty) {
      setState(() => _error = 'অন্তত একটি বন্ধকী পণ্য যোগ করুন।');
      return;
    }

    final principal = double.tryParse(_principalController.text.trim()) ?? 0;
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
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('নতুন বন্ধক গ্রহণ')),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
          children: [
            Text('গ্রাহক', style: theme.textTheme.titleSmall),
            const SizedBox(height: 4),
            InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: _pickCustomer,
              child: InputDecorator(
                decoration: InputDecoration(
                  prefixIcon: const Icon(Icons.person_outline),
                  suffixIcon: Icon(
                    _customerId == null
                        ? Icons.chevron_right
                        : Icons.check_circle,
                  ),
                ),
                child: Text(_customerName ?? 'গ্রাহক নির্বাচন করুন'),
              ),
            ),
            _SectionTitle(title: 'মূলধন ও শর্তাবলী'),
            TextFormField(
              controller: _principalController,
              autofocus: true,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(
                labelText: 'প্রদত্ত মূলধন / ঋণ (টাকা)',
                prefixIcon: Icon(Icons.payments_outlined),
              ),
            ),
            const SizedBox(height: 12),
            TextFormField(
              controller: _interestRateController,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: const InputDecoration(
                labelText: 'মাসিক সুদের হার % (খালি থাকলে ডিফল্ট)',
                prefixIcon: Icon(Icons.percent_outlined),
              ),
            ),
            const SizedBox(height: 12),
            InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: _pickDueDate,
              child: InputDecorator(
                decoration: const InputDecoration(
                  labelText: 'পরিশোধের শেষ তারিখ (খালি থাকলে ডিফল্ট)',
                  prefixIcon: Icon(Icons.event_outlined),
                  suffixIcon: Icon(Icons.chevron_right),
                ),
                child: Text(
                  _dueDate == null ? 'তারিখ নির্বাচন করুন' : formatDate(_dueDate),
                ),
              ),
            ),
            _SectionTitle(title: 'বন্ধকী পণ্য (${_pawnItems.length})'),
            for (var index = 0; index < _pawnItems.length; index++)
              Card(
                child: ListTile(
                  title: Text(_pawnItemLabels[index]),
                  trailing: IconButton(
                    tooltip: 'পণ্য সরান',
                    icon: const Icon(Icons.delete_outline),
                    onPressed: () => setState(() {
                      _pawnItems.removeAt(index);
                      _pawnItemLabels.removeAt(index);
                    }),
                  ),
                ),
              ),
            FilledButton.tonalIcon(
              onPressed: _addPawnItem,
              icon: const Icon(Icons.playlist_add),
              label: const Text('বন্ধকী পণ্য যোগ করুন'),
            ),
            _SectionTitle(title: 'নোট (মন্তব্য)'),
            TextFormField(
              controller: _notesController,
              maxLines: 2,
              decoration: const InputDecoration(
                labelText: 'নোট',
                prefixIcon: Icon(Icons.note_outlined),
              ),
            ),
            const SizedBox(height: 16),
            const _PawnHint(
              icon: Icons.info_outline,
              message:
                  'অ্যাপে কখনো সুদ হিসাব হয় না — টিকিট তৈরির পর সার্ভারের স্বয়ংক্রিয় হিসাব সব তথ্য নির্ভুল রাখে।',
            ),
          ],
        ),
      ),
      bottomSheet: _error == null
          ? null
          : Container(
              width: double.infinity,
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
              color: Theme.of(context).colorScheme.errorContainer,
              child: Text(
                _error!,
                style: TextStyle(
                  color: Theme.of(context).colorScheme.onErrorContainer,
                ),
              ),
            ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
      floatingActionButton: _submitting
          ? StateHandlerView.loading(message: 'বন্ধক গ্রহণ করা হচ্ছে…')
          : SizedBox(
              width: 240,
              child: FilledButton.icon(
                onPressed: _submit,
                icon: const Icon(Icons.handyman_outlined),
                label: const Text('বন্ধক নিশ্চিত করুন'),
              ),
            ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title});

  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 20, bottom: 6),
      child: Text(
        title,
        style: Theme.of(
          context,
        ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600),
      ),
    );
  }
}

class _PawnHint extends StatelessWidget {
  const _PawnHint({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card.filled(
      margin: const EdgeInsets.only(top: 4, bottom: 4),
      color: theme.colorScheme.primaryContainer.withValues(alpha: 0.25),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Icon(icon, size: 20, color: theme.colorScheme.primary),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                message,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ),
          ],
        ),
      ),
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
    final estimatedValue =
        double.tryParse(_estimatedValueController.text.trim()) ?? 0;

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
              Text(
                'বন্ধকী পণ্য',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              Text(
                'মোট ওজন সম্পূর্ণ পণ্যের ওজন; পাথর বাদ দিয়ে সার্ভার নিট স্বর্ণের ওজন হিসাব করবে।',
                style: Theme.of(context).textTheme.bodySmall,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _descriptionController,
                autofocus: true,
                validator: (value) =>
                    (value == null || value.trim().isEmpty)
                        ? 'পণ্যের বিবরণ আবশ্যক'
                        : null,
                decoration: const InputDecoration(
                  labelText: 'পণ্যের বিবরণ',
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
                validator: (value) {
                  final weight = double.tryParse(value ?? '');
                  if (weight == null || weight <= 0) {
                    return 'শূন্যের বেশি মোট ওজন দিন';
                  }

                  return null;
                },
                decoration: const InputDecoration(
                  labelText: 'মোট ওজন (গ্রাম)',
                  prefixIcon: Icon(Icons.scale_outlined),
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _stoneWeightController,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                validator: (value) {
                  final stoneWeight = double.tryParse(value ?? '');
                  final grossWeight = double.tryParse(
                    _grossWeightController.text,
                  );

                  if (stoneWeight != null &&
                      grossWeight != null &&
                      stoneWeight >= grossWeight) {
                    return 'পাথরের ওজন অবশ্যই মোট ওজনের চেয়ে কম হতে হবে';
                  }

                  return null;
                },
                decoration: const InputDecoration(
                  labelText: 'পাথরের ওজন (গ্রাম, ঐচ্ছিক)',
                  prefixIcon: Icon(Icons.diamond_outlined),
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _estimatedValueController,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                validator: (value) {
                  final estimatedValue = double.tryParse(value ?? '');

                  if (estimatedValue == null || estimatedValue < 0) {
                    return 'আনুমানিক মূল্য আবশ্যক';
                  }

                  return null;
                },
                decoration: const InputDecoration(
                  labelText: 'আনুমানিক মূল্য (টাকা)',
                  prefixIcon: Icon(Icons.attach_money_outlined),
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
