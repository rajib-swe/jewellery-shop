import 'package:flutter_test/flutter_test.dart';
import 'package:goldapp_mobile/core/api/dio_client.dart';
import 'package:goldapp_mobile/services/token_storage.dart';

import '../../support/fake_http_client_adapter.dart';

void main() {
  const baseUrl = 'https://shop.test/api/v1';

  test('attaches the stored bearer token to every request', () async {
    final adapter = FakeHttpClientAdapter(
      (_) async => FakeHttpClientAdapter.json({'data': {}}),
    );
    final dio = buildDio(
      baseUrl: baseUrl,
      tokenStorage: createTokenStorage({
        TokenStorage.tokenKey: 'stored-token',
      }),
      onUnauthorized: () async {},
      adapter: adapter,
    );

    await dio.get<void>('/customers');

    expect(adapter.requests.single.headers['Authorization'], 'Bearer stored-token');
  });

  test('omits the header when no token is stored', () async {
    final adapter = FakeHttpClientAdapter(
      (_) async => FakeHttpClientAdapter.json({'data': {}}),
    );
    final dio = buildDio(
      baseUrl: baseUrl,
      tokenStorage: createTokenStorage(),
      onUnauthorized: () async {},
      adapter: adapter,
    );

    await dio.get<void>('/customers');

    expect(adapter.requests.single.headers.containsKey('Authorization'), isFalse);
  });

  test('reports an unauthorized failure once', () async {
    var unauthorizedCalls = 0;
    final adapter = FakeHttpClientAdapter(
      (_) async => FakeHttpClientAdapter.json(
        {'message': 'Unauthenticated.'},
        statusCode: 401,
      ),
    );
    final dio = buildDio(
      baseUrl: baseUrl,
      tokenStorage: createTokenStorage(),
      onUnauthorized: () async => unauthorizedCalls++,
      adapter: adapter,
    );

    await expectLater(
      dio.get<void>('/customers'),
      throwsA(isA<Exception>()),
    );
    expect(unauthorizedCalls, 1);
  });

  test('stays quiet for the login and me endpoints', () async {
    var unauthorizedCalls = 0;
    final adapter = FakeHttpClientAdapter(
      (_) async => FakeHttpClientAdapter.json(
        {'message': 'Unauthenticated.'},
        statusCode: 401,
      ),
    );
    final dio = buildDio(
      baseUrl: baseUrl,
      tokenStorage: createTokenStorage(),
      onUnauthorized: () async => unauthorizedCalls++,
      adapter: adapter,
    );

    for (final path in ['/login', '/me']) {
      await expectLater(
        dio.post<void>(path),
        throwsA(isA<Exception>()),
      );
    }

    expect(unauthorizedCalls, 0);
  });
}