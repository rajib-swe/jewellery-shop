import '../core/utils/parsing.dart';

class ItemCategory {
  const ItemCategory({
    required this.id,
    required this.name,
    required this.description,
  });

  final int id;
  final String name;
  final String? description;

  factory ItemCategory.fromJson(Map<String, dynamic> json) => ItemCategory(
    id: asInt(json['id']) ?? 0,
    name: asString(json['name']) ?? '',
    description: asString(json['description']),
  );
}

class Item {
  const Item({
    required this.id,
    required this.tagNo,
    required this.categoryId,
    required this.categoryName,
    required this.name,
    required this.karat,
    required this.grossWeight,
    required this.stoneWeight,
    required this.netWeight,
    required this.makingType,
    required this.makingValue,
    required this.stonePrice,
    required this.status,
    required this.imageUrl,
    required this.barcode,
    required this.createdAt,
  });

  final int id;
  final String tagNo;
  final int categoryId;
  final String categoryName;
  final String name;
  final int karat;
  final double grossWeight;
  final double stoneWeight;
  final double netWeight;
  final String makingType;
  final double makingValue;
  final double stonePrice;
  final String status;
  final String? imageUrl;
  final String? barcode;
  final DateTime? createdAt;

  bool get isSellable => status == 'in_stock';

  String get statusLabel => switch (status) {
    'in_stock' => 'In stock',
    'sold' => 'Sold',
    'pawned' => 'Pawned',
    'scrap' => 'Scrap',
    _ => status,
  };

  String get makingTypeLabel => switch (makingType) {
    'per_gram' => 'Per gram',
    'percent' => 'Percent',
    'fixed' => 'Fixed',
    _ => makingType,
  };

  factory Item.fromJson(Map<String, dynamic> json) {
    final category = asJsonMap(json['category']);

    return Item(
      id: asInt(json['id']) ?? 0,
      tagNo: asString(json['tag_no']) ?? '',
      categoryId: asInt(category['id']) ?? 0,
      categoryName: asString(category['name']) ?? '',
      name: asString(json['name']) ?? '',
      karat: asInt(json['karat']) ?? 0,
      grossWeight: asDouble(json['gross_weight']) ?? 0,
      stoneWeight: asDouble(json['stone_weight']) ?? 0,
      netWeight: asDouble(json['net_weight']) ?? 0,
      makingType: asString(json['making_type']) ?? 'fixed',
      makingValue: asDouble(json['making_value']) ?? 0,
      stonePrice: asDouble(json['stone_price']) ?? 0,
      status: asString(json['status']) ?? '',
      imageUrl: asString(json['image_url']),
      barcode: asString(json['barcode']),
      createdAt: asDate(json['created_at']),
    );
  }
}

class ItemPage {
  const ItemPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  final List<Item> items;
  final int currentPage;
  final int lastPage;
  final int total;
}

class StockKaratSummary {
  const StockKaratSummary({
    required this.karat,
    required this.totalNetWeight,
    required this.itemCount,
  });

  final int karat;
  final double totalNetWeight;
  final int itemCount;

  factory StockKaratSummary.fromJson(Map<String, dynamic> json) =>
      StockKaratSummary(
        karat: asInt(json['karat']) ?? 0,
        totalNetWeight: asDouble(json['total_net_weight']) ?? 0,
        itemCount: asInt(json['item_count']) ?? 0,
      );
}

class StockSummary {
  const StockSummary({
    required this.totalItems,
    required this.totalNetWeight,
    required this.byKarat,
  });

  final int totalItems;
  final double totalNetWeight;
  final List<StockKaratSummary> byKarat;

  factory StockSummary.fromJson(Map<String, dynamic> json) => StockSummary(
    totalItems: asInt(json['total_items']) ?? 0,
    totalNetWeight: asDouble(json['total_net_weight']) ?? 0,
    byKarat: _list(json['by_karat'], StockKaratSummary.fromJson),
  );
}

List<T> _list<T>(Object? raw, T Function(Map<String, dynamic> json) build) {
  if (raw is! List) {
    return const [];
  }

  return raw.map((item) => build(asJsonMap(item))).toList(growable: false);
}
