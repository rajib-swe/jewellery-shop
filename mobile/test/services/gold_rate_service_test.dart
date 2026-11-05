import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:goldapp_mobile/core/api/api_exception.dart';
import 'package:goldapp_mobile/services/gold_rate_service.dart';

import '../support/fake_http_client_adapter.dart';

const _latestJson = <Object?>[
  {
    'id': 1,
    'karat': 22,
    'rate_per_gram': '6500.00',
    'effective_date': '2026-11-05',
    'created_by': {'id': 1, 'name': 'Admin'},
  },
  {
    'id': 2,
    'karat': 21,
    'rate_per_gram': '6200.50',
    'effective_date': '2026-11-04',
    'created_by': {'id': 1, 'name': 'Admin'},
  },
];

void main() {
  GoldRateService buildService(FakeHttpClientAdapter adapter) {
    final dio = Dio(BaseOptions(baseUrl: 'https://shop.test/api/v1'))
      ..httpClientAdapter = adapter;

    return GoldRateService(dio: dio);
  }

  group('latest', () {
    test('parses every karat returned by the API', () async {
      final adapter = FakeHttpClientAdapter(
        (_) async => FakeHttpClientAdapter.json({'data': _latestJson}),
      );
      final service = buildService(adapter);

      final rates = await service.latest();

      expect(rates, hasLength(2));
      expect(rates.first.karat, 22);
      expect(rates.first.ratePerGram, 6500);
      expect(rates.first.effectiveDate, DateTime(2026, 11, 5));
      expect(rates.first.createdByName, 'Admin');
      expect(adapter.requests.single.path, '/gold-rates/latest');
    });

    test('surfaces failures as ApiException', () async {
      final service = buildService(
        FakeHttpClientAdapter(
          (_) async => FakeHttpClientAdapter.json({
            'message': 'Forbidden.',
          }, statusCode: 403),
        ),
      );

      try {
        await service.latest();
        fail('expected an ApiException');
      } on ApiException catch (error) {
        expect(error.isForbidden, isTrue);
      }
    });
  });

  group('create', () {
    test('posts the rate as a two decimal string', () async {
      final adapter = FakeHttpClientAdapter(
        (_) async => FakeHttpClientAdapter.json({
          'data': {
            'id': 9,
            'karat': 22,
            'rate_per_gram': '6500.00',
            'effective_date': '2026-11-05',
            'created_by': {'id': 1, 'name': 'Admin'},
          },
          'meta': {'message': 'Gold rate saved.'},
        }),
      );
      final service = buildService(adapter);

      final rate = await service.create(
        karat: 22,
        ratePerGram: 6500,
        effectiveDate: DateTime(2026, 11, 5),
      );

      expect(rate.id, 9);
      expect(adapter.requests.single.method, 'POST');
      expect(adapter.requests.single.path, '/gold-rates');
      expect(adapter.requests.single.data, {
        'karat': 22,
        'rate_per_gram': '6500.00',
        'effective_date': '2026-11-05',
      });
    });

    test('exposes the field validation message', () async {
      final service = buildService(
        FakeHttpClientAdapter(
          (_) async => FakeHttpClientAdapter.json({
            'message': 'The given data was invalid.',
            'errors': {
              'rate_per_gram': ['The rate per gram must be greater than 0.'],
            },
          }, statusCode: 422),
        ),
      );

      try {
        await service.create(
          karat: 22,
          ratePerGram: 0,
          effectiveDate: DateTime(2026, 11, 5),
        );
        fail('expected an ApiException');
      } on ApiException catch (error) {
        expect(error.isValidation, isTrue);
        expect(
          error.fieldError('rate_per_gram'),
          'The rate per gram must be greater than 0.',
        );
      }
    });
  });
}
