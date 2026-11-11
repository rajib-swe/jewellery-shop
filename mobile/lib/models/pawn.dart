import '../core/utils/parsing.dart';
import 'sale.dart' show PartyRef;

const List<String> pawnStatuses = ['active', 'redeemed', 'forfeited'];

String pawnStatusLabel(String status) => switch (status) {
  'active' => 'চলতি',
  'overdue' => 'মেয়াদোত্তীর্ণ',
  'redeemed' => 'খালাসকৃত',
  'forfeited' => 'বাজেয়াপ্ত',
  _ => status.isEmpty ? '—' : status,
};

String pawnPaymentTypeLabel(String type) => switch (type) {
  'interest' => 'সুদ পরিশোধ',
  'principal' => 'মূলধন পরিশোধ',
  'redeem' => 'খালাস',
  _ => type.isEmpty ? '—' : type,
};

class PawnItem {
  const PawnItem({
    required this.id,
    required this.description,
    required this.karat,
    required this.grossWeight,
    required this.stoneWeight,
    required this.netWeight,
    required this.estimatedValue,
    required this.photoUrl,
    required this.itemId,
  });

  final int id;
  final String description;
  final int karat;
  final double grossWeight;
  final double stoneWeight;
  final double netWeight;
  final double estimatedValue;
  final String? photoUrl;
  final int? itemId;

  factory PawnItem.fromJson(Map<String, dynamic> json) => PawnItem(
    id: asInt(json['id']) ?? 0,
    description: asString(json['description']) ?? '',
    karat: asInt(json['karat']) ?? 0,
    grossWeight: asDouble(json['gross_weight']) ?? 0,
    stoneWeight: asDouble(json['stone_weight']) ?? 0,
    netWeight: asDouble(json['net_weight']) ?? 0,
    estimatedValue: asDouble(json['estimated_value']) ?? 0,
    photoUrl: asString(json['photo_url']),
    itemId: asInt(json['item_id']),
  );
}

class PawnPayment {
  const PawnPayment({
    required this.id,
    required this.type,
    required this.amount,
    required this.date,
    required this.method,
    required this.reference,
    required this.note,
    required this.userName,
  });

  final int id;
  final String type;
  final double amount;
  final DateTime? date;
  final String method;
  final String? reference;
  final String? note;
  final String? userName;

  factory PawnPayment.fromJson(Map<String, dynamic> json) {
    final user = asJsonMap(json['user']);

    return PawnPayment(
      id: asInt(json['id']) ?? 0,
      type: asString(json['type']) ?? '',
      amount: asDouble(json['amount']) ?? 0,
      date: asDate(json['date']),
      method: asString(json['method']) ?? '',
      reference: asString(json['reference']),
      note: asString(json['note']),
      userName: asString(user['name']),
    );
  }
}

class PawnPeriod {
  const PawnPeriod({
    required this.from,
    required this.to,
    required this.days,
    required this.months,
    required this.principal,
    required this.interest,
  });

  final DateTime? from;
  final DateTime? to;
  final int days;
  final double months;
  final double principal;
  final double interest;

  factory PawnPeriod.fromJson(Map<String, dynamic> json) => PawnPeriod(
    from: asDate(json['from']),
    to: asDate(json['to']),
    days: asInt(json['days']) ?? 0,
    months: asDouble(json['months']) ?? 0,
    principal: asDouble(json['principal']) ?? 0,
    interest: asDouble(json['interest']) ?? 0,
  );
}

class PawnSummary {
  const PawnSummary({
    required this.outstandingPrincipal,
    required this.principalPaid,
    required this.interestRate,
    required this.interestAccrued,
    required this.interestPaid,
    required this.interestDue,
    required this.totalPaid,
    required this.totalPayable,
    required this.asOf,
    required this.periods,
  });

