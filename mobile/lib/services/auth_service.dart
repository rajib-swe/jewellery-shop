import 'package:dio/dio.dart';

import '../core/api/api_exception.dart';
import '../core/api/api_response.dart';
import '../core/api/endpoints.dart';
import '../core/utils/parsing.dart';
import '../models/user.dart';
import 'token_storage.dart';

class AuthSession {
  const AuthSession({required this.user, required this.token});

  final User user;
  final String token;
}

class AuthService {
  const AuthService({required this.dio, required this.tokenStorage});

  final Dio dio;
  final TokenStorage tokenStorage;

  Future<AuthSession> login({
    required String email,
    required String password,
    bool remember = false,
  }) async {
    final response = await _guard(
      () => dio.post<Object?>(
        Endpoints.login,
        data: {'email': email, 'password': password, 'remember': remember},
      ),
    );

    final body = response.data;
    final token = asString(asJsonMap(body)['token']);

    if (token == null) {
      throw const ApiException(
        message:
            'Login did not return an access token. Confirm the server builds '
            'one for token clients.',
      );
    }

    final user = User.fromJson(dataMap(body));
    await tokenStorage.write(token);

    return AuthSession(user: user, token: token);
  }

  Future<User> me() async {
    final response = await _guard(() => dio.get<Object?>(Endpoints.me));

    return User.fromJson(dataMap(response.data));
  }

  Future<String?> storedToken() => tokenStorage.read();

  Future<void> storeToken(String token) => tokenStorage.write(token);

  Future<void> forgetToken() => tokenStorage.clear();

  Future<void> logout() async {
    try {
      await _guard(() => dio.post<Object?>(Endpoints.logout));
    } on ApiException catch (error) {
      if (!error.isUnauthorized) {
        rethrow;
      }
    }
  }

  Future<Response<T>> _guard<T>(Future<Response<T>> Function() request) async {
    try {
      return await request();
    } on DioException catch (error) {
      throw ApiException.from(error);
    }
  }
}