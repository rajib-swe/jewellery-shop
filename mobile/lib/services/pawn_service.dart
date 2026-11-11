import 'package:dio/dio.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_response.dart';
import '../core/api/endpoints.dart';
import '../core/utils/parsing.dart';
import '../models/pawn.dart';

class PawnService {
  const PawnService({required this.dio});

  final Dio dio;

  Future<PawnPage> list({
    String? search,
    String? status,
    int? customerId,
    bool? overdue,
    String? dateFrom,
    String? dateTo,
    int page = 1,
    int perPage = 20,
  }) async {
    final query = search?.trim() ?? '';

    final response = await ApiException.guard(
      () => dio.get<Object?>(
        Endpoints.pawns,
        queryParameters: {
          'page': page,
          'per_page': perPage,
          if (query.isNotEmpty) 'search': query,
          if (status != null && status.isNotEmpty) 'status': status,
          'customer_id': ?customerId,
          'overdue': ?overdue,
          if (dateFrom != null && dateFrom.isNotEmpty) 'date_from': dateFrom,
          if (dateTo != null && dateTo.isNotEmpty) 'date_to': dateTo,
        },
      ),
    );

    final items = dataList(response.data)
        .map((pawn) => Pawn.fromJson(asJsonMap(pawn)))
        .toList(growable: false);
    final meta = metaMap(response.data);

    return PawnPage(
      items: items,
      currentPage: asInt(meta['current_page']) ?? page,
      lastPage: asInt(meta['last_page']) ?? page,
      total: asInt(meta['total']) ?? items.length,
    );
  }

  /// `data.summary` carries the server's live interest breakdown, so the pawn
  /// detail screen never computes interest itself.
  Future<Pawn> show(int id) async {
    final response = await ApiException.guard(
      () => dio.get<Object?>(Endpoints.pawn(id)),
    );

    return Pawn.fromJson(dataMap(response.data));
  }

  Future<Pawn> create({
    required int customerId,
    required List<PawnItemInput> items,
    required double principal,
    double? interestRate,
    String? dueDate,
    String? notes,
  }) async {
    final response = await ApiException.guard(
      () => dio.post<Object?>(
        Endpoints.pawns,
        data: {
          'customer_id': customerId,
          'principal': principal.toStringAsFixed(2),
          'items': [for (final item in items) item.toJson()],
          if (interestRate != null)
            'interest_rate': interestRate.toStringAsFixed(2),
          if (dueDate != null && dueDate.isNotEmpty) 'due_date': dueDate,
          if (notes != null && notes.isNotEmpty) 'notes': notes,
        },
      ),
    );

    return Pawn.fromJson(dataMap(response.data));
  }

  Future<Pawn> addPayment(
    int id, {
    required double amount,
    String? method,
    String? note,
  }) async {
    final response = await ApiException.guard(
      () => dio.post<Object?>(
        Endpoints.pawnPayments(id),
        data: {
          'amount': amount.toStringAsFixed(2),
          if (method != null && method.isNotEmpty) 'method': method,
          if (note != null && note.isNotEmpty) 'note': note,
        },
      ),
    );

    return Pawn.fromJson(dataMap(response.data));
  }

  Future<Pawn> redeem(int id, {String? method, String? note}) async {
    final response = await ApiException.guard(
      () => dio.post<Object?>(
        Endpoints.pawnRedeem(id),
        data: {
          if (method != null && method.isNotEmpty) 'method': method,
          if (note != null && note.isNotEmpty) 'note': note,
        },
      ),
    );

    return Pawn.fromJson(dataMap(response.data));
  }

  Future<Pawn> renew(int id, {int? termDays, String? note}) async {
    final response = await ApiException.guard(
      () => dio.post<Object?>(
        Endpoints.pawnRenew(id),
        data: {
          if (termDays != null && termDays > 0) 'term_days': termDays,
          if (note != null && note.isNotEmpty) 'note': note,
        },
      ),
    );

    return Pawn.fromJson(dataMap(response.data));
  }
}
