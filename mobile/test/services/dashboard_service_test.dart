import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:goldapp_mobile/core/api/api_exception.dart';
import 'package:goldapp_mobile/services/dashboard_service.dart';

import '../support/fake_http_client_adapter.dart';

const _dashboardJson = <String, dynamic>{
  'date': '2026-11-05',
  'today': {
    'invoice_count': 3,
    'sales_total': '12345.67',
    'sales_paid': '10000.00',
    'cash_balance': '5432.10',
    'cash_in': '2000.00',
    'cash_out': '500.00',
  },
  'stock': {'total_items': 12, 'total_weight': '140.000'},
  'pawns': {
    'active_count': 4,
    'outstanding_principal': '50000.00',
    'interest_due': '1500.00',
    'overdue_count': 1,
  },
  'receivables': '3000.00',
  'payables': '1000.00',
  'sales_trend': <Object?>[],
};

void main() {
  DashboardService buildService(FakeHttpClientAdapter adapter) {
    final dio = Dio(BaseOptions(baseUrl: 'https://shop.test/api/v1'))
      ..httpClientAdapter = adapter;

    return DashboardService(dio: dio);
  }

  test('parses the dashboard summary', () async {
    final adapter = FakeHttpClientAdapter(
      (_) async => FakeHttpClientAdapter.json({'data': _dashboardJson}),
    );
    final service = buildService(adapter);

    final summary = await service.fetch();

    expect(summary.date, DateTime(2026, 11, 5));
    expect(summary.today.invoiceCount, 3);
    expect(summary.today.salesTotal, 12345.67);
    expect(summary.today.cashBalance, 5432.10);
    expect(summary.today.cashIn, 2000);
    expect(summary.pawns.activeCount, 4);
    expect(summary.pawns.outstandingPrincipal, 50000);
    expect(summary.pawns.interestDue, 1500);
    expect(summary.pawns.overdueCount, 1);
    expect(adapter.requests.single.method, 'GET');
    expect(adapter.requests.single.path, '/reports/dashboard');
  });

  test('surfaces server failures as ApiException', () async {
    final service = buildService(
      FakeHttpClientAdapter(
        (_) async => FakeHttpClientAdapter.json({
          'message': 'Server error.',
        }, statusCode: 500),
      ),
    );

    await expectLater(service.fetch(), throwsA(isA<ApiException>()));
  });

  test('reports offline failures', () async {
    final service = buildService(
      FakeHttpClientAdapter((options) async {
        throw DioException.connectionError(
          reason: 'offline',
          requestOptions: options,
        );
      }),
    );

    try {
      await service.fetch();
      fail('expected an ApiException');
    } on ApiException catch (error) {
      expect(error.isOffline, isTrue);
    }
  });
}
