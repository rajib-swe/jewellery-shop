import 'package:dio/dio.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_response.dart';
import '../core/api/endpoints.dart';
import '../core/utils/format.dart';
import '../core/utils/parsing.dart';
import '../models/gold_rate.dart';

class GoldRateService {
  const GoldRateService({required this.dio});

  final Dio dio;

  Future<List<GoldRate>> latest() async {
    final response = await ApiException.guard(
      () => dio.get<Object?>(Endpoints.latestGoldRates),
    );

    return dataList(response.data)
        .map((rate) => GoldRate.fromJson(asJsonMap(rate)))
        .toList(growable: false);
  }

  Future<GoldRate> create({
    required int karat,
    required double ratePerGram,
    required DateTime effectiveDate,
  }) async {
    final response = await ApiException.guard(
      () => dio.post<Object?>(
        Endpoints.goldRates,
        data: {
          'karat': karat,
          'rate_per_gram': ratePerGram.toStringAsFixed(2),
          'effective_date': formatIsoDate(effectiveDate),
        },
      ),
    );

    return GoldRate.fromJson(dataMap(response.data));
  }
}
