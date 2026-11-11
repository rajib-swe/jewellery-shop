import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api/api_exception.dart';
import '../core/theme/app_colors.dart';
import '../models/customer.dart';
import '../providers/customers_provider.dart';

Future<Customer?> showCustomerFormSheet(
  BuildContext context, {
  String? initialName,
  String? initialPhone,
}) {
  return showModalBottomSheet<Customer>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => _CustomerFormSheet(
      initialName: initialName,
      initialPhone: initialPhone,
    ),
  );
}

class _CustomerFormSheet extends ConsumerStatefulWidget {
  const _CustomerFormSheet({
    this.initialName,
    this.initialPhone,
  });

  final String? initialName;
  final String? initialPhone;

  @override
  ConsumerState<_CustomerFormSheet> createState() => _CustomerFormSheetState();
}

class _CustomerFormSheetState extends ConsumerState<_CustomerFormSheet> {
  final _formKey = GlobalKey<FormState>();

  late final TextEditingController _nameController;
  late final TextEditingController _phoneController;
  final _addressController = TextEditingController();
  final _nidController = TextEditingController();
  final _openingBalanceController = TextEditingController();
  final _notesController = TextEditingController();

  bool _submitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController(text: widget.initialName ?? '');
    _phoneController = TextEditingController(text: widget.initialPhone ?? '');
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _addressController.dispose();
    _nidController.dispose();
    _openingBalanceController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) {
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });

    try {
      final customer = await ref.read(customerServiceProvider).create(
        name: _nameController.text.trim(),
        phone: _phoneController.text.trim(),
        openingBalance:
            double.tryParse(_openingBalanceController.text.trim()) ?? 0,
        address: _addressController.text.trim(),
        nid: _nidController.text.trim(),
        notes: _notesController.text.trim(),
      );

      if (!mounted) {
        return;
      }

      ref.invalidate(customersControllerProvider);

      Navigator.of(context).pop(customer);
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _submitting = false;
        _error = error.fieldError('phone') ??
            error.fieldError('name') ??
            error.fieldError('opening_balance') ??
            error.message;
      });
    } catch (e) {
      if (!mounted) {
        return;
      }

      setState(() {
        _submitting = false;
        _error = 'গ্রাহক সংরক্ষণ করা যায়নি: $e';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Padding(
      padding: EdgeInsets.only(
        bottom: MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            mainAxisSize: MainAxisSize.min,
            children: [
              Center(
                child: Container(
                  height: 4,
                  width: 44,
                  decoration: BoxDecoration(
                    color: AppColors.outline,
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(
                      color: AppColors.primary.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(
                      Icons.person_add_alt_1,
                      color: AppColors.primary,
                      size: 22,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'নতুন গ্রাহক যোগ করুন',
                          style: theme.textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink,
                          ),
                        ),
                        Text(
                          'বিক্রয় ও বন্ধকের জন্য গ্রাহকের বিবরণ দিন',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: AppColors.inkMuted,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              if (_error != null) ...[
                const SizedBox(height: 14),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
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
              ],
              const SizedBox(height: 18),
              TextFormField(
                controller: _nameController,
                autofocus: true,
                textInputAction: TextInputAction.next,
                style: const TextStyle(color: AppColors.ink, fontWeight: FontWeight.w600),
                decoration: const InputDecoration(
                  labelText: 'গ্রাহকের পুরো নাম *',
                  hintText: 'যেমন: আনোয়ার হোসেন',
                  prefixIcon: Icon(Icons.person_outline),
                ),
                validator: (value) =>
                    (value == null || value.trim().isEmpty)
                        ? 'গ্রাহকের নাম আবশ্যক'
                        : null,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _phoneController,
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.next,
                style: const TextStyle(color: AppColors.ink, fontWeight: FontWeight.w600),
                decoration: const InputDecoration(
                  labelText: 'মোবাইল নম্বর *',
                  hintText: 'যেমন: 01711000000',
                  prefixIcon: Icon(Icons.phone_outlined),
                ),
                validator: (value) {
                  if (value == null || value.trim().isEmpty) {
                    return 'মোবাইল নম্বর আবশ্যক';
                  }
                  final clean = value.replaceAll(RegExp(r'\D'), '');
                  if (clean.length < 10) {
                    return 'সঠিক মোবাইল নম্বর দিন (কমপক্ষে ১০ ডিজিট)';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _addressController,
                textInputAction: TextInputAction.next,
                style: const TextStyle(color: AppColors.ink),
                decoration: const InputDecoration(
                  labelText: 'ঠিকানা (ঐচ্ছিক)',
                  hintText: 'যেমন: থানা রোড, সদর',
                  prefixIcon: Icon(Icons.location_on_outlined),
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _nidController,
                      textInputAction: TextInputAction.next,
                      style: const TextStyle(color: AppColors.ink),
                      decoration: const InputDecoration(
                        labelText: 'NID নম্বর (ঐচ্ছিক)',
                        hintText: 'জাতীয় পরিচয়পত্র',
                        prefixIcon: Icon(Icons.badge_outlined),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextFormField(
                      controller: _openingBalanceController,
                      keyboardType:
                          const TextInputType.numberWithOptions(decimal: true),
                      textInputAction: TextInputAction.next,
                      style: const TextStyle(color: AppColors.ink),
                      decoration: const InputDecoration(
                        labelText: 'পূর্বের বাকি (ঐচ্ছিক)',
                        hintText: '০.০০',
                        prefixIcon: Icon(Icons.payments_outlined),
                        prefixText: '৳ ',
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _notesController,
                maxLines: 2,
                style: const TextStyle(color: AppColors.ink),
                decoration: const InputDecoration(
                  labelText: 'মন্তব্য বা পরিচিতি (ঐচ্ছিক)',
                  hintText: 'গ্রাহক সম্পর্কে যেকোনো নোট',
                  prefixIcon: Icon(Icons.edit_note),
                ),
              ),
              const SizedBox(height: 22),
              FilledButton.icon(
                onPressed: _submitting ? null : _submit,
                icon: _submitting
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.check),
                label: Text(
                  _submitting ? 'সংরক্ষণ হচ্ছে…' : 'গ্রাহক সংরক্ষণ করুন',
                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                ),
                style: FilledButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

