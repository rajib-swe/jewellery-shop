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
}
