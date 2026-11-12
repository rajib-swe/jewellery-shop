import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:goldapp_mobile/core/api/endpoints.dart';
import 'package:goldapp_mobile/services/daily_closing_service.dart';

void main() {
  late Dio dio;
  late DailyClosingService service;

  setUp(() {
    dio = Dio();
    service = DailyClosingService(dio: dio);
  });

  test('parses summary response', () async {
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          if (options.path == Endpoints.dailyClosingShow) {
            return handler.resolve(
              Response(
                requestOptions: options,
                statusCode: 200,
                data: {
                  'data': {
                    'date': '2026-11-12',
                    'opening_balance': '15000.00',
                    'total_in': '50000.00',
                    'total_out': '20000.00',
                    'closing_balance': '45000.00',
                    'transaction_count': 5,
                    'locked': false,
                    'by_method': [
                      {
                        'method': 'cash',
                        'total_in': '40000.00',
                        'total_out': '15000.00',
                        'net': '25000.00',
                      },
                    ],
                  },
                },
              ),
            );
          }
          return handler.next(options);
        },
      ),
    );

    final summary = await service.fetchSummary('2026-11-12');
    expect(summary.date, '2026-11-12');
    expect(summary.openingBalance, 15000.00);
    expect(summary.totalIn, 50000.00);
    expect(summary.totalOut, 20000.00);
    expect(summary.closingBalance, 45000.00);
    expect(summary.transactionCount, 5);
    expect(summary.isLocked, false);
    expect(summary.byMethod.length, 1);
    expect(summary.byMethod.first.method, 'cash');
  });

  test('posts closeDay data', () async {
    RequestOptions? captured;

    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          if (options.path == Endpoints.dailyClosings && options.method == 'POST') {
            captured = options;
            return handler.resolve(
              Response(
                requestOptions: options,
                statusCode: 201,
                data: {'data': {'id': 1}, 'meta': {'message': 'Day closed'}},
              ),
            );
          }
          return handler.next(options);
        },
      ),
    );

    await service.closeDay('2026-11-12', note: 'All clear');
    expect(captured, isNotNull);
    final map = captured!.data as Map<String, dynamic>;
    expect(map['date'], '2026-11-12');
    expect(map['note'], 'All clear');
  });
}

