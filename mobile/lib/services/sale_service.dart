import 'package:dio/dio.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_response.dart';
import '../core/api/endpoints.dart';
import '../core/utils/parsing.dart';
import '../models/sale.dart';

class SaleService {
  const SaleService({required this.dio});

  final Dio dio;

  Future<SalePage> list({
    String? search,
    String? status,
    int? customerId,
    String? dateFrom,
    String? dateTo,
    int page = 1,
    int perPage = 20,
  }) async {
    final query = search?.trim() ?? '';

    final response = await ApiException.guard(
      () => dio.get<Object?>(
        Endpoints.sales,
        queryParameters: {
          'page': page,
          'per_page': perPage,
          if (query.isNotEmpty) 'search': query,
          if (status != null && status.isNotEmpty) 'status': status,
          'customer_id': ?customerId,
          if (dateFrom != null && dateFrom.isNotEmpty) 'date_from': dateFrom,
          if (dateTo != null && dateTo.isNotEmpty) 'date_to': dateTo,
        },
      ),
    );

    final items = dataList(response.data)
        .map((sale) => Sale.fromJson(asJsonMap(sale)))
        .toList(growable: false);
    final meta = metaMap(response.data);

    return SalePage(
      items: items,
      currentPage: asInt(meta['current_page']) ?? page,
      lastPage: asInt(meta['last_page']) ?? page,
      total: asInt(meta['total']) ?? items.length,
    );
  }

  Future<Sale> show(int id) async {
    final response = await ApiException.guard(
      () => dio.get<Object?>(Endpoints.sale(id)),
    );

    return Sale.fromJson(dataMap(response.data));
  }

  /// The API has no preview endpoint, so the totals the server computes in this
  /// response are the only authoritative figures — the app renders them and
  /// never recalculates anything itself.
  Future<Sale> create({
    int? customerId,
    required List<SaleLineInput> items,
    List<SalePaymentInput> payments = const [],
    List<SaleExchangeInput> exchanges = const [],
    double? discount,
    String? notes,
  }) async {
    final response = await ApiException.guard(
      () => dio.post<Object?>(
        Endpoints.sales,
        data: {
          'customer_id': ?customerId,
          'items': [for (final item in items) item.toJson()],
          if (payments.isNotEmpty)
            'payments': [for (final payment in payments) payment.toJson()],
          if (exchanges.isNotEmpty)
            'exchanges': [for (final exchange in exchanges) exchange.toJson()],
          if (discount != null) 'discount': discount.toStringAsFixed(2),
          if (notes != null && notes.isNotEmpty) 'notes': notes,
        },
      ),
    );

    return Sale.fromJson(dataMap(response.data));
  }

  Future<Sale> addPayment(
    int id, {
    required String method,
    required double amount,
    String? reference,
  }) async {
    final response = await ApiException.guard(
      () => dio.post<Object?>(
        Endpoints.salePayments(id),
        data: {
          'method': method,
          'amount': amount.toStringAsFixed(2),
          if (reference != null && reference.isNotEmpty) 'reference': reference,
        },
      ),
    );

    return Sale.fromJson(dataMap(response.data));
  }

  Future<Sale> voidSale(int id, {String? reason}) async {
    final response = await ApiException.guard(
      () => dio.post<Object?>(
        Endpoints.saleVoid(id),
        data: {if (reason != null && reason.isNotEmpty) 'reason': reason},
      ),
    );

    return Sale.fromJson(dataMap(response.data));
  }
}
