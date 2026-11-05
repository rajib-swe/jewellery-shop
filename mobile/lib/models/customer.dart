import '../core/utils/parsing.dart';

class Customer {
  const Customer({
    required this.id,
    required this.code,
    required this.name,
    required this.phone,
    required this.nid,
    required this.address,
    required this.photoUrl,
    required this.openingBalance,
    required this.notes,
    required this.createdAt,
  });

  final int id;
  final String code;
  final String name;
  final String phone;
  final String? nid;
  final String? address;
  final String? photoUrl;
  final double openingBalance;
  final String? notes;
  final DateTime? createdAt;

  String get initials {
    final parts = name.trim().split(RegExp(r'\s+'));
    return parts
        .take(2)
        .where((part) => part.isNotEmpty)
        .map((part) => part[0].toUpperCase())
        .join();
  }

  factory Customer.fromJson(Map<String, dynamic> json) {
    return Customer(
      id: asInt(json['id']) ?? 0,
      code: asString(json['code']) ?? '',
      name: asString(json['name']) ?? '',
      phone: asString(json['phone']) ?? '',
      nid: asString(json['nid']),
      address: asString(json['address']),
      photoUrl: asString(json['photo_url']),
      openingBalance: asDouble(json['opening_balance']) ?? 0,
      notes: asString(json['notes']),
      createdAt: asDate(json['created_at']),
    );
  }
}

class CustomerPage {
  const CustomerPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  final List<Customer> items;
  final int currentPage;
  final int lastPage;
  final int total;
}

class CustomerHistory {
  const CustomerHistory({
    required this.sales,
    required this.pawns,
    required this.payments,
    required this.dueBalance,
  });

  final List<CustomerSale> sales;
  final List<CustomerPawn> pawns;
  final List<CustomerPayment> payments;
  final double dueBalance;

  factory CustomerHistory.fromJson(Map<String, dynamic> json) {
    return CustomerHistory(
      sales: _list(json['sales'], CustomerSale.fromJson),
      pawns: _list(json['pawns'], CustomerPawn.fromJson),
      payments: _list(json['payments'], CustomerPayment.fromJson),
      dueBalance: asDouble(json['due_balance']) ?? 0,
    );
  }
}

class CustomerSale {
  const CustomerSale({
    required this.id,
    required this.invoiceNo,
    required this.date,
    required this.status,
    required this.total,
    required this.paid,
    required this.due,
  });

  final int id;
  final String invoiceNo;
  final DateTime? date;
  final String status;
  final double total;
  final double paid;
  final double due;

  factory CustomerSale.fromJson(Map<String, dynamic> json) {
    return CustomerSale(
      id: asInt(json['id']) ?? 0,
      invoiceNo: asString(json['invoice_no']) ?? '',
      date: asDate(json['date']),
      status: asString(json['status']) ?? '',
      total: asDouble(json['total']) ?? 0,
      paid: asDouble(json['paid']) ?? 0,
      due: asDouble(json['due']) ?? 0,
    );
  }
}

class CustomerPawn {
  const CustomerPawn({
    required this.id,
    required this.pawnNo,
    required this.date,
    required this.dueDate,
    required this.status,
    required this.principal,
    required this.outstandingPrincipal,
    required this.interestDue,
    required this.totalPayable,
  });

  final int id;
  final String pawnNo;
  final DateTime? date;
  final DateTime? dueDate;
  final String status;
  final double principal;
  final double outstandingPrincipal;
  final double interestDue;
  final double totalPayable;

  factory CustomerPawn.fromJson(Map<String, dynamic> json) {
    return CustomerPawn(
      id: asInt(json['id']) ?? 0,
      pawnNo: asString(json['pawn_no']) ?? '',
      date: asDate(json['date']),
      dueDate: asDate(json['due_date']),
      status: asString(json['status']) ?? '',
      principal: asDouble(json['principal']) ?? 0,
      outstandingPrincipal: asDouble(json['outstanding_principal']) ?? 0,
      interestDue: asDouble(json['interest_due']) ?? 0,
      totalPayable: asDouble(json['total_payable']) ?? 0,
    );
  }
}

class CustomerPayment {
  const CustomerPayment({
    required this.id,
    required this.invoiceNo,
    required this.date,
    required this.method,
    required this.amount,
    required this.reference,
  });

  final int id;
  final String invoiceNo;
  final DateTime? date;
  final String method;
  final double amount;
  final String? reference;

  factory CustomerPayment.fromJson(Map<String, dynamic> json) {
    return CustomerPayment(
      id: asInt(json['id']) ?? 0,
      invoiceNo: asString(json['invoice_no']) ?? '',
      date: asDate(json['date']),
      method: asString(json['method']) ?? '',
      amount: asDouble(json['amount']) ?? 0,
      reference: asString(json['reference']),
    );
  }
}

class CustomerProfile {
  const CustomerProfile({required this.customer, required this.history});

  final Customer customer;
  final CustomerHistory history;
}

List<T> _list<T>(Object? raw, T Function(Map<String, dynamic> json) build) {
  if (raw is! List) {
    return const [];
  }

  return raw.map((item) => build(asJsonMap(item))).toList(growable: false);
}
