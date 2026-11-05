import 'package:dio/dio.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_response.dart';
import '../core/api/endpoints.dart';
import '../models/dashboard_summary.dart';

class DashboardService {
  const DashboardService({required this.dio});

  final Dio dio;

  Future<DashboardSummary> fetch() async {
    final response = await ApiException.guard(
      () => dio.get<Object?>(Endpoints.dashboard),
    );

    return DashboardSummary.fromJson(dataMap(response.data));
  }
}
