import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../../domain/errors/api_error.dart';
import '../../domain/models/tv_agent.dart';

/// Klien HTTP untuk agen TV.
///
/// **Sementara (DEC-015).** Di Tahap 2 perintah ke TV datang dari Laravel
/// lewat Reverb, dan kelas ini hilang. Yang tidak hilang adalah bentuk
/// datanya: agen memakai envelope error yang sama dengan kontrak §2, jadi
/// penanganan error di UI tidak berubah.
class TvAgentClient {
  TvAgentClient({http.Client? httpClient})
      : _http = httpClient ?? http.Client();

  final http.Client _http;

  /// Timeout pendek: ini LAN. Kalau TV tidak menjawab dalam 2 detik saat
  /// dipindai, kemungkinan besar bukan agen — dan pemindaian 254 alamat
  /// tidak boleh menunggu lama.
  static const scanTimeout = Duration(milliseconds: 1200);

  /// Perintah boleh menunggu lebih lama: operator sedang menunggu hasilnya,
  /// dan gagal karena timeout terlalu pendek lebih buruk daripada menunggu.
  static const commandTimeout = Duration(seconds: 6);

  void close() => _http.close();

  // ── Pembacaan ─────────────────────────────────────────────────────

  /// `GET /health`. Tanpa auth — dipakai untuk menemukan TV.
  Future<TvAgentInfo> health(
    String baseUrl, {
    Duration timeout = commandTimeout,
  }) async {
    final json = await _send(
      method: 'GET',
      baseUrl: baseUrl,
      path: '/health',
      timeout: timeout,
    );
    return TvAgentInfo.fromJson(_normalize(baseUrl), json);
  }

  /// `GET /state` — apa yang sedang ditampilkan TV.
  Future<Map<String, dynamic>> state(TvLink link) => _send(
        method: 'GET',
        baseUrl: link.baseUrl,
        path: '/state',
        token: link.deviceToken,
      );

  // ── Pairing ───────────────────────────────────────────────────────

  /// Tukar kode yang tampil di layar TV dengan device token.
  ///
  /// [stationCode] menjadi identitas station pada agen. Setelah ini, agen
  /// **menolak** perintah untuk station lain — itu yang mencegah perintah
  /// salah sasaran mengubah label TV diam-diam.
  Future<TvLink> pair({
    required String baseUrl,
    required String code,
    required String stationId,
    required String stationCode,
  }) async {
    final json = await _send(
      method: 'POST',
      baseUrl: baseUrl,
      path: '/pair',
      body: {
        'code': code,
        'station_code': stationCode,
        'sender_time': DateTime.now().millisecondsSinceEpoch,
      },
    );

    final token = json['device_token'] as String?;
    if (token == null || token.isEmpty) {
      throw const ApiError(
        code: ApiErrorCode.unexpected,
        message: 'Agen TV tidak mengirim token.',
      );
    }

    return TvLink(
      stationId: stationId,
      stationCode: stationCode,
      deviceUid: json['device_uid'] as String? ?? '?',
      deviceToken: token,
      baseUrl: _normalize(baseUrl),
      pairedAt: DateTime.now().toUtc(),
    );
  }

  /// Cabut pairing di sisi TV. TV kembali menampilkan kode.
  Future<void> unpair(TvLink link) => _send(
        method: 'POST',
        baseUrl: link.baseUrl,
        path: '/unpair',
        token: link.deviceToken,
      );

  // ── Kontrol sesi ──────────────────────────────────────────────────

