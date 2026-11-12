class CashBookMethodSummary {
  const CashBookMethodSummary({
    required this.method,
    required this.totalIn,
    required this.totalOut,
    required this.net,
  });

  factory CashBookMethodSummary.fromJson(Map<String, dynamic> json) {
    return CashBookMethodSummary(
      method: (json['method'] as String?) ?? 'cash',
      totalIn: _parseDouble(json['total_in']),
      totalOut: _parseDouble(json['total_out']),
      net: _parseDouble(json['net']),
    );
  }

  final String method;
  final double totalIn;
  final double totalOut;
  final double net;

  static double _parseDouble(dynamic val) {
    if (val == null) return 0;
    if (val is num) return val.toDouble();
    return double.tryParse(val.toString()) ?? 0;
  }
}

class CashBookSummary {
  const CashBookSummary({
    required this.date,
    required this.openingBalance,
    required this.totalIn,
    required this.totalOut,
    required this.closingBalance,
    required this.transactionCount,
    required this.isLocked,
    required this.byMethod,
  });

  factory CashBookSummary.fromJson(Map<String, dynamic> json) {
    final rawByMethod = json['by_method'];
    final methods = <CashBookMethodSummary>[];
    if (rawByMethod is List) {
      for (final item in rawByMethod) {
        if (item is Map<String, dynamic>) {
          methods.add(CashBookMethodSummary.fromJson(item));
        }
      }
    }

    return CashBookSummary(
      date: (json['date'] as String?) ?? '',
      openingBalance: _parseDouble(json['opening_balance']),
      totalIn: _parseDouble(json['total_in']),
      totalOut: _parseDouble(json['total_out']),
      closingBalance: _parseDouble(json['closing_balance']),
      transactionCount: (json['transaction_count'] as num?)?.toInt() ?? 0,
      isLocked: (json['locked'] as bool?) ?? false,
      byMethod: methods,
    );
  }

  final String date;
  final double openingBalance;
  final double totalIn;
  final double totalOut;
  final double closingBalance;
  final int transactionCount;
  final bool isLocked;
  final List<CashBookMethodSummary> byMethod;

  static double _parseDouble(dynamic val) {
    if (val == null) return 0;
    if (val is num) return val.toDouble();
    return double.tryParse(val.toString()) ?? 0;
  }
}

class DailyClosingRecord {
  const DailyClosingRecord({
    required this.id,
    required this.date,
    required this.openingBalance,
    required this.totalIn,
    required this.totalOut,
    required this.closingBalance,
    required this.isLocked,
    this.closedAt,
    this.reopenedAt,
    this.closedByName,
    this.reopenedByName,
    this.note,
  });

  factory DailyClosingRecord.fromJson(Map<String, dynamic> json) {
    return DailyClosingRecord(
      id: (json['id'] as num?)?.toInt() ?? 0,
      date: (json['date'] as String?) ?? '',
      openingBalance: _parseDouble(json['opening_balance']),
      totalIn: _parseDouble(json['total_in']),
      totalOut: _parseDouble(json['total_out']),
      closingBalance: _parseDouble(json['closing_balance']),
      isLocked: (json['is_locked'] as bool?) ?? false,
      closedAt: json['closed_at'] != null ? DateTime.tryParse(json['closed_at'].toString()) : null,
      reopenedAt: json['reopened_at'] != null ? DateTime.tryParse(json['reopened_at'].toString()) : null,
      closedByName: json['closed_by'] is Map ? (json['closed_by']['name'] as String?) : null,
      reopenedByName: json['reopened_by'] is Map ? (json['reopened_by']['name'] as String?) : null,
      note: json['note'] as String?,
    );
  }

  final int id;
  final String date;
  final double openingBalance;
  final double totalIn;
  final double totalOut;
  final double closingBalance;
  final bool isLocked;
  final DateTime? closedAt;
  final DateTime? reopenedAt;
  final String? closedByName;
  final String? reopenedByName;
  final String? note;

  static double _parseDouble(dynamic val) {
    if (val == null) return 0;
    if (val is num) return val.toDouble();
    return double.tryParse(val.toString()) ?? 0;
  }
}

