import 'dart:typed_data';

import 'package:dio/dio.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_response.dart';
import '../core/api/endpoints.dart';
import '../core/utils/parsing.dart';
import '../models/item.dart';

class InventoryService {
  const InventoryService({required this.dio});

  final Dio dio;

  Future<ItemPage> list({
    String? search,
    int? categoryId,
    int? karat,
    String? status,
    int page = 1,
    int perPage = 20,
  }) async {
    final query = search?.trim() ?? '';

    final response = await ApiException.guard(
      () => dio.get<Object?>(
        Endpoints.items,
        queryParameters: {
          'page': page,
          'per_page': perPage,
          if (query.isNotEmpty) 'search': query,
          'category_id': ?categoryId,
          'karat': ?karat,
          if (status != null && status.isNotEmpty) 'status': status,
        },
      ),
    );

    final items = dataList(response.data)
        .map((item) => Item.fromJson(asJsonMap(item)))
        .toList(growable: false);
    final meta = metaMap(response.data);

    return ItemPage(
      items: items,
      currentPage: asInt(meta['current_page']) ?? page,
      lastPage: asInt(meta['last_page']) ?? page,
      total: asInt(meta['total']) ?? items.length,
    );
  }

  /// A scan resolves a tag through the same search index the list uses,
  /// because there is no `GET /items/{tag_no}` route; the exact tag number or
  /// barcode is matched client-side against whatever the search returns.
  Future<Item?> findByTag(String code) async {
    final lookup = code.trim();

    if (lookup.isEmpty) {
      return null;
    }

    final response = await ApiException.guard(
      () => dio.get<Object?>(
        Endpoints.items,
        queryParameters: {'search': lookup, 'per_page': 20},
      ),
    );

    final needle = lookup.toLowerCase();
    final hits = dataList(response.data)
        .map((item) => Item.fromJson(asJsonMap(item)))
        .toList(growable: false);

    for (final item in hits) {
      final tag = item.tagNo.toLowerCase();
      final barcode = item.barcode?.toLowerCase();

      if (tag == needle || (barcode != null && barcode == needle)) {
        return item;
      }
    }

    return null;
  }

  Future<Item> show(int id) async {
    final response = await ApiException.guard(
      () => dio.get<Object?>(Endpoints.item(id)),
    );

    return Item.fromJson(dataMap(response.data));
  }

  Future<List<ItemCategory>> categories() async {
    final response = await ApiException.guard(
      () => dio.get<Object?>(Endpoints.categories),
    );

    return dataList(response.data)
        .map((category) => ItemCategory.fromJson(asJsonMap(category)))
        .toList(growable: false);
  }

  Future<StockSummary> summary() async {
    final response = await ApiException.guard(
      () => dio.get<Object?>(Endpoints.stockSummary),
    );

    return StockSummary.fromJson(dataMap(response.data));
  }

  Future<Item> create({
    required int categoryId,
    required String name,
    required int karat,
    required double grossWeight,
    double stoneWeight = 0,
    String makingType = 'fixed',
    double makingValue = 0,
    double stonePrice = 0,
    String? barcode,
    Uint8List? imageBytes,
  }) async {
    final form = FormData.fromMap({
      'category_id': categoryId.toString(),
      'name': name,
      'karat': karat.toString(),
      'gross_weight': grossWeight.toStringAsFixed(3),
      'stone_weight': stoneWeight.toStringAsFixed(3),
      'making_type': makingType,
      'making_value': makingValue.toStringAsFixed(2),
      'stone_price': stonePrice.toStringAsFixed(2),
      if (barcode != null && barcode.isNotEmpty) 'barcode': barcode,
    });

    if (imageBytes != null) {
      form.files.add(
        MapEntry(
          'image',
          MultipartFile.fromBytes(imageBytes, filename: 'item.jpg'),
        ),
      );
    }

    final response = await ApiException.guard(
      () => dio.post<Object?>(Endpoints.items, data: form),
    );

    return Item.fromJson(dataMap(response.data));
  }
}
