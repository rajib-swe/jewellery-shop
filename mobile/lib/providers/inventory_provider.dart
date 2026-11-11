import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../services/document_service.dart';
import '../services/inventory_service.dart';
import 'auth_provider.dart';

final inventoryServiceProvider = Provider<InventoryService>((ref) {
  return InventoryService(dio: ref.watch(dioProvider));
});

final documentServiceProvider = Provider<DocumentService>((ref) {
  return DocumentService(
    dio: ref.watch(dioProvider),
    tokenStorage: ref.watch(tokenStorageProvider),
  );
});
