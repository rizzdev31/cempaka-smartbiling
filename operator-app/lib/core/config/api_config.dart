import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Konfigurasi endpoint server.
///
/// ATURAN (DEC-002, CLAUDE.md §4): base URL TIDAK BOLEH hardcode.
/// Nilainya datang dari `--dart-define` saat build, dan masih bisa diubah
/// operator lewat settings screen tanpa rebuild APK.
///
/// Tanpa settings screen, setiap salah IP di lokasi berarti rebuild APK
/// di tempat — pemborosan waktu terbesar saat testing lapangan.
///
/// Build dev:
/// ```
/// flutter run --dart-define=API_BASE_URL=http://192.168.0.50:8000 \
///             --dart-define=WS_HOST=192.168.0.50 \
///             --dart-define=WS_PORT=8080
/// ```
class ApiConfig extends ChangeNotifier {
  ApiConfig._();

  static final ApiConfig instance = ApiConfig._();

  static const _kBaseUrl = 'cfg.base_url';
  static const _kWsHost = 'cfg.ws_host';
  static const _kWsPort = 'cfg.ws_port';

  // ── Nilai dari build ──────────────────────────────────────────────
  static const _defaultBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000',
  );
  static const _defaultWsHost =
      String.fromEnvironment('WS_HOST', defaultValue: '10.0.2.2');
  static const _defaultWsPort =
      int.fromEnvironment('WS_PORT', defaultValue: 8080);

  // ── Nilai aktif (override dari settings) ──────────────────────────
  String _baseUrl = _defaultBaseUrl;
  String _wsHost = _defaultWsHost;
  int _wsPort = _defaultWsPort;

  /// `http://192.168.0.50:8000` — tanpa `/api/v1`.
  String get baseUrl => _baseUrl;

  /// Prefix API sesuai kontrak.
  String get apiBase => '$_baseUrl/api/v1';

  String get wsHost => _wsHost;
  int get wsPort => _wsPort;

  /// Flavor dev memperbolehkan cleartext HTTP dan menampilkan banner
  /// diagnostik. Release build: HTTPS-only, banner disembunyikan.
  ///
  /// Perbedaan cleartext ditegakkan Android lewat
  /// `android/app/src/debug/AndroidManifest.xml` — manifest itu hanya
  /// digabung pada build debug, jadi release TIDAK pernah mengizinkan HTTP.
  bool get isDev => kDebugMode;

  /// Reverb/Pusher: TLS hanya di prod.
  bool get forceTls => !isDev;

  bool get isDefault =>
      _baseUrl == _defaultBaseUrl &&
      _wsHost == _defaultWsHost &&
      _wsPort == _defaultWsPort;

  /// Dipanggil sekali di `main()` sebelum `runApp`.
  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    _baseUrl = prefs.getString(_kBaseUrl) ?? _defaultBaseUrl;
    _wsHost = prefs.getString(_kWsHost) ?? _defaultWsHost;
    _wsPort = prefs.getInt(_kWsPort) ?? _defaultWsPort;
    notifyListeners();
  }

  /// Simpan override dari settings screen.
  Future<void> update({
    String? baseUrl,
    String? wsHost,
    int? wsPort,
  }) async {
    final prefs = await SharedPreferences.getInstance();

    if (baseUrl != null) {
      _baseUrl = normalizeBaseUrl(baseUrl);
      await prefs.setString(_kBaseUrl, _baseUrl);
    }
    if (wsHost != null) {
      _wsHost = wsHost.trim();
      await prefs.setString(_kWsHost, _wsHost);
    }
    if (wsPort != null) {
      _wsPort = wsPort;
      await prefs.setInt(_kWsPort, _wsPort);
    }

    notifyListeners();
  }

  Future<void> resetToBuildDefaults() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kBaseUrl);
    await prefs.remove(_kWsHost);
    await prefs.remove(_kWsPort);
    _baseUrl = _defaultBaseUrl;
    _wsHost = _defaultWsHost;
    _wsPort = _defaultWsPort;
    notifyListeners();
  }

  /// Menolerir input operator yang buru-buru: `192.168.0.50`,
  /// `192.168.0.50:8000`, `http://192.168.0.50:8000/`.
  static String normalizeBaseUrl(String raw) {
    var v = raw.trim();
    if (v.isEmpty) return _defaultBaseUrl;
    if (!v.startsWith('http://') && !v.startsWith('https://')) {
      v = 'http://$v';
    }
    while (v.endsWith('/')) {
      v = v.substring(0, v.length - 1);
    }
    // Kalau hanya host tanpa port, pakai 8000 (default artisan serve).
    if (!_hasExplicitPort(v)) {
      v = '$v:8000';
    }
    return v;
  }

  /// `Uri.hasPort` tidak bisa dipakai di sini: Dart menganggap `:80` pada
  /// `http://` dan `:443` pada `https://` sebagai port default lalu
  /// menyembunyikannya, jadi `hasPort` bernilai `false`. Akibatnya
  /// `192.168.0.50:80` akan diubah menjadi `192.168.0.50:80:8000`.
  static bool _hasExplicitPort(String url) =>
      RegExp(r'^https?://[^/:]+:\d+').hasMatch(url);

  /// Validasi untuk settings screen. `null` = valid.
  static String? validateBaseUrl(String raw) {
    if (raw.trim().isEmpty) return 'Alamat server tidak boleh kosong.';
    final uri = Uri.tryParse(normalizeBaseUrl(raw));
    if (uri == null || uri.host.isEmpty) {
      return 'Alamat tidak valid. Contoh: 192.168.0.50:8000';
    }
    return null;
  }
}
