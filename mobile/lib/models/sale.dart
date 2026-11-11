import '../core/utils/parsing.dart';

const List<String> paymentMethods = ['cash', 'bkash', 'nagad', 'card', 'bank'];

String paymentMethodLabel(String method) => switch (method) {
  'cash' => 'নগদ (ক্যাশ)',
  'bkash' => 'বিকাশ',
  'nagad' => 'নগদ (মোবাইল)',
  'card' => 'কার্ড',
  'bank' => 'ব্যাংক',
  _ => method.isEmpty ? '—' : method,
};

String saleStatusLabel(String status) => switch (status) {
  'completed' => 'সম্পন্ন',
  'void' => 'বাতিল',
  _ => status.isEmpty ? '—' : status,
};

class SaleLine {
  const SaleLine({
    required this.id,
    required this.itemId,
    required this.tagNo,
    required this.name,
    required this.karat,
    required this.weight,
    required this.rate,
    required this.goldValue,
    required this.making,
    required this.stonePrice,
    required this.lineTotal,
  });

  final int id;
  final int? itemId;
  final String tagNo;
  final String name;
  final int karat;
  final double weight;
  final double rate;
  final double goldValue;
  final double making;
  final double stonePrice;
  final double lineTotal;

  factory SaleLine.fromJson(Map<String, dynamic> json) => SaleLine(
    id: asInt(json['id']) ?? 0,
    itemId: asInt(json['item_id']),
    tagNo: asString(json['tag_no']) ?? '',
    name: asString(json['name']) ?? '',
    karat: asInt(json['karat']) ?? 0,
    weight: asDouble(json['weight']) ?? 0,
    rate: asDouble(json['rate']) ?? 0,
    goldValue: asDouble(json['gold_value']) ?? 0,
    making: asDouble(json['making']) ?? 0,
    stonePrice: asDouble(json['stone_price']) ?? 0,
    lineTotal: asDouble(json['line_total']) ?? 0,
  );
}

class SalePayment {
  const SalePayment({
    required this.id,
    required this.method,
    required this.amount,
    required this.reference,
    required this.userName,
    required this.createdAt,
  });

  final int id;
  final String method;
  final double amount;
  final String? reference;
  final String? userName;
  final DateTime? createdAt;

  factory SalePayment.fromJson(Map<String, dynamic> json) {
    final user = asJsonMap(json['user']);

    return SalePayment(
      id: asInt(json['id']) ?? 0,
      method: asString(json['method']) ?? '',
      amount: asDouble(json['amount']) ?? 0,
      reference: asString(json['reference']),
      userName: asString(user['name']),
      createdAt: asDate(json['created_at']),
    );
  }
}

class SaleExchange {
  const SaleExchange({
    required this.id,
    required this.description,
    required this.karat,
    required this.weight,
    required this.rate,
    required this.amount,
  });

  final int id;
  final String description;
  final int karat;
  final double weight;
  final double rate;
  final double amount;

  factory SaleExchange.fromJson(Map<String, dynamic> json) => SaleExchange(
    id: asInt(json['id']) ?? 0,
    description: asString(json['description']) ?? '',
    karat: asInt(json['karat']) ?? 0,
    weight: asDouble(json['weight']) ?? 0,
    rate: asDouble(json['rate']) ?? 0,
    amount: asDouble(json['amount']) ?? 0,
  );
}

class PartyRef {
  const PartyRef({
    required this.id,
    required this.code,
    required this.name,
    required this.phone,
  });

  final int id;
  final String code;
  final String name;
  final String phone;

  factory PartyRef.fromJson(Map<String, dynamic> json) => PartyRef(
    id: asInt(json['id']) ?? 0,
    code: asString(json['code']) ?? '',
    name: asString(json['name']) ?? '',
    phone: asString(json['phone']) ?? '',
  );
}

class Sale {
  const Sale({
    required this.id,
    required this.invoiceNo,
    required this.date,
    required this.status,
    required this.customer,
    required this.userName,
    required this.subtotal,
    required this.discount,
    required this.vat,
    required this.exchangeAmount,
    required this.total,
    required this.paid,
    required this.due,
    required this.notes,
    required this.voidedAt,
    required this.voidReason,
    required this.items,
    required this.payments,
    required this.exchanges,
  });

  final int id;
  final String invoiceNo;
  final DateTime? date;
  final String status;
  final PartyRef? customer;
  final String? userName;
  final double subtotal;
  final double discount;
  final double vat;
  final double exchangeAmount;
  final double total;
  final double paid;
  final double due;
  final String? notes;
  final DateTime? voidedAt;
  final String? voidReason;
  final List<SaleLine> items;
  final List<SalePayment> payments;
  final List<SaleExchange> exchanges;

  bool get isVoided => status == 'void';

  factory Sale.fromJson(Map<String, dynamic> json) {
    final customer = asJsonMap(json['customer']);
    final user = asJsonMap(json['user']);

    return Sale(
      id: asInt(json['id']) ?? 0,
      invoiceNo: asString(json['invoice_no']) ?? '',
      date: asDate(json['date']),
      status: asString(json['status']) ?? '',
      customer: customer.isEmpty ? null : PartyRef.fromJson(customer),
      userName: asString(user['name']),
      subtotal: asDouble(json['subtotal']) ?? 0,
      discount: asDouble(json['discount']) ?? 0,
      vat: asDouble(json['vat']) ?? 0,
      exchangeAmount: asDouble(json['exchange_amount']) ?? 0,
      total: asDouble(json['total']) ?? 0,
      paid: asDouble(json['paid']) ?? 0,
      due: asDouble(json['due']) ?? 0,
      notes: asString(json['notes']),
      voidedAt: asDate(json['voided_at']),
      voidReason: asString(json['void_reason']),
      items: _list(json['items'], SaleLine.fromJson),
      payments: _list(json['payments'], SalePayment.fromJson),
      exchanges: _list(json['exchanges'], SaleExchange.fromJson),
    );
  }
}

class SalePage {
  const SalePage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  final List<Sale> items;
  final int currentPage;
  final int lastPage;
  final int total;
}

class SaleLineInput {
  const SaleLineInput({
    this.itemId,
    this.name,
    this.karat,
    this.weight,
    this.makingType,
    this.makingValue,
    this.stonePrice,
  });

  final int? itemId;
  final String? name;
  final int? karat;
  final double? weight;
  final String? makingType;
  final double? makingValue;
  final double? stonePrice;

  Map<String, Object?> toJson() => {
    if (itemId != null) 'item_id': itemId,
    if (itemId == null) 'name': name,
    if (itemId == null) 'karat': karat,
    if (itemId == null) 'weight': weight?.toStringAsFixed(3),
    if (itemId == null) 'making_type': makingType,
    if (itemId == null) 'making_value': makingValue?.toStringAsFixed(2),
    if (itemId == null) 'stone_price': stonePrice?.toStringAsFixed(2),
  };
}

class SalePaymentInput {
  const SalePaymentInput({
    required this.method,
    required this.amount,
    this.reference,
  });

  final String method;
  final double amount;
  final String? reference;

  Map<String, Object?> toJson() => {
    'method': method,
    'amount': amount.toStringAsFixed(2),
    if (reference != null && reference!.isNotEmpty) 'reference': reference,
  };
}

class SaleExchangeInput {
  const SaleExchangeInput({
    required this.description,
    required this.karat,
    required this.weight,
  });

  final String description;
  final int karat;
  final double weight;

  Map<String, Object?> toJson() => {
    'description': description,
    'karat': karat,
    'weight': weight.toStringAsFixed(3),
  };
}

List<T> _list<T>(Object? raw, T Function(Map<String, dynamic> json) build) {
  if (raw is! List) {
    return const [];
  }

  return raw.map((item) => build(asJsonMap(item))).toList(growable: false);
}
