import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:goldapp_mobile/models/pawn.dart';
import 'package:goldapp_mobile/models/sale.dart' show PartyRef;
import 'package:goldapp_mobile/services/pawn_service.dart';

import '../support/fake_http_client_adapter.dart';

const _pawnJson = <String, dynamic>{
  'id': 5,
  'pawn_no': 'PN-000005',
  'date': '2026-10-05',
  'due_date': '2027-01-05',
  'status': 'active',
  'principal': '20000.00',
  'interest_rate': '2.00',
  'interest_type': 'per_month',
  'notes': 'gold bala',
  'is_overdue': false,
  'customer': {
    'id': 4,
    'code': 'CUS-4',
    'name': 'Rezaul Karim',
    'phone': '01700000000',
  },
  'user': {'id': 1, 'name': 'admin'},
  'items': [
    {
      'id': 9,
      'description': '22K bala',
      'karat': 22,
      'gross_weight': '15.500',
      'stone_weight': '1.500',
      'net_weight': '14.000',
      'estimated_value': '900000.00',
      'photo_url': null,
    },
  ],
  'payments': [
    {
      'id': 33,
      'type': 'interest',
      'amount': '400.00',
      'date': '2026-11-01',
      'method': 'cash',
      'reference': 'RCPT-9',
      'note': null,
      'user': {'id': 1, 'name': 'admin'},
    },
  ],
  'summary': {
    'outstanding_principal': '20000.00',
    'principal_paid': '0.00',
    'interest_rate': '2.00',
    'interest_accrued': '800.00',
    'interest_paid': '400.00',
    'interest_due': '400.00',
    'total_paid': '400.00',
    'total_payable': '20400.00',
    'as_of': '2026-11-05',
    'periods': [
      {
        'from': '2026-10-05',
        'to': '2026-11-05',
        'days': 31,
        'months': '1.0333',
        'principal': '20000.00',
        'interest': '800.00',
      },
    ],
  },
  'close_reason': null,
};

void main() {
  PawnService buildService(FakeHttpClientAdapter adapter) {
    final dio = Dio(BaseOptions(baseUrl: 'https://shop.test/api/v1'))
      ..httpClientAdapter = adapter;

    return PawnService(dio: dio);
  }

  group('list', () {
    test('sends search, status and pagination and reads paginator meta', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async =>
            FakeHttpClientAdapter.json({'data': [_pawnJson], 'meta': {
              'current_page': 1,
              'last_page': 3,
              'total': 57,
            }}),
      );

      final page = await buildService(
        adapter,
      ).list(search: 'PN', status: 'active');

      expect(page.items.single.pawnNo, 'PN-000005');
      expect(page.total, 57);

      final query = adapter.requests.single.queryParameters;
      expect(query['search'], 'PN');
      expect(query['status'], 'active');
    });

    test('overdue is sent as a boolean flag instead of a status', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async => FakeHttpClientAdapter.json({'data': []}),
      );

      await buildService(adapter).list(overdue: true);

      final query = adapter.requests.single.queryParameters;
      expect(query['overdue'], true);
      expect(query.containsKey('status'), isFalse);
    });

    test('omits the overdue flag when it is not requested', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async => FakeHttpClientAdapter.json({'data': []}),
      );

      await buildService(adapter).list();

      final query = adapter.requests.single.queryParameters;
      expect(query.containsKey('overdue'), isFalse);
    });
  });

  group('show', () {
    test('parses the server-computed summary and periods', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async => FakeHttpClientAdapter.json(_pawnJson),
      );

      final pawn = await buildService(adapter).show(5);

      expect(pawn.pawnNo, 'PN-000005');
      expect(pawn.customer?.name, 'Rezaul Karim');
      expect(pawn.items.single.netWeight, 14);
      expect(pawn.summary.totalPayable, 20400);
      expect(pawn.summary.interestDue, 400);
      expect(pawn.summary.periods.single.interest, 800);
      expect(pawn.payments.single.type, 'interest');
      expect((pawn.customer as PartyRef).phone, '01700000000');
    });
  });

  group('create', () {
    test('posts customer, principal and item weights in grams', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async => FakeHttpClientAdapter.json(_pawnJson),
      );

      final pawn = await buildService(adapter).create(
        customerId: 4,
        principal: 20000,
        items: [
          const PawnItemInput(
            description: '22K bala',
            karat: 22,
            grossWeight: 15.5,
            stoneWeight: 1.5,
            estimatedValue: 900000,
          ),
        ],
        interestRate: 2,
        dueDate: '2027-01-05',
        notes: 'gold bala',
      );

      expect(pawn.id, 5);

      final body = adapter.requests.single.data as Map<String, dynamic>;
      expect((body['items'] as List).length, 1);
      expect(((body['items'] as List).single as Map)['gross_weight'], '15.500');
      expect(body['principal'], '20000.00');
      expect(body['interest_rate'], '2.00');
      expect(body['due_date'], '2027-01-05');
    });
  });

  group('payment actions', () {
    test('addPayment posts amount with an optional method and note', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async => FakeHttpClientAdapter.json(_pawnJson),
      );

      await buildService(
        adapter,
      ).addPayment(5, amount: 400, method: 'cash', note: 'interest');

      final request = adapter.requests.single;
      expect(request.uri.path, '/api/v1/pawns/5/payments');
      expect((request.data as Map)['amount'], '400.00');
      expect((request.data as Map)['method'], 'cash');
    });

    test('redeem sends optional method and note only', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async => FakeHttpClientAdapter.json(_pawnJson),
      );

      await buildService(adapter).redeem(5, method: 'cash');

      final request = adapter.requests.single;
      expect(request.uri.path, '/api/v1/pawns/5/redeem');
      expect((request.data as Map)['method'], 'cash');
      expect((request.data as Map).containsKey('amount'), isFalse);
    });

    test('renew sends term_days bounded by the API maximum', () async {
      final adapter = FakeHttpClientAdapter(
        (options) async => FakeHttpClientAdapter.json(_pawnJson),
      );

      await buildService(adapter).renew(5, termDays: 90, note: 'renewal');

      final request = adapter.requests.single;
      expect(request.uri.path, '/api/v1/pawns/5/renew');
      expect((request.data as Map)['term_days'], 90);
      expect((request.data as Map)['note'], 'renewal');
    });
  });
}
