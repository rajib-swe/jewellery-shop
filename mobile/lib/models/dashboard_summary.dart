import '../core/utils/parsing.dart';

class DashboardSummary {
  const DashboardSummary({
    required this.date,
    required this.today,
    required this.pawns,
  });

  final DateTime? date;
  final DashboardToday today;
  final DashboardPawns pawns;

  factory DashboardSummary.fromJson(Map<String, dynamic> json) {
    return DashboardSummary(
      date: asDate(json['date']),
      today: DashboardToday.fromJson(asJsonMap(json['today'])),
      pawns: DashboardPawns.fromJson(asJsonMap(json['pawns'])),
    );
  }
}

class DashboardToday {
  const DashboardToday({
    required this.invoiceCount,
    required this.salesTotal,
    required this.salesPaid,
    required this.cashBalance,
    required this.cashIn,
    required this.cashOut,
  });

  final int invoiceCount;
  final double salesTotal;
  final double salesPaid;
  final double cashBalance;
  final double cashIn;
  final double cashOut;

  factory DashboardToday.fromJson(Map<String, dynamic> json) {
    return DashboardToday(
      invoiceCount: asInt(json['invoice_count']) ?? 0,
      salesTotal: asDouble(json['sales_total']) ?? 0,
      salesPaid: asDouble(json['sales_paid']) ?? 0,
      cashBalance: asDouble(json['cash_balance']) ?? 0,
      cashIn: asDouble(json['cash_in']) ?? 0,
      cashOut: asDouble(json['cash_out']) ?? 0,
    );
  }
}

class DashboardPawns {
  const DashboardPawns({
    required this.activeCount,
    required this.outstandingPrincipal,
    required this.interestDue,
    required this.overdueCount,
  });

  final int activeCount;
  final double outstandingPrincipal;
  final double interestDue;
  final int overdueCount;

  factory DashboardPawns.fromJson(Map<String, dynamic> json) {
    return DashboardPawns(
      activeCount: asInt(json['active_count']) ?? 0,
      outstandingPrincipal: asDouble(json['outstanding_principal']) ?? 0,
      interestDue: asDouble(json['interest_due']) ?? 0,
      overdueCount: asInt(json['overdue_count']) ?? 0,
    );
  }
}
