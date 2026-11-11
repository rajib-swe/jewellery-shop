import 'package:dio/dio.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_response.dart';
import '../core/api/endpoints.dart';
import '../core/utils/parsing.dart';
import '../models/customer.dart';

class CustomerService {
  const CustomerService({required this.dio});

  final Dio dio;

  Future<CustomerPage> list({
    String? search,
    int page = 1,
    int perPage = 20,
  }) async {
    final query = search?.trim() ?? '';

    final response = await ApiException.guard(
      () => dio.get<Object?>(
        Endpoints.customers,
        queryParameters: {
          'page': page,
          'per_page': perPage,
          if (query.isNotEmpty) 'search': query,
        },
      ),
    );

    final items = dataList(response.data)
        .map((customer) => Customer.fromJson(asJsonMap(customer)))
        .toList(growable: false);
    final meta = metaMap(response.data);

    return CustomerPage(
      items: items,
      currentPage: asInt(meta['current_page']) ?? page,
      lastPage: asInt(meta['last_page']) ?? page,
      total: asInt(meta['total']) ?? items.length,
    );
  }

  Future<Customer> show(int id) async {
    final response = await ApiException.guard(
      () => dio.get<Object?>(Endpoints.customer(id)),
    );

    return Customer.fromJson(dataMap(response.data));
  }

  Future<CustomerHistory> history(int id) async {
    final response = await ApiException.guard(
      () => dio.get<Object?>(Endpoints.customerHistory(id)),
    );

    return CustomerHistory.fromJson(dataMap(response.data));
  }

  Future<Customer> create({
    required String name,
    required String phone,
    double openingBalance = 0,
    String? address,
    String? nid,
    String? notes,
  }) async {
    final response = await ApiException.guard(
      () => dio.post<Object?>(
        Endpoints.customers,
        data: {
          'name': name.trim(),
          'phone': phone.trim(),
          'opening_balance': openingBalance,
          if (address != null && address.trim().isNotEmpty) 'address': address.trim(),
          if (nid != null && nid.trim().isNotEmpty) 'nid': nid.trim(),
          if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
        },
      ),
    );

    return Customer.fromJson(dataMap(response.data));
  }
}
