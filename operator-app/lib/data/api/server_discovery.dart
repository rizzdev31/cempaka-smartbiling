import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:http/http.dart' as http;

/// Server billing yang ditemukan di jaringan.
class DiscoveredServer {
  const DiscoveredServer({
    required this.baseUrl,
    required this.instance,
    required this.version,
  });

  /// `http://192.168.100.11:8000` — siap dipakai [ApiConfig].
  final String baseUrl;

  /// Nama rental dari `APP_NAME`. Ditampilkan ke teknisi supaya dia memilih
  /// server dari namanya, bukan dari deretan IP.
  final String instance;

  final String version;

  String get host => Uri.parse(baseUrl).host;

  @override
  String toString() => '$instance ($baseUrl)';
}

/// Menemukan server billing di jaringan lokal — OD-011.
///
/// ## Masalah yang diselesaikan
///
/// IP laptop server diberikan router lewat DHCP. Begitu laptop menyambung
/// ulang ke WiFi, IP-nya bisa berganti — dan semua tablet serta TV putus,
/// padahal tidak ada yang rusak. Tanpa penemuan otomatis, teknisi mengetik
/// ulang IP di setiap perangkat, di tengah jam operasional.
///
/// ## Cara kerjanya
///
/// Mengambil IP perangkat sendiri, lalu memindai seluruh subnet-nya mencari
/// alamat yang menjawab `GET /api/v1/health` dengan `app` yang cocok.
///
/// Pemeriksaan `app` itu yang penting: tanpanya, scanner akan menerima
/// layanan apa pun yang kebetulan hidup di port 8000 — printer, router,
/// aplikasi lain — dan tablet menyambung ke tempat yang salah.
///
/// ## Yang sengaja tidak dilakukan
///
/// Tidak memakai mDNS/Bonjour. Lebih rapi secara teori, tapi butuh responder
/// di sisi Laravel yang tidak ada bawaannya di Windows, dan banyak router
/// rumahan memblokir multicast. Pemindaian langsung lebih kasar tapi bekerja
/// di jaringan apa pun tanpa tambahan apa pun di server.
class ServerDiscovery {
  ServerDiscovery({
    http.Client? httpClient,
    Future<List<String>> Function()? localAddresses,
  })  : _http = httpClient ?? http.Client(),
        _localAddresses = localAddresses ?? _systemAddresses;

  final http.Client _http;
  final Future<List<String>> Function() _localAddresses;

  /// Harus sama dengan `HealthController::APP_ID` di Laravel.
  static const appId = 'cempaka-smart-billing';

  /// Port yang dicoba — hanya 8000, default `php artisan serve`.
  ///
  /// Port 80 sengaja TIDAK dipindai. Dua alasan: ia tidak akan dipakai di
  /// jaringan lokal (Nginx baru muncul di Tahap 3A, di VPS, dengan nama
  /// domain yang diketik manual), dan ia justru port yang paling sering
  /// dijawab router, printer, serta kamera — menambahnya hanya
  /// menggandakan waktu pindai sambil menambah kandidat palsu.
  static const ports = [8000];

  /// Per alamat. Pendek karena di LAN server yang hidup menjawab dalam
  /// puluhan milidetik — sisanya hanya menunda hasil tanpa menambah temuan.
  static const probeTimeout = Duration(milliseconds: 600);

  /// Berapa alamat diperiksa bersamaan. Android membatasi jumlah soket
  /// terbuka; membuka 254 sekaligus justru membuat sebagian gagal dan
  /// server yang hidup ikut terlewat.
  static const batchSize = 32;

  /// Periksa satu alamat. Dipakai juga untuk memastikan alamat tersimpan
  /// masih hidup sebelum repot memindai.
  Future<DiscoveredServer?> probe(String baseUrl) async {
    try {
      final response = await _http
          .get(
            Uri.parse('$baseUrl/api/v1/health'),
            headers: const {'Accept': 'application/json'},
          )
          .timeout(probeTimeout);

      if (response.statusCode != 200) return null;

      final body = jsonDecode(response.body);
      if (body is! Map) return null;

      final data = body['data'];
      if (data is! Map || data['app'] != appId) return null;

      return DiscoveredServer(
        baseUrl: baseUrl,
        instance: (data['instance'] as String?) ?? 'Server billing',
        version: (data['version'] as String?) ?? '-',
      );
    } catch (_) {
      // Tidak menjawab, bukan server kita, atau bukan JSON. Semuanya sama
      // saja di sini: bukan yang dicari.
      return null;
    }
  }

  /// Memindai subnet perangkat ini. Berhenti pada temuan pertama.
  Future<DiscoveredServer?> discover() async {
    final hasil = await discoverAll(stopAtFirst: true);
    return hasil.isEmpty ? null : hasil.first;
  }

  /// Memindai dan mengembalikan SEMUA yang ditemukan.
  ///
  /// Lebih dari satu berarti ada dua server hidup di jaringan yang sama —
  /// biasanya laptop cadangan yang lupa dimatikan. Teknisi harus memilih
  /// sendiri; menebak salah satunya berarti transaksi masuk ke database yang
  /// keliru, dan itu baru ketahuan saat laporan tidak cocok.
  Future<List<DiscoveredServer>> discoverAll({bool stopAtFirst = false}) async {
    final prefixes = await _subnetPrefixes();
    final temuan = <DiscoveredServer>[];

    for (final prefix in prefixes) {
      for (var start = 1; start <= 254; start += batchSize) {
        final batch = <Future<DiscoveredServer?>>[];

        for (var i = start; i < start + batchSize && i <= 254; i++) {
          for (final port in ports) {
            batch.add(probe('http://$prefix.$i:$port'));
          }
        }

        for (final server in await Future.wait(batch)) {
          if (server == null) continue;
          temuan.add(server);
          if (stopAtFirst) return temuan;
        }
      }
    }

    return temuan;
  }

  /// `192.168.100.11` -> `192.168.100`.
  Future<List<String>> _subnetPrefixes() async {
    final addresses = await _localAddresses();
    final prefixes = <String>{};

    for (final address in addresses) {
      final parts = address.split('.');
      if (parts.length == 4) {
        prefixes.add('${parts[0]}.${parts[1]}.${parts[2]}');
      }
    }

    return prefixes.toList(growable: false);
  }

  static Future<List<String>> _systemAddresses() async {
    try {
      final interfaces = await NetworkInterface.list(
        type: InternetAddressType.IPv4,
        includeLoopback: false,
        includeLinkLocal: false,
      );

      return [
        for (final i in interfaces)
          for (final a in i.addresses) a.address,
      ];
    } catch (_) {
      return const [];
    }
  }

  void close() => _http.close();
}
