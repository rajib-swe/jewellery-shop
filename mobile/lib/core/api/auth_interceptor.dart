import 'package:dio/dio.dart';

import '../../services/token_storage.dart';
import 'endpoints.dart';

typedef UnauthorizedHandler = Future<void> Function();

class AuthInterceptor extends Interceptor {
  AuthInterceptor({
    required this.tokenStorage,
    required this.onUnauthorized,
  });

  static const Set<String> silentPaths = {Endpoints.login, Endpoints.me};

  final TokenStorage tokenStorage;
  final UnauthorizedHandler onUnauthorized;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final token = await tokenStorage.read();

    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }

    handler.next(options);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    if (err.response?.statusCode == 401 && !_isSilent(err.requestOptions.path)) {
      await onUnauthorized();
    }

    handler.next(err);
  }

  bool _isSilent(String path) =>
      silentPaths.any((silent) => path.endsWith(silent));
}