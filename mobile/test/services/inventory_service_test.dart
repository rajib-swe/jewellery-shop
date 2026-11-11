import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:goldapp_mobile/services/inventory_service.dart';

import '../support/fake_http_client_adapter.dart';

const _itemJson = <String, dynamic>{
  'id': 4,
  'tag_no': 'TAG-001',
  'category': {'id': 2, 'name': 'Rings'},
  'name': 'Gold ring',
  'karat': 22,
  'gross_weight': '5.500',
  'stone_weight': '0.500',
  'net_weight': '5.000',
  'making_type': 'per_gram',
  'making_value': '450.00',
  'stone_price': '0.00',
  'status': 'in_stock',
  'image': null,
  'image_url': null,
  'barcode': 'BAR-001',
  'created_at': '2026-02-03T09:00:00+00:00',
  'updated_at': '2026-02-03T09:00:00+00:00',
};

void main() {
  InventoryService buildService(FakeHttpClientAdapter adapter) {
    final dio = Dio(BaseOptions(baseUrl: 'https://shop.test/api/v1'))
      ..httpClientAdapter = adapter;

    return InventoryService(dio: dio);
  }

  group('list', () {
    test('sends filters and reads the paginator meta', () async {
      final adapter = FakeHttpClientAdapter(
        (_) async => FakeHttpClientAdapter.json({
          'data': [_itemJson],
          'links': <String, dynamic>{},
          'meta': {'current_page': 1, 'last_page': 2, 'total': 12},
        }),
      );
      final service = buildService(adapter);

      final page = await service.list(
        search: 'ring',
        categoryId: 2,
        karat: 22,
        status: 'in_stock',
        perPage: 10,
      );

      expect(page.items, hasLength(1));
      expect(page.items.single.tagNo, 'TAG-001');
      expect(page.items.single.categoryName, 'Rings');
      expect(page.items.single.netWeight, 5);
      expect(page.currentPage, 1);
      expect(page.lastPage, 2);
      expect(page.total, 12);
      expect(adapter.requests.single.path, '/items');
      expect(adapter.requests.single.queryParameters, {
        'page': 1,
        'per_page': 10,
        'search': 'ring',
        'category_id': 2,
        'karat': 22,
        'status': 'in_stock',
      });
    });

    test('omits filters that were not supplied', () async {
      final adapter = FakeHttpClientAdapter(
        (_) async => FakeHttpClientAdapter.json({
          'data': <Object?>[],
          'meta': {'current_page': 1, 'last_page': 1, 'total': 0},
        }),
      );
      final service = buildService(adapter);

      await service.list(search: '   ');

      expect(adapter.requests.single.queryParameters, {
        'page': 1,
        'per_page': 20,
      });
    });
  });

  group('findByTag', () {
    test('matches the tag number case-insensitively', () async {
      final adapter = FakeHttpClientAdapter(
        (_) async => FakeHttpClientAdapter.json({
          'data': [_itemJson],
          'meta': {'current_page': 1, 'last_page': 1, 'total': 1},
        }),
      );
      final service = buildService(adapter);

      final item = await service.findByTag('tag-001');

      expect(item?.id, 4);
      expect(adapter.requests.single.queryParameters['search'], 'tag-001');
    });

    test('matches a barcode', () async {
      final service = buildService(
        FakeHttpClientAdapter(
          (_) async => FakeHttpClientAdapter.json({
            'data': [_itemJson],
            'meta': {'current_page': 1, 'last_page': 1, 'total': 1},
          }),
        ),
      );

      final item = await service.findByTag('BAR-001');

      expect(item?.tagNo, 'TAG-001');
    });

    test('returns null when nothing matches', () async {
      final service = buildService(
        FakeHttpClientAdapter(
          (_) async => FakeHttpClientAdapter.json({
            'data': [_itemJson],
            'meta': {'current_page': 1, 'last_page': 1, 'total': 1},
          }),
        ),
      );

      expect(await service.findByTag('TAG-999'), isNull);
    });

    test('skips the request for a blank scan', () async {
      final adapter = FakeHttpClientAdapter(
        (_) async => FakeHttpClientAdapter.json({'data': <Object?>[]}),
      );
      final service = buildService(adapter);

      expect(await service.findByTag('   '), isNull);
      expect(adapter.requests, isEmpty);
    });
  });

  test('parses the item detail', () async {
    final adapter = FakeHttpClientAdapter(
      (_) async => FakeHttpClientAdapter.json({'data': _itemJson}),
    );
    final service = buildService(adapter);

    final item = await service.show(4);

    expect(item.id, 4);
    expect(item.statusLabel, 'In stock');
    expect(item.makingTypeLabel, 'Per gram');
    expect(item.barcode, 'BAR-001');
    expect(item.createdAt, DateTime.utc(2026, 2, 3, 9));
    expect(adapter.requests.single.path, '/items/4');
  });

  test('parses categories', () async {
    final adapter = FakeHttpClientAdapter(
      (_) async => FakeHttpClientAdapter.json({
        'data': [
          {'id': 1, 'name': 'Necklaces', 'description': null},
          {'id': 2, 'name': 'Rings', 'description': 'Finger rings'},
        ],
      }),
    );
    final service = buildService(adapter);

    final categories = await service.categories();

    expect(categories, hasLength(2));
    expect(categories.last.name, 'Rings');
    expect(adapter.requests.single.path, '/categories');
  });

  test('parses the stock summary', () async {
    final adapter = FakeHttpClientAdapter(
      (_) async => FakeHttpClientAdapter.json({
        'data': {
          'total_items': 8,
          'total_net_weight': '42.500',
          'by_karat': [
            {'karat': 18, 'total_net_weight': '10.000', 'item_count': 2},
            {'karat': 22, 'total_net_weight': '32.500', 'item_count': 6},
          ],
        },
      }),
    );
    final service = buildService(adapter);

    final summary = await service.summary();

    expect(summary.totalItems, 8);
    expect(summary.totalNetWeight, 42.5);
    expect(summary.byKarat, hasLength(2));
    expect(summary.byKarat.last.karat, 22);
    expect(summary.byKarat.last.itemCount, 6);
    expect(adapter.requests.single.path, '/stock/summary');
  });

  test('creates an item with multipart fields and a photo', () async {
    final adapter = FakeHttpClientAdapter(
      (_) async => FakeHttpClientAdapter.json({'data': _itemJson}),
    );
    final service = buildService(adapter);

    final item = await service.create(
      categoryId: 2,
      name: 'Gold ring',
      karat: 22,
      grossWeight: 5.5,
      stoneWeight: 0.5,
      makingType: 'per_gram',
      makingValue: 450,
      barcode: 'BAR-001',
      imageBytes: Uint8List.fromList([1, 2, 3]),
    );

    final request = adapter.requests.single;
    final form = request.data as FormData;
    final fields = {for (final field in form.fields) field.key: field.value};

    expect(request.path, '/items');
    expect(request.method, 'POST');
    expect(fields, {
      'category_id': '2',
      'name': 'Gold ring',
      'karat': '22',
      'gross_weight': '5.500',
      'stone_weight': '0.500',
      'making_type': 'per_gram',
      'making_value': '450.00',
      'stone_price': '0.00',
      'barcode': 'BAR-001',
    });
    expect(form.files.single.key, 'image');
    expect(item.id, 4);
  });
}
