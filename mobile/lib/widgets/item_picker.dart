import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../core/api/api_exception.dart';
import '../models/item.dart';
import '../providers/inventory_provider.dart';
import 'state_handler.dart';

Future<Item?> showItemPicker(BuildContext context) {
  return showModalBottomSheet<Item>(
    context: context,
    isScrollControlled: true,
    useSafeArea: true,
    builder: (context) => const _ItemPickerSheet(),
  );
}

class _ItemPickerSheet extends ConsumerStatefulWidget {
  const _ItemPickerSheet();

  @override
  ConsumerState<_ItemPickerSheet> createState() => _ItemPickerSheetState();
}

class _ItemPickerSheetState extends ConsumerState<_ItemPickerSheet> {
  final _searchController = TextEditingController();

  Timer? _debounce;
  List<Item> _items = const [];
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
          .read(inventoryServiceProvider)
          .list(search: search, status: 'in_stock', perPage: 20);

      if (!mounted || generation != _generation) {
        return;
      }

      setState(() {
        _items = page.items;
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

  Future<void> _scan() async {
    final code = await showBarcodeScanner(context);

    if (code == null || !mounted) {
      return;
    }

    _searchController.text = code;

    final found = await ref
        .read(inventoryServiceProvider)
        .findByTag(code);

    if (!mounted) {
      return;
    }

    if (found == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$code নম্বরে কোনো পণ্য পাওয়া যায়নি।')),
      );
      await _load(code);
      return;
    }

    Navigator.of(context).pop(found);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return FractionallySizedBox(
      heightFactor: 0.85,
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
                color: theme.colorScheme.outline,
                borderRadius: BorderRadius.circular(999),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 4),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'স্টক পণ্য যোগ করুন',
                      style: theme.textTheme.titleLarge,
                    ),
                  ),
                  IconButton(
                    tooltip: 'বারকোড স্ক্যান',
                    onPressed: _scan,
                    icon: const Icon(Icons.qr_code_scanner),
                  ),
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
                decoration: InputDecoration(
                  hintText: 'ট্যাগ, বারকোড বা নাম খুঁজুন',
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
      return StateHandlerView.loading(message: 'পণ্য খোঁজা হচ্ছে…');
    }

    if (_error != null) {
      return StateHandlerView.error(
        message: _error!,
        onRetry: () => _load(_searchController.text),
      );
    }

    if (_items.isEmpty) {
      return StateHandlerView.empty(
        icon: Icons.inventory_2_outlined,
        message: _searchController.text.trim().isEmpty
            ? 'স্টকে কোনো পণ্য নেই'
            : 'এই তথ্যে কোনো পণ্য মেলেনি',
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.only(bottom: 24),
      itemCount: _items.length,
      separatorBuilder: (context, index) => const Divider(height: 1),
      itemBuilder: (context, index) {
        final item = _items[index];

        return ListTile(
          title: Text(item.name),
          subtitle: Text(
            '${item.tagNo} · ${item.karat}K · ${item.netWeight.toStringAsFixed(3)} g',
          ),
          trailing: Text(
            item.categoryName,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: Theme.of(context).colorScheme.outline,
            ),
          ),
          onTap: () => Navigator.of(context).pop(item),
        );
      },
    );
  }
}

Future<String?> showBarcodeScanner(BuildContext context) {
  return Navigator.of(context).push<String>(
    PageRouteBuilder<String>(
      opaque: false,
      pageBuilder: (context, animation, secondaryAnimation) =>
          const _BarcodeScannerPage(),
    ),
  );
}

class _BarcodeScannerPage extends StatelessWidget {
  const _BarcodeScannerPage();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: const Text('ট্যাগ বারকোড স্ক্যান'),
      ),
      body: Stack(
        children: [
          MobileScanner(
            onDetect: (capture) {
              final code = capture.barcodes
                  .where((barcode) => barcode.rawValue != null)
                  .map((barcode) => barcode.rawValue!)
                  .firstOrNull;

              if (code != null && Navigator.of(context).canPop()) {
                Navigator.of(context).pop(code);
              }
            },
          ),
          SafeArea(
            child: Align(
              alignment: Alignment.bottomCenter,
              child: Padding(
                padding: const EdgeInsets.only(bottom: 24),
                child: FilledButton.tonal(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('বাতিল'),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
