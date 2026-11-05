import '../core/utils/parsing.dart';

class GoldRate {
  const GoldRate({
    required this.id,
    required this.karat,
    required this.ratePerGram,
    required this.effectiveDate,
    required this.createdByName,
  });

  final int id;
  final int karat;
  final double ratePerGram;
  final DateTime? effectiveDate;
  final String? createdByName;

  factory GoldRate.fromJson(Map<String, dynamic> json) {
    final createdBy = asJsonMap(json['created_by']);

    return GoldRate(
      id: asInt(json['id']) ?? 0,
      karat: asInt(json['karat']) ?? 0,
      ratePerGram: asDouble(json['rate_per_gram']) ?? 0,
      effectiveDate: asDate(json['effective_date']),
      createdByName: asString(createdBy['name']),
    );
  }
}
