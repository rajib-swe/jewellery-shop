import 'package:dio/dio.dart';

import '../../services/token_storage.dart';
import 'auth_interceptor.dart';

Dio buildDio({
  required String baseUrl,
  required TokenStorage tokenStorage,
  required UnauthorizedHandler onUnauthorized,
  HttpClientAdapter? adapter,
}) {
  final dio = Dio(
    BaseOptions(
      baseUrl: baseUrl,
      headers: const {'Accept': 'application/json'},
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 30),
      sendTimeout: const Duration(seconds: 30),
      responseType: ResponseType.json,
    ),
  );

  dio.interceptors.add(
    AuthInterceptor(
      tokenStorage: tokenStorage,
      onUnauthorized: onUnauthorized,
    ),
  );

  if (adapter != null) {
    dio.httpClientAdapter = adapter;
  }

  return dio;
}