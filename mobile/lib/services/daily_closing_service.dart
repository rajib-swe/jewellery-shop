import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_response.dart';
import '../core/api/endpoints.dart';
import '../core/utils/parsing.dart';
import '../models/daily_closing.dart';
import '../providers/auth_provider.dart';

final dailyClosingServiceProvider = Provider<DailyClosingService>((ref) {
  return DailyClosingService(dio: ref.watch(dioProvider));
});

class DailyClosingService {
  const DailyClosingService({required this.dio});

  final Dio dio;

  Future<CashBookSummary> fetchSummary(String date) async {
    final response = await ApiException.guard(
      () => dio.get<Object?>(
        Endpoints.dailyClosingShow,
        queryParameters: {'date': date},
      ),
    );

    return CashBookSummary.fromJson(dataMap(response.data));
  }

  Future<void> closeDay(String date, {String? note}) async {
    await ApiException.guard(
      () => dio.post<Object?>(
        Endpoints.dailyClosings,
        data: {
          'date': date,
          if (note != null && note.isNotEmpty) 'note': note,
        },
      ),
    );
  }

  Future<void> reopenDay(int id, {String? reason}) async {
    await ApiException.guard(
      () => dio.post<Object?>(
        Endpoints.dailyClosingReopen(id),
        data: {
          if (reason != null && reason.isNotEmpty) 'reason': reason,
        },
      ),
    );
  }

  Future<List<DailyClosingRecord>> listClosings({int page = 1}) async {
    try {
      final response = await ApiException.guard(
        () => dio.get<Object?>(
          Endpoints.dailyClosings,
          queryParameters: {'page': page},
        ),
      );

      return dataList(response.data)
          .map((item) => DailyClosingRecord.fromJson(asJsonMap(item)))
          .toList(growable: false);
    } catch (_) {
      return const [];
    }
  }

  Future<String> runMigration() async {
    final response = await ApiException.guard(
      () => dio.post<Object?>(Endpoints.migrateDatabase),
    );

    final map = asJsonMap(response.data);
    return (map['message'] as String?) ?? 'মাইগ্রেশন সফল হয়েছে';
  }
}