  /// Kirim keadaan sesi ke TV.
  ///
  /// Dipakai untuk memulai **dan** memperbarui — agen menerapkan `end_at`
  /// terbaru apa pun keadaan sebelumnya, jadi extend memakai jalur yang sama.
  /// Itu sengaja: satu jalur berarti satu tempat yang bisa salah.
  ///
  /// [senderTime] dikirim agar TV mengoreksi jamnya sendiri (DEC-003). Jam TV
  /// sering salah setelah boot sebelum NTP jalan.
  Future<void> pushSession(
    TvLink link, {
    required String sessionId,
    required DateTime? startedAt,
    required DateTime? endAt,
    required bool pendingPayment,
    String? customerLabel,
  }) =>
      _send(
        method: 'POST',
        baseUrl: link.baseUrl,
        path: '/session',
        token: link.deviceToken,
        body: {
          'session_id': sessionId,
          'station_code': link.stationCode,
          'customer_label': customerLabel,
          'started_at': startedAt?.millisecondsSinceEpoch,
          'end_at': endAt?.millisecondsSinceEpoch,
          'pending_payment': pendingPayment,
          'sender_time': DateTime.now().millisecondsSinceEpoch,
        },
      );

  /// Akhiri sesi — TV kembali ke layar idle.
  Future<void> endSession(TvLink link) => _send(
        method: 'DELETE',
        baseUrl: link.baseUrl,
        path: '/session',
        token: link.deviceToken,
        body: {'sender_time': DateTime.now().millisecondsSinceEpoch},
      );

  /// Sinkronkan jam TV tanpa mengubah sesi. Dipakai untuk uji jangkauan.
  Future<Map<String, dynamic>> ping(TvLink link) => _send(
        method: 'GET',
        baseUrl: link.baseUrl,
        path: '/state',
        token: link.deviceToken,
      );

  // ── Internal ──────────────────────────────────────────────────────

  Future<Map<String, dynamic>> _send({
    required String method,
    required String baseUrl,
    required String path,
    String? token,
    Map<String, dynamic>? body,
    Duration timeout = commandTimeout,
  }) async {
    final uri = Uri.parse('${_normalize(baseUrl)}$path');
    final headers = <String, String>{
      'Accept': 'application/json',
      if (body != null) 'Content-Type': 'application/json',
      if (token != null) 'X-Agent-Token': token,
    };

    http.Response response;
    try {
      final request = http.Request(method, uri)..headers.addAll(headers);
      if (body != null) {
        // Field null dibuang, bukan dikirim sebagai null: agen
        // memperlakukan field yang tidak ada sebagai "jangan ubah".
        request.body = jsonEncode(
          body..removeWhere((_, v) => v == null),
        );
      }

      final streamed = await _http.send(request).timeout(timeout);
      response = await http.Response.fromStream(streamed);
    } on TimeoutException {
      throw const ApiError.timeout();
    } catch (_) {
      throw const ApiError.network();
    }

    final decoded = _decode(response.body);

    if (response.statusCode >= 400) {
      // Agen memakai envelope error kontrak §2, jadi bisa diurai langsung.
      if (decoded.containsKey('error')) {
        throw ApiError.fromJson(decoded, httpStatus: response.statusCode);
      }
      throw ApiError(
        code: ApiErrorCode.unexpected,
        message: 'Agen TV menolak permintaan (${response.statusCode}).',
        httpStatus: response.statusCode,
      );
    }

    // Agen membalas langsung tanpa pembungkus `data`, berbeda dari Laravel.
    // Dibiarkan begitu: menambah pembungkus hanya untuk keseragaman berarti
    // menulis kode yang akan dihapus saat kelas ini hilang.
    return decoded;
  }

  Map<String, dynamic> _decode(String raw) {
    if (raw.isEmpty) return const {};
    try {
      final v = jsonDecode(raw);
      return v is Map<String, dynamic> ? v : const {};
    } catch (_) {
      return const {};
    }
  }

  /// `192.168.0.77` → `http://192.168.0.77:8787`
  static String _normalize(String raw) {
    var v = raw.trim();
    if (v.isEmpty) return v;
    if (!v.startsWith('http://') && !v.startsWith('https://')) {
      v = 'http://$v';
    }
    while (v.endsWith('/')) {
      v = v.substring(0, v.length - 1);
    }
    final uri = Uri.tryParse(v);
    if (uri != null && !uri.hasPort) v = '$v:$defaultPort';
    return v;
  }

  /// Dipakai juga oleh pemindai dan layar entri manual.
  static String normalizeBaseUrl(String raw) => _normalize(raw);

  static const defaultPort = 8787;
}
