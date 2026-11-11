import 'package:dio/dio.dart';

class ApiException implements Exception {
  const ApiException({
    required this.message,
    this.statusCode,
    this.fieldErrors = const {},
    this.isUnauthorized = false,
    this.isForbidden = false,
    this.isValidation = false,
    this.isOffline = false,
  });

  final String message;
  final int? statusCode;
  final Map<String, List<String>> fieldErrors;
  final bool isUnauthorized;
  final bool isForbidden;
  final bool isValidation;
  final bool isOffline;

  factory ApiException.from(Object error) {
    if (error is ApiException) {
      return error;
    }

    if (error is! DioException) {
      return ApiException(message: 'Something went wrong. Please try again.');
    }

    final statusCode = error.response?.statusCode;
    final body = error.response?.data;
    final payload = body is Map<String, dynamic> ? body : null;

    return ApiException(
      message: _readMessage(payload) ?? _defaultMessage(statusCode, error.type),
      statusCode: statusCode,
      fieldErrors: _readFieldErrors(payload),
      isUnauthorized: statusCode == 401,
      isForbidden: statusCode == 403,
      isValidation: statusCode == 422,
      isOffline: error.response == null,
    );
  }

  String? fieldError(String field) {
    final errors = fieldErrors[field];
    return (errors == null || errors.isEmpty) ? null : errors.first;
  }

  @override
  String toString() => message;

  static Map<String, List<String>> _readFieldErrors(
    Map<String, dynamic>? payload,
  ) {
    final errors = payload?['errors'];

    if (errors is! Map) {
      return const {};
    }

    return {
      for (final entry in errors.entries)
        entry.key.toString(): [
          ...(entry.value is List
              ? (entry.value as List).map((value) => value.toString())
              : <String>[entry.value.toString()]),
        ],
    };
  }

  static String? _readMessage(Map<String, dynamic>? payload) {
    final message = payload?['message'];

    if (message is String && message.trim().isNotEmpty) {
      return message.trim();
    }

    if (message is Map) {
      return _readMessage(message.cast<String, dynamic>());
    }

    return null;
  }

  static Future<T> guard<T>(Future<T> Function() request) async {
    try {
      return await request();
    } on DioException catch (error) {
      throw ApiException.from(error);
    }
  }

  String get userMessage {
    if (isOffline) {
      return 'ইন্টারনেট সংযোগ নেই। নেটওয়ার্ক পরীক্ষা করে আবার চেষ্টা করুন।';
    }
    if (isUnauthorized) {
      return 'আপনার সেশনের মেয়াদ শেষ হয়েছে। আবার প্রবেশ করুন।';
    }
    if (isForbidden) {
      return 'আপনার এই কাজের অনুমতি নেই।';
    }
    if (statusCode == 404) {
      return 'তথ্যটি খুঁজে পাওয়া যায়নি।';
    }
    if (statusCode != null && statusCode! >= 500) {
      return 'সার্ভারে সমস্যা দেখা দিয়েছে। কিছুক্ষণ পর আবার চেষ্টা করুন।';
    }
    if (message == 'Something went wrong. Please try again.') {
      return 'কিছু সমস্যা হয়েছে। আবার চেষ্টা করুন।';
    }
    return message;
  }

  static String _defaultMessage(int? statusCode, DioExceptionType type) {
    if (statusCode == null) {
      return switch (type) {
        DioExceptionType.connectionTimeout ||
        DioExceptionType.receiveTimeout ||
        DioExceptionType.sendTimeout =>
          'The server took too long to respond. Please try again.',
        _ => 'No connection. Check your network and try again.',
      };
    }

    return switch (statusCode) {
      401 => 'Your session has expired. Please sign in again.',
      403 => 'You do not have permission to do that.',
      404 => 'That record could not be found.',
      >= 500 => 'The server had a problem. Please try again shortly.',
      _ => 'Something went wrong. Please try again.',
    };
  }
}
