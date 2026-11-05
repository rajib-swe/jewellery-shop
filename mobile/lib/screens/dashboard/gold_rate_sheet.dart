import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api/api_exception.dart';
import '../../core/utils/format.dart';
import '../../models/gold_rate.dart';
import '../../providers/dashboard_provider.dart';

Future<GoldRate?> showGoldRateSheet(BuildContext context) {
  return showModalBottomSheet<GoldRate>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => const GoldRateSheet(),
  );
}

class GoldRateSheet extends ConsumerStatefulWidget {
  const GoldRateSheet({super.key});

  @override
  ConsumerState<GoldRateSheet> createState() => _GoldRateSheetState();
}

class _GoldRateSheetState extends ConsumerState<GoldRateSheet> {
  static const List<int> _karats = [24, 22, 21, 18];

  final _formKey = GlobalKey<FormState>();
  final _rateController = TextEditingController();

  int _karat = 22;
  DateTime _effectiveDate = DateTime.now();
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _rateController.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _effectiveDate,
      firstDate: DateTime(now.year - 5),
      lastDate: now,
    );

    if (picked != null) {
      setState(() => _effectiveDate = picked);
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    FocusScope.of(context).unfocus();
    setState(() {
      _saving = true;
      _error = null;
    });

    try {
      final rate = await ref
          .read(goldRateServiceProvider)
          .create(
            karat: _karat,
            ratePerGram: double.parse(_rateController.text.trim()),
            effectiveDate: _effectiveDate,
          );

      if (!mounted) {
        return;
      }

      Navigator.of(context).pop(rate);
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _error =
            error.fieldError('karat') ??
            error.fieldError('rate_per_gram') ??
            error.fieldError('effective_date') ??
            error.message;
      });
    } finally {
      if (mounted) {
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        child: Form(
          key: _formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  height: 4,
                  width: 44,
                  decoration: BoxDecoration(
                    color: theme.colorScheme.outline,
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Text('Update gold rate', style: theme.textTheme.titleLarge),
              const SizedBox(height: 4),
              Text(
                'The shop uses the latest rate entered for each karat.',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.outline,
                ),
              ),
              const SizedBox(height: 24),
              DropdownButtonFormField<int>(
                initialValue: _karat,
                decoration: const InputDecoration(
                  labelText: 'Karat',
                  prefixIcon: Icon(Icons.workspace_premium_outlined),
                ),
                items: [
                  for (final karat in _karats)
                    DropdownMenuItem(value: karat, child: Text('${karat}K')),
                ],
                onChanged: _saving
                    ? null
                    : (value) => setState(() => _karat = value ?? _karat),
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _rateController,
                enabled: !_saving,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                textInputAction: TextInputAction.done,
                decoration: const InputDecoration(
                  labelText: 'Rate per gram',
                  prefixText: '৳ ',
                  prefixIcon: Icon(Icons.payments_outlined),
                ),
                validator: (value) {
                  final rate = double.tryParse(value?.trim() ?? '');

                  if (rate == null) {
                    return 'Enter a valid rate.';
                  }

                  if (rate <= 0) {
                    return 'Rate must be greater than zero.';
                  }

                  return null;
                },
              ),
              const SizedBox(height: 16),
              InkWell(
                borderRadius: BorderRadius.circular(14),
                onTap: _saving ? null : _pickDate,
                child: InputDecorator(
                  decoration: const InputDecoration(
                    labelText: 'Effective date',
                    prefixIcon: Icon(Icons.event_outlined),
                    suffixIcon: Icon(Icons.calendar_month_outlined),
                  ),
                  child: Text(formatIsoDate(_effectiveDate)),
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: theme.colorScheme.error.withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: theme.colorScheme.error.withValues(alpha: 0.35),
                    ),
                  ),
                  child: Text(
                    _error!,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.error,
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 24),
              FilledButton(
                onPressed: _saving ? null : _save,
                child: _saving
                    ? const SizedBox(
                        height: 22,
                        width: 22,
                        child: CircularProgressIndicator(strokeWidth: 2.4),
                      )
                    : const Text('Save rate'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
