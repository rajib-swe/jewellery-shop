import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:goldapp_mobile/services/token_storage.dart';

import '../support/fake_http_client_adapter.dart';

void main() {
  test('returns nothing before a token is written', () async {
    expect(await createTokenStorage().read(), isNull);
  });

  test('round trips a token', () async {
    final storage = createTokenStorage();

    await storage.write('1|abcdefghijklmnop');

    expect(await storage.read(), '1|abcdefghijklmnop');
  });

  test('survives a fresh instance, as after an app restart', () async {
    final sessionStorage = createTokenStorage();
    await sessionStorage.write('persisted-token');

    final restartedStorage = TokenStorage(
      storage: const FlutterSecureStorage(),
    );

    expect(await restartedStorage.read(), 'persisted-token');
  });

  test('clears the stored token', () async {
    final storage = createTokenStorage();
    await storage.write('persisted-token');

    await storage.clear();

    expect(await storage.read(), isNull);
  });
}