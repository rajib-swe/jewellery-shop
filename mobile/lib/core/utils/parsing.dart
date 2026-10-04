double? asDouble(Object? raw) {
  if (raw == null) {
    return null;
  }

  if (raw is num) {
    return raw.toDouble();
  }

  return double.tryParse(raw.toString());
}

int? asInt(Object? raw) {
  if (raw == null) {
    return null;
  }

  if (raw is int) {
    return raw;
  }

  if (raw is num) {
    return raw.toInt();
  }

  return int.tryParse(raw.toString());
}

String? asString(Object? raw) {
  if (raw == null) {
    return null;
  }

  final value = raw is String ? raw : raw.toString();
  return value.isEmpty ? null : value;
}

List<String> asStringList(Object? raw) {
  if (raw is! List) {
    return const [];
  }

  return raw.map((value) => value.toString()).toList(growable: false);
}

Map<String, dynamic> asJsonMap(Object? raw) {
  if (raw is Map<String, dynamic>) {
    return raw;
  }

  return const {};
}

DateTime? asDate(Object? raw) {
  if (raw == null) {
    return null;
  }

  if (raw is DateTime) {
    return raw;
  }

  return DateTime.tryParse(raw.toString());
}