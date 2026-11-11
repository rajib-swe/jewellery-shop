import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api/api_exception.dart';
import '../core/theme/app_colors.dart';
import '../models/customer.dart';
import '../providers/customers_provider.dart';
import 'customer_form_sheet.dart';
import 'state_handler.dart';

Future<Customer?> showCustomerPicker(BuildContext context) {
  return showModalBottomSheet<Customer>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => const _CustomerPickerSheet(),
  );
}

class _CustomerPickerSheet extends ConsumerStatefulWidget {
  const _CustomerPickerSheet();

  @override
  ConsumerState<_CustomerPickerSheet> createState() =>
      _CustomerPickerSheetState();
}

class _CustomerPickerSheetState extends ConsumerState<_CustomerPickerSheet> {
  final _searchController = TextEditingController();

  Timer? _debounce;
  List<Customer> _customers = const [];
  bool _loading = true;
  String? _error;
  int _generation = 0;

  @override
  void initState() {
    super.initState();
    _load('');
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), () => _load(value));
  }

  Future<void> _load(String search) async {
    final generation = ++_generation;

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final page = await ref
          .read(customerServiceProvider)
          .list(search: search, perPage: 20);

      if (!mounted || generation != _generation) {
        return;
      }

      setState(() {
        _customers = page.items;
        _loading = false;
      });
    } on ApiException catch (error) {
      if (!mounted || generation != _generation) {
        return;
      }

      setState(() {
        _error = error.message;
        _loading = false;
      });
    }
  }

  Future<void> _openAddCustomer() async {
    final query = _searchController.text.trim();
    final isPhone = RegExp(r'^\d+$').hasMatch(query);
    final newCust = await showCustomerFormSheet(
      context,
      initialName: isPhone ? null : (query.isNotEmpty ? query : null),
      initialPhone: isPhone ? query : null,
    );

    if (newCust != null && mounted) {
      Navigator.of(context).pop(newCust);
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return FractionallySizedBox(
      heightFactor: 0.88,
      child: Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: Column(
          children: [
            const SizedBox(height: 12),
            Container(
              height: 4,
              width: 44,
              decoration: BoxDecoration(
                color: AppColors.outline,
                borderRadius: BorderRadius.circular(999),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 14, 16, 4),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'গ্রাহক নির্বাচন করুন',
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: AppColors.ink,
                      ),
                    ),
                  ),
                  FilledButton.tonalIcon(
                    onPressed: _openAddCustomer,
                    icon: const Icon(Icons.person_add, size: 18),
                    label: const Text('নতুন গ্রাহক'),
                    style: FilledButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                      minimumSize: const Size(0, 36),
                    ),
                  ),
                  const SizedBox(width: 4),
                  IconButton(
                    tooltip: 'বন্ধ',
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
              child: TextField(
                controller: _searchController,
                autofocus: true,
                textInputAction: TextInputAction.search,
                onChanged: _onSearchChanged,
                style: const TextStyle(color: AppColors.ink, fontWeight: FontWeight.w500),
                decoration: InputDecoration(
                  hintText: 'নাম, মোবাইল, এনআইডি বা কোড খুঁজুন…',
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: _searchController.text.isEmpty
                      ? null
                      : IconButton(
                          tooltip: 'মুছুন',
                          onPressed: () {
                            _searchController.clear();
                            _onSearchChanged('');
                          },
                          icon: const Icon(Icons.close),
                        ),
                ),
              ),
            ),
            Expanded(child: _buildBody()),
          ],
        ),
      ),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return StateHandlerView.loading(message: 'গ্রাহক খোঁজা হচ্ছে…');
    }

    if (_error != null) {
      return StateHandlerView.error(
        message: _error!,
        onRetry: () => _load(_searchController.text),
      );
    }

    if (_customers.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.person_search, size: 48, color: AppColors.inkMuted),
              const SizedBox(height: 12),
              Text(
                _searchController.text.trim().isEmpty
                    ? 'এখনো কোনো গ্রাহক নেই'
                    : 'এই তথ্যে কোনো গ্রাহক পাওয়া যায়নি',
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: AppColors.ink,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: _openAddCustomer,
                icon: const Icon(Icons.person_add),
                label: const Text('নতুন গ্রাহক হিসেবে যুক্ত করুন'),
              ),
            ],
          ),
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.only(bottom: 24),
      itemCount: _customers.length,
      separatorBuilder: (context, index) => const Divider(height: 1),
      itemBuilder: (context, index) {
        final customer = _customers[index];

        return ListTile(
          leading: CircleAvatar(
            backgroundColor: AppColors.primary.withValues(alpha: 0.12),
            foregroundColor: AppColors.primary,
            child: Text(
              customer.initials,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
          title: Text(
            customer.name,
            style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.ink),
          ),
          subtitle: Text(
            customer.phone.isEmpty ? customer.code : '${customer.phone} · ${customer.code}',
            style: const TextStyle(color: AppColors.inkMuted),
          ),
          onTap: () => Navigator.of(context).pop(customer),
        );
      },
    );
  }
}
