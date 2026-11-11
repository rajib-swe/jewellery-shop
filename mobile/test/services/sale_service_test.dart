import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:goldapp_mobile/models/sale.dart';
import 'package:goldapp_mobile/services/sale_service.dart';

import '../support/fake_http_client_adapter.dart';

const _saleJson = <String, dynamic>{
  'id': 3,
  'invoice_no': 'INV-000003',
  'date': '2026-11-05',
  'status': 'completed',
  'customer': {
    'id': 4,
    'code': 'CUS-4',
    'name': 'Rezaul Karim',
    'phone': '01700000000',
  },
  'user': {'id': 1, 'name': 'admin'},
  'subtotal': '12000.00',
  'discount': '200.00',
  'vat': '0.00',
  'exchange_amount': '1500.00',
  'total': '10300.00',
  'paid': '10300.00',
  'due': '0.00',
  'notes': 'wrapping request',
  'voided_at': null,
  'void_reason': null,
  'items': [
    {
      'id': 11,
      'item_id': 42,
      'tag_no': 'T-100',
      'name': 'Chain',
      'karat': 22,
      'weight': '11.664',
      'rate': '6800.00',
      'gold_value': '79315.20',
      'making': '500.00',
      'stone_price': '0.00',
      'line_total': '13300.00',
    },
  ],
  'payments': [
    {
      'id': 21,
      'method': 'bkash',
      'amount': '600.00',
      'reference': 'TRX-1',
      'user': {'id': 1, 'name': 'admin'},
      'created_at': '2026-11-05T10:00:00+00:00',
    },
  ],
  'exchanges': [
    {
      'id': 31,
      'description': 'old ring',
      'karat': 22,
      'weight': '5.000',
      'rate': '6800.00',
      'amount': '34000.00',
    },
  ],
};

void main() {
  SaleService buildService(FakeHttpClientAdapter adapter) {
    final dio = Dio(BaseOptions(baseUrl: 'https://shop.test/api/v1'))
      ..httpClientAdapter = adapter;

    return SaleService(dio: dio);
  }

  group('list', () {
    test('sends search, status and pagination and reads paginator meta', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async =>
            FakeHttpClientAdapter.json({'data': [_saleJson], 'meta': {
              'current_page': 2,
              'last_page': 5,
              'total': 96,
            }}),
      );

      final page = await buildService(
        adapter,
      ).list(search: 'INV', status: 'completed', page: 2);

      expect(page.items.single.id, 3);
      expect(page.currentPage, 2);
      expect(page.lastPage, 5);
      expect(page.total, 96);

      final query = adapter.requests.single.queryParameters;
      expect(query['search'], 'INV');
      expect(query['status'], 'completed');
      expect(query['page'], 2);
      expect(query['per_page'], 20);
    });

    test('omits the status filter when it is blank', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async => FakeHttpClientAdapter.json({'data': []}),
      );

      await buildService(adapter).list();

      expect(
        adapter.requests.single.queryParameters.containsKey('status'),
        isFalse,
      );
    });
  });

  group('show', () {
    test('parses items, payments and exchanges', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async => FakeHttpClientAdapter.json(_saleJson),
      );

      final sale = await buildService(adapter).show(3);

      expect(sale.invoiceNo, 'INV-000003');
      expect(sale.total, 10300);
      expect(sale.customer?.name, 'Rezaul Karim');
      expect(sale.items.single.tagNo, 'T-100');
      expect(sale.payments.single.method, 'bkash');
      expect(sale.exchanges.single.amount, 34000);
    });
  });

  group('create', () {
    test('posts catalogue and manual lines with gram weights', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async => FakeHttpClientAdapter.json(_saleJson),
      );

      final sale = await buildService(adapter).create(
        customerId: 4,
        items: [
          const SaleLineInput(itemId: 42),
          const SaleLineInput(
            name: 'Custom bala',
            karat: 21,
            weight: 5.5,
            makingType: 'per_gram',
            makingValue: 300,
            stonePrice: 120,
          ),
        ],
        payments: [
          const SalePaymentInput(method: 'bkash', amount: 2500),
        ],
        exchanges: [
          const SaleExchangeInput(description: 'old ring', karat: 22, weight: 5),
        ],
        discount: 200,
        notes: 'wrapping request',
      );

      expect(sale.id, 3);

      final body = adapter.requests.single.data as Map<String, dynamic>;
      final items = body['items'] as List<dynamic>;

      expect((items.first as Map)['item_id'], 42);
      expect((items.last as Map)['weight'], '5.500');
      expect((items.last as Map)['making_type'], 'per_gram');
      expect(
        ((body['payments'] as List).single as Map)['method'],
        'bkash',
      );
      expect(
        ((body['exchanges'] as List).single as Map)['weight'],
        '5.000',
      );
      expect(body['discount'], '200.00');
      expect(body['notes'], 'wrapping request');
    });

    test('omits payments and exchanges when empty', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async => FakeHttpClientAdapter.json(_saleJson),
      );

      await buildService(
        adapter,
      ).create(items: [const SaleLineInput(itemId: 42)]);

      final body = adapter.requests.single.data as Map<String, dynamic>;

      expect(body.containsKey('payments'), isFalse);
      expect(body.containsKey('exchanges'), isFalse);
      expect(body.containsKey('discount'), isFalse);
    });
  });

  group('payments and void', () {
    test('addPayment posts method, amount and optional reference', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async => FakeHttpClientAdapter.json(_saleJson),
      );

      await buildService(
        adapter,
      ).addPayment(3, method: 'cash', amount: 400);

      final request = adapter.requests.single;
      expect(request.uri.path, '/api/v1/sales/3/payments');
      expect((request.data as Map)['method'], 'cash');
      expect((request.data as Map)['amount'], '400.00');
      expect((request.data as Map).containsKey('reference'), isFalse);
    });

    test('posts the reason to the void endpoint when given', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async => FakeHttpClientAdapter.json(_saleJson),
      );

      await buildService(adapter).voidSale(3, reason: 'wrong entry');

      final request = adapter.requests.single;
      expect(request.uri.path, '/api/v1/sales/3/void');
      expect((request.data as Map)['reason'], 'wrong entry');
    });
  });
}
