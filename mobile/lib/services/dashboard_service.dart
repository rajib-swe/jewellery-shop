import 'package:dio/dio.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_response.dart';
import '../core/api/endpoints.dart';
import '../models/dashboard_summary.dart';

class DashboardService {
  const DashboardService({required this.dio});

  final Dio dio;

  Future<DashboardSummary> fetch() async {
    try {
      final response = await ApiException.guard(
        () => dio.get<Object?>(Endpoints.dashboard),
      );

      return DashboardSummary.fromJson(dataMap(response.data));
    } on ApiException catch (error) {
      // If server returns 403 or permission error, provide a safe fallback summary
      // so the app screen continues to function without a hard block.
      final msg = error.message.toLowerCase();
      if (error.statusCode == 403 || msg.contains('permission') || msg.contains('unauthorized')) {
        return DashboardSummary(
          date: DateTime.now(),
          today: const DashboardToday(
            salesTotal: 0,
            salesPaid: 0,
            cashBalance: 0,
            cashIn: 0,
            cashOut: 0,
            invoiceCount: 0,
          ),
          pawns: const DashboardPawns(
            activeCount: 0,
            outstandingPrincipal: 0,
            interestDue: 0,
            overdueCount: 0,
          ),
        );
      }
      rethrow;
    }
  }
}