  final double outstandingPrincipal;
  final double principalPaid;
  final double interestRate;
  final double interestAccrued;
  final double interestPaid;
  final double interestDue;
  final double totalPaid;
  final double totalPayable;
  final DateTime? asOf;
  final List<PawnPeriod> periods;

  factory PawnSummary.fromJson(Map<String, dynamic> json) => PawnSummary(
    outstandingPrincipal: asDouble(json['outstanding_principal']) ?? 0,
    principalPaid: asDouble(json['principal_paid']) ?? 0,
    interestRate: asDouble(json['interest_rate']) ?? 0,
    interestAccrued: asDouble(json['interest_accrued']) ?? 0,
    interestPaid: asDouble(json['interest_paid']) ?? 0,
    interestDue: asDouble(json['interest_due']) ?? 0,
    totalPaid: asDouble(json['total_paid']) ?? 0,
    totalPayable: asDouble(json['total_payable']) ?? 0,
    asOf: asDate(json['as_of']),
    periods: _list(json['periods'], PawnPeriod.fromJson),
  );
}

class Pawn {
  const Pawn({
    required this.id,
    required this.pawnNo,
    required this.date,
    required this.dueDate,
    required this.status,
    required this.principal,
    required this.interestRate,
    required this.interestType,
    required this.notes,
    required this.isOverdue,
    required this.customer,
    required this.userName,
    required this.items,
    required this.payments,
    required this.summary,
    required this.closeReason,
  });

  final int id;
  final String pawnNo;
  final DateTime? date;
  final DateTime? dueDate;
  final String status;
  final double principal;
  final double interestRate;
  final String interestType;
  final String? notes;
  final bool isOverdue;
  final PartyRef? customer;
  final String? userName;
  final List<PawnItem> items;
  final List<PawnPayment> payments;
  final PawnSummary summary;
  final String? closeReason;

  bool get isOpen => status == 'active';
  bool get isRedeemed => status == 'redeemed';
  bool get isForfeited => status == 'forfeited';

  factory Pawn.fromJson(Map<String, dynamic> json) {
    final customer = asJsonMap(json['customer']);
    final user = asJsonMap(json['user']);

    return Pawn(
      id: asInt(json['id']) ?? 0,
      pawnNo: asString(json['pawn_no']) ?? '',
      date: asDate(json['date']),
      dueDate: asDate(json['due_date']),
      status: asString(json['status']) ?? '',
      principal: asDouble(json['principal']) ?? 0,
      interestRate: asDouble(json['interest_rate']) ?? 0,
      interestType: asString(json['interest_type']) ?? '',
      notes: asString(json['notes']),
      isOverdue: json['is_overdue'] == true,
      customer: customer.isEmpty ? null : PartyRef.fromJson(customer),
      userName: asString(user['name']),
      items: _list(json['items'], PawnItem.fromJson),
      payments: _list(json['payments'], PawnPayment.fromJson),
      summary: PawnSummary.fromJson(asJsonMap(json['summary'])),
      closeReason: asString(json['close_reason']),
    );
  }
}

class PawnPage {
  const PawnPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  final List<Pawn> items;
  final int currentPage;
  final int lastPage;
  final int total;
}

class PawnItemInput {
  const PawnItemInput({
    required this.description,
    required this.karat,
    required this.grossWeight,
    this.stoneWeight = 0,
    required this.estimatedValue,
  });

  final String description;
  final int karat;
  final double grossWeight;
  final double stoneWeight;
  final double estimatedValue;

  Map<String, Object?> toJson() => {
    'description': description,
    'karat': karat,
    'gross_weight': grossWeight.toStringAsFixed(3),
    if (stoneWeight > 0) 'stone_weight': stoneWeight.toStringAsFixed(3),
    'estimated_value': estimatedValue.toStringAsFixed(2),
  };
}

List<T> _list<T>(Object? raw, T Function(Map<String, dynamic> json) build) {
  if (raw is! List) {
    return const [];
  }

  return raw.map((item) => build(asJsonMap(item))).toList(growable: false);
}
