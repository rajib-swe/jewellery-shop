import 'dart:io';

import 'package:dio/dio.dart';
import 'package:share_plus/share_plus.dart';

import '../core/utils/format.dart';
import 'token_storage.dart';

/// The shop documents are rendered by web (not /api/v1) routes, which still
/// accept the same Sanctum bearer token because a native client sends no
/// Referer/Origin header.
class DocumentService {
  const DocumentService({required this.dio, required this.tokenStorage});

  final Dio dio;
  final TokenStorage tokenStorage;

  static String _origin(String apiBaseUrl) {
    final index = apiBaseUrl.indexOf('/api/v');

    if (index > 0) {
      return apiBaseUrl.substring(0, index);
    }

    return apiBaseUrl;
  }

  String webUrl(String apiBaseUrl, String webPath) =>
      '${_origin(apiBaseUrl)}$webPath';

  Future<void> downloadAndShare({
    required String apiBaseUrl,
    required String webPath,
    required String fileName,
  }) async {
    final token = await tokenStorage.read();

    if (token == null || token.isEmpty) {
      throw StateError('ডকুমেন্ট ডাউনলোড করতে আপনাকে প্রবেশ করা অবস্থায় থাকতে হবে।');
    }

    final response = await dio.get<List<int>>(
      webUrl(apiBaseUrl, webPath),
      options: Options(
        responseType: ResponseType.bytes,
        headers: {'Authorization': 'Bearer $token'},
      ),
    );

    final bytes = response.data;
    final file = File(
      '${Directory.systemTemp.path}/$fileName',
    );

    if (bytes == null || bytes.isEmpty) {
      throw StateError('সার্ভার থেকে কোনো ডকুমেন্ট পাওয়া যায়নি।');
    }

    await file.writeAsBytes(bytes);
    await SharePlus.instance.share(
      ShareParams(files: [XFile(file.path)], text: fileName),
    );
  }
}

String saleInvoiceFileName(int saleId, DateTime? date) =>
    'invoice-$saleId-${date == null ? '' : formatIsoDate(date)}.pdf';

String pawnTicketFileName(int pawnId, DateTime? date) =>
    'pawn-ticket-$pawnId-${date == null ? '' : formatIsoDate(date)}.pdf';
