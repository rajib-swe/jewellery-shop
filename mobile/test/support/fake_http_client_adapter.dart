import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:goldapp_mobile/services/token_storage.dart';

class FakeHttpClientAdapter implements HttpClientAdapter {
  FakeHttpClientAdapter(this.responder);

  final Future<ResponseBody> Function(RequestOptions options) responder;

  final List<RequestOptions> requests = [];

  static ResponseBody json(Object? body, {int statusCode = 200}) =>
      ResponseBody.fromString(
        jsonEncode(body),
        statusCode,
        headers: {
          Headers.contentTypeHeader: [Headers.jsonContentType],
        },
      );

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) {
    requests.add(options);
    return responder(options);
  }

  @override
  void close({bool force = false}) {}
}

TokenStorage createTokenStorage([Map<String, String> initial = const {}]) {
  FlutterSecureStorage.setMockInitialValues(<String, String>{...initial});
  return TokenStorage();
}