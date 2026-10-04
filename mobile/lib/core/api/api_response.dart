Object? unwrapData(Object? body) {
  if (body is Map<String, dynamic> && body.containsKey('data')) {
    return body['data'];
  }

  return body;
}

Map<String, dynamic> dataMap(Object? body) {
  final data = unwrapData(body);

  if (data is Map<String, dynamic>) {
    return data;
  }

  throw const FormatException('Expected an object in the API data payload.');
}

List<Object?> dataList(Object? body) {
  final data = unwrapData(body);

  if (data is List) {
    return data.cast<Object?>();
  }

  throw const FormatException('Expected a list in the API data payload.');
}

String? metaMessage(Object? body) {
  if (body is! Map<String, dynamic>) {
    return null;
  }

  final message = (body['meta'] as Map<String, dynamic>?)?['message'];
  return message is String ? message : null;
}