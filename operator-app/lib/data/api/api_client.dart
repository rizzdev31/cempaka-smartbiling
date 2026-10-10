import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

import '../../core/config/api_config.dart';
import '../../core/time/server_time.dart';
import '../../domain/errors/api_error.dart';

/// Lapisan HTTP ke Laravel — kontrak `docs/contracts/API.md`.
///
/// Semua yang berlaku untuk SETIAP request tinggal di sini, supaya tidak
/// perlu diingat ulang di 20 method repository:
///
/// - membuka amplop `{ data, meta }` dan `{ error, meta }` (§2)
/// - mengubah kegagalan jadi [ApiError] dengan `error.code` kontrak
/// - menyinkronkan server-time offset dari header `X-Server-Time` (DEC-003)
/// - menempelkan Bearer token dan `Idempotency-Key` (§3)
///
/// Base URL TIDAK pernah hardcode — selalu dari [ApiConfig] (DEC-002).
class ApiClient {
  ApiClient({http.Client? httpClient, ApiConfig? config})
      : _http = httpClient ?? http.Client(),
        _config = config ?? ApiConfig.instance;

  final http.Client _http;
  final ApiConfig _config;

  static const _kToken = 'auth.token';

  /// Jaringan lokal boleh lambat, tapi operator tidak boleh menunggu tanpa
  /// kabar. Lewat ini dianggap gagal dan UI bisa menawarkan coba lagi.
  static const timeout = Duration(seconds: 15);

  String? _token;

  String? get token => _token;

  bool get isAuthenticated => _token != null;

  /// Dipanggil sekali di `main()`, sesudah [ApiConfig.load].
  Future<void> loadToken() async {
    final prefs = await SharedPreferences.getInstance();
    _token = prefs.getString(_kToken);
  }

  Future<void> saveToken(String token) async {
    _token = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kToken, token);
  }

  Future<void> clearToken() async {
    _token = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kToken);
  }

  // ── Kata kerja HTTP ───────────────────────────────────────────────

  Future<dynamic> get(String path, {Map<String, String>? query}) =>
      _send('GET', path, query: query);

  /// [idempotencyKey] wajib untuk POST yang MEMBUAT data (§3). Key dibuat
  /// sekali per niat aksi dan dipakai ulang saat retry — bukan key baru
  /// setiap percobaan, karena justru itu yang membuat data dobel.
  Future<dynamic> post(String path, {Object? body, String? idempotencyKey}) =>
      _send('POST', path, body: body, idempotencyKey: idempotencyKey);

  Future<dynamic> patch(String path, {Object? body}) =>
      _send('PATCH', path, body: body);

  /// Seperti [get], tapi juga mengembalikan `meta` — dipakai endpoint yang
  /// menaruh aturan di sana, mis. `offline_threshold_seconds`.
  Future<({dynamic data, Map<String, dynamic> meta})> getWithMeta(
    String path, {
    Map<String, String>? query,
  }) async {
    final envelope = await _sendRaw('GET', path, query: query);
    return (
      data: envelope['data'],
      meta: (envelope['meta'] as Map<String, dynamic>?) ?? const {},
    );
  }

  Future<dynamic> _send(
    String method,
    String path, {
    Object? body,
    Map<String, String>? query,
    String? idempotencyKey,
  }) async {
    final envelope = await _sendRaw(
      method,
      path,
      body: body,
      query: query,
      idempotencyKey: idempotencyKey,
    );
    return envelope['data'];
  }

  Future<Map<String, dynamic>> _sendRaw(
    String method,
    String path, {
    Object? body,
    Map<String, String>? query,
    String? idempotencyKey,
  }) async {
    final uri = Uri.parse('${_config.apiBase}$path').replace(
      queryParameters: (query == null || query.isEmpty) ? null : query,
    );

    final request = http.Request(method, uri)
      ..headers.addAll({
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        if (_token != null) 'Authorization': 'Bearer $_token',
        if (idempotencyKey != null) 'Idempotency-Key': idempotencyKey,
      });

    if (body != null) {
      request.body = jsonEncode(body);
    }

    final stopwatch = Stopwatch()..start();
    late http.Response response;

    try {
      final streamed = await _http.send(request).timeout(timeout);
      response = await http.Response.fromStream(streamed);
    } on TimeoutException {
      throw const ApiError(
        code: ApiErrorCode.timeout,
        message: 'Server tidak menjawab. Periksa sambungan ke server.',
      );
    } catch (_) {
      /*
       * Kegagalan sebelum response sampai: WiFi putus, IP salah, server
       * mati. Dibedakan dari error server supaya UI bisa menyarankan hal
       * yang benar — memeriksa jaringan, bukan menghubungi admin.
       */
      throw const ApiError(
        code: ApiErrorCode.networkUnreachable,
        message: 'Tidak bisa menghubungi server. Periksa WiFi dan alamat server.',
      );
    } finally {
      stopwatch.stop();
    }

    _syncServerTime(response, stopwatch.elapsed);

    final decoded = _decode(response);

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return decoded;
    }

    throw ApiError.fromJson(decoded, httpStatus: response.statusCode);
  }

  Map<String, dynamic> _decode(http.Response response) {
    if (response.body.isEmpty) {
      return const {};
    }

    try {
      final parsed = jsonDecode(response.body);
      return parsed is Map<String, dynamic> ? parsed : {'data': parsed};
    } catch (_) {
      /*
       * Bukan JSON. Biasanya halaman error HTML Laravel saat debug, atau
       * portal WiFi yang menyela request. Pesannya dibuat mengarahkan ke
       * sana, bukan menampilkan HTML mentah ke operator.
       */
      throw ApiError(
        code: ApiErrorCode.unexpected,
        message: 'Jawaban server tidak dikenali. Pastikan alamatnya menunjuk '
            'ke server billing, bukan halaman lain.',
        httpStatus: response.statusCode,
      );
    }
  }

  /// DEC-003 — offset disegarkan dari SETIAP response, termasuk yang gagal.
  ///
  /// Header dipasang middleware global justru supaya 404 dan 422 pun
  /// membawanya: client yang kehilangan offset ikut salah menampilkan timer.
  void _syncServerTime(http.Response response, Duration roundTrip) {
    final header = response.headers['x-server-time'];
    if (header == null) return;

    final parsed = DateTime.tryParse(header);
    if (parsed == null) return;

    ServerTime.instance.sync(parsed, roundTripTime: roundTrip);
  }

  void close() => _http.close();
}
