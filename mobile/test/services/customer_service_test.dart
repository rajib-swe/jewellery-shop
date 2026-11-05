import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:goldapp_mobile/core/api/api_exception.dart';
import 'package:goldapp_mobile/services/customer_service.dart';

import '../support/fake_http_client_adapter.dart';

const _customerJson = <String, dynamic>{
  'id': 7,
  'code': 'CUS-ABC',
  'name': 'Rezaul Karim',
  'phone': '01700000000',
  'nid': '1234567890',
  'address': 'Dhaka',
  'photo': null,
  'photo_url': null,
  'opening_balance': '250.00',
  'notes': 'VIP',
  'created_at': '2026-01-02T10:00:00+00:00',
  'updated_at': '2026-01-02T10:00:00+00:00',
};

const _historyJson = <String, dynamic>{
  'sales': [
    {
      'id': 1,
      'invoice_no': 'INV-1',
      'date': '2026-11-01',
      'status': 'completed',
      'total': '1000.00',
      'paid': '600.00',
      'due': '400.00',
    },
  ],
  'pawns': [
    {
      'id': 2,
      'pawn_no': 'PN-1',
      'date': '2026-10-01',
      'due_date': '2026-11-01',
      'status': 'active',
      'principal': '5000.00',
      'outstanding_principal': '5000.00',
      'interest_due': '250.00',
      'total_payable': '5250.00',
    },
  ],
  'payments': [
    {
      'id': 3,
      'invoice_no': 'INV-1',
      'date': '2026-11-01',
      'method': 'cash',
      'amount': '600.00',
      'reference': 'RCPT-1',
    },
  ],
  'due_balance': '5650.00',
};

void main() {
  CustomerService buildService(FakeHttpClientAdapter adapter) {
    final dio = Dio(BaseOptions(baseUrl: 'https://shop.test/api/v1'))
      ..httpClientAdapter = adapter;

    return CustomerService(dio: dio);
  }

  group('list', () {
    test('sends search and pagination and reads the paginator meta', () async {
      final adapter = FakeHttpClientAdapter(
        (_) async => FakeHttpClientAdapter.json({
          'data': [_customerJson],
          'links': <String, dynamic>{},
          'meta': {'current_page': 2, 'last_page': 3, 'total': 25},
        }),
      );
      final service = buildService(adapter);

      final page = await service.list(search: 'karim', page: 2, perPage: 10);

      expect(page.items, hasLength(1));
      expect(page.items.single.name, 'Rezaul Karim');
      expect(page.items.single.openingBalance, 250);
      expect(page.currentPage, 2);
      expect(page.lastPage, 3);
      expect(page.total, 25);
      expect(adapter.requests.single.path, '/customers');
      expect(adapter.requests.single.queryParameters, {
        'page': 2,
        'per_page': 10,
        'search': 'karim',
      });
    });

    test('omits an empty search term', () async {
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

  test('parses a customer profile', () async {
    final service = buildService(
      FakeHttpClientAdapter(
        (_) async => FakeHttpClientAdapter.json({'data': _customerJson}),
      ),
    );

    final customer = await service.show(7);

    expect(customer.id, 7);
    expect(customer.code, 'CUS-ABC');
    expect(customer.nid, '1234567890');
    expect(customer.createdAt, DateTime.utc(2026, 1, 2, 10));
  });

  test('parses the customer history tabs', () async {
    final adapter = FakeHttpClientAdapter(
      (_) async => FakeHttpClientAdapter.json({'data': _historyJson}),
    );
    final service = buildService(adapter);

    final history = await service.history(7);

    expect(history.dueBalance, 5650);
    expect(history.sales.single.invoiceNo, 'INV-1');
    expect(history.sales.single.due, 400);
    expect(history.pawns.single.pawnNo, 'PN-1');
    expect(history.pawns.single.totalPayable, 5250);
    expect(history.payments.single.method, 'cash');
    expect(history.payments.single.amount, 600);
    expect(adapter.requests.single.path, '/customers/7/history');
  });

  test('surfaces a missing customer', () async {
    final service = buildService(
      FakeHttpClientAdapter(
        (_) async => FakeHttpClientAdapter.json({
          'message': 'Not found.',
        }, statusCode: 404),
      ),
    );

    try {
      await service.show(99);
      fail('expected an ApiException');
    } on ApiException catch (error) {
      expect(error.statusCode, 404);
    }
  });
}
