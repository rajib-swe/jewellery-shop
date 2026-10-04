import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:goldapp_mobile/core/api/api_exception.dart';
import 'package:goldapp_mobile/services/auth_service.dart';
import 'package:goldapp_mobile/services/token_storage.dart';

import '../support/fake_http_client_adapter.dart';

const _userJson = <String, dynamic>{
  'id': 7,
  'name': 'Rezaul Karim',
  'email': 'karim@example.com',
  'roles': ['manager'],
  'permissions': ['access api', 'manage gold rates'],
};

void main() {
  late TokenStorage tokenStorage;

  AuthService buildService(
    FakeHttpClientAdapter adapter, {
    String baseUrl = 'https://shop.test/api/v1',
  }) {
    tokenStorage = createTokenStorage();

    final dio = Dio(BaseOptions(baseUrl: baseUrl))
      ..httpClientAdapter = adapter;

    return AuthService(dio: dio, tokenStorage: tokenStorage);
  }

  group('login', () {
    test('stores the bearer token and parses the user', () async {
      final service = buildService(
        FakeHttpClientAdapter(
          (_) async => FakeHttpClientAdapter.json({
            'data': _userJson,
            'meta': {'message': 'Authenticated.'},
            'token': '1|abcdefghijklmnop',
          }),
        ),
      );

      final session = await service.login(
        email: 'karim@example.com',
        password: 'secret-password',
      );

      expect(session.token, '1|abcdefghijklmnop');
      expect(session.user.id, 7);
      expect(session.user.name, 'Rezaul Karim');
      expect(session.user.primaryRole, 'manager');
      expect(session.user.can('manage gold rates'), isTrue);
      expect(session.user.can('void sales'), isFalse);
      expect(session.user.isAdminOrManager, isTrue);
      expect(await tokenStorage.read(), '1|abcdefghijklmnop');
    });

    test('posts the credentials to the login endpoint', () async {
      final adapter = FakeHttpClientAdapter(
        (_) async => FakeHttpClientAdapter.json({
          'data': _userJson,
          'token': 'token-value',
        }),
      );
      final service = buildService(adapter);

      await service.login(email: 'karim@example.com', password: 'secret-password');

      final request = adapter.requests.single;
      expect(request.method, 'POST');
      expect(request.path, '/login');
      expect(request.data, {
        'email': 'karim@example.com',
        'password': 'secret-password',
        'remember': false,
      });
    });

    test('fails when the server returns no token', () async {
      final service = buildService(
        FakeHttpClientAdapter(
          (_) async => FakeHttpClientAdapter.json({
            'data': _userJson,
            'meta': {'message': 'Authenticated.'},
          }),
        ),
      );

      await expectLater(
        service.login(email: 'karim@example.com', password: 'secret-password'),
        throwsA(isA<ApiException>()),
      );
      expect(await tokenStorage.read(), isNull);
    });

    test('surfaces the email validation message', () async {
      final service = buildService(
        FakeHttpClientAdapter(
          (_) async => FakeHttpClientAdapter.json(
            {
              'message': 'The email field is required.',
              'errors': {'email': ['These credentials do not match our records.']},
            },
            statusCode: 422,
          ),
        ),
      );

      try {
        await service.login(email: 'karim@example.com', password: 'wrong');
        fail('expected an ApiException');
      } on ApiException catch (error) {
        expect(error.isValidation, isTrue);
        expect(
          error.fieldError('email'),
          'These credentials do not match our records.',
        );
      }
    });

    test('reports an offline failure as such', () async {
      final service = buildService(
        FakeHttpClientAdapter((_) async {
          throw DioException.connectionError(
            reason: 'connection refused',
            requestOptions: RequestOptions(path: '/login'),
          );
        }),
      );

      try {
        await service.login(email: 'karim@example.com', password: 'secret');
        fail('expected an ApiException');
      } on ApiException catch (error) {
        expect(error.isOffline, isTrue);
        expect(error.message, contains('No connection'));
      }
    });
  });

  group('me', () {
    test('parses the signed in user', () async {
      final service = buildService(
        FakeHttpClientAdapter(
          (_) async => FakeHttpClientAdapter.json({'data': _userJson}),
        ),
      );

      final user = await service.me();

      expect(user.id, 7);
      expect(user.roles, ['manager']);
      expect(user.permissions, contains('access api'));
    });
  });

  group('logout', () {
    test('posts to the logout endpoint', () async {
      final adapter = FakeHttpClientAdapter(
        (_) async => FakeHttpClientAdapter.json({'message': 'Logged out.'}),
      );
      final service = buildService(adapter);

      await service.logout();

      expect(adapter.requests.single.method, 'POST');
      expect(adapter.requests.single.path, '/logout');
    });

    test('does not raise when the token was already revoked', () async {
      final service = buildService(
        FakeHttpClientAdapter(
          (_) async => FakeHttpClientAdapter.json(
            {'message': 'Unauthenticated.'},
            statusCode: 401,
          ),
        ),
      );

      await expectLater(service.logout(), completes);
    });

    test('raises other server failures', () async {
      final service = buildService(
        FakeHttpClientAdapter(
          (_) async => FakeHttpClientAdapter.json(
            {'message': 'Server error.'},
            statusCode: 500,
          ),
        ),
      );

      await expectLater(service.logout(), throwsA(isA<ApiException>()));
    });
  });

  group('token storage', () {
    test('clears the stored token', () async {
      final service = buildService(
        FakeHttpClientAdapter(
          (_) async => FakeHttpClientAdapter.json({
            'data': _userJson,
            'token': 'token-value',
          }),
        ),
      );

      await service.login(email: 'karim@example.com', password: 'secret');
      await service.forgetToken();

      expect(await service.storedToken(), isNull);
    });
  });
}