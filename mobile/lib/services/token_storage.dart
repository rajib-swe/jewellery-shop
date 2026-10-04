import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class TokenStorage {
  TokenStorage({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  static const String tokenKey = 'api_token';

  final FlutterSecureStorage _storage;

  String? _cached;

  Future<String?> read() async {
    final cached = _cached;

    if (cached != null) {
      return cached;
    }

    final stored = await _storage.read(key: tokenKey);

    return _cached = stored;
  }

  Future<void> write(String token) async {
    _cached = token;
    await _storage.write(key: tokenKey, value: token);
  }

  Future<void> clear() async {
    _cached = null;
    await _storage.delete(key: tokenKey);
  }
}