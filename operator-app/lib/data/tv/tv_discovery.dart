import 'dart:async';

import '../../domain/models/tv_agent.dart';
import 'local_ip.dart';
import 'tv_agent_client.dart';

/// Penemuan agen TV di jaringan lokal.
///
/// ## Kenapa pemindaian subnet, bukan mDNS
///
/// mDNS lebih rapi di atas kertas, tapi bergantung pada multicast — yang
/// sering di-drop access point murah, dan butuh WiFi multicast lock di
/// Android. Pemindaian subnet hanya memakai HTTP biasa: kalau operator bisa
/// membuka alamat TV di browser, pemindaian juga bisa menemukannya.
///
/// Analisis lengkapnya ada di OD-011.
///
/// ## Batasnya
///
/// Hanya menemukan TV di **subnet /24 yang sama**. Kalau operator dan TV
/// berbeda subnet, pemindaian tidak akan menemukan apa pun dan operator harus
/// memasukkan alamat manual. Itu bukan kegagalan yang disembunyikan — UI
/// selalu menyediakan entri manual.
class TvDiscovery {
  TvDiscovery(this._client);

  final TvAgentClient _client;

  /// Jumlah probe yang berjalan bersamaan.
  ///
  /// 32 dipilih setelah mempertimbangkan dua hal: lebih rendah membuat
  /// pemindaian 254 alamat terasa lama, lebih tinggi membuat tablet murah
  /// kehabisan socket dan justru melambat.
  static const concurrency = 32;

  bool get isSupported => supportsSubnetScan;

  /// Pindai subnet /24 milik perangkat ini.
  ///
  /// [onProgress] dipanggil dengan (selesai, total) supaya UI bisa
  /// menampilkan kemajuan — pemindaian 2–4 detik tanpa umpan balik terasa
  /// seperti aplikasi menggantung.
  Future<TvScanResult> scan({
    void Function(int done, int total)? onProgress,
    String? subnetOverride,
  }) async {
    if (!isSupported) {
      return const TvScanResult(
        agents: [],
        subnet: null,
        supported: false,
      );
    }

    final subnet = subnetOverride ?? await _detectSubnet();
    if (subnet == null) {
      return const TvScanResult(agents: [], subnet: null, supported: true);
    }

    final hosts = [for (var i = 1; i <= 254; i++) '$subnet.$i'];
    final found = <TvAgentInfo>[];
    var done = 0;

    for (var i = 0; i < hosts.length; i += concurrency) {
      final batch = hosts.skip(i).take(concurrency);

      await Future.wait(
        batch.map((host) async {
          final info = await _probe(host);
          if (info != null) found.add(info);
          done++;
          onProgress?.call(done, hosts.length);
        }),
      );
    }

    // Urut berdasarkan station supaya daftarnya stabil antar pemindaian;
    // yang belum dipasangkan di atas karena itu yang perlu ditindak.
    found.sort((a, b) {
      if (a.paired != b.paired) return a.paired ? 1 : -1;
      return (a.stationCode ?? '').compareTo(b.stationCode ?? '');
    });

    return TvScanResult(agents: found, subnet: subnet, supported: true);
  }

  /// Periksa satu alamat. Dipakai juga oleh entri manual.
  Future<TvAgentInfo?> probeAddress(String address) =>
      _probe(address, timeout: TvAgentClient.commandTimeout);

  Future<TvAgentInfo?> _probe(
    String host, {
    Duration timeout = TvAgentClient.scanTimeout,
  }) async {
    try {
      return await _client.health(host, timeout: timeout);
    } catch (_) {
      // Alamat kosong, perangkat lain, atau port tertutup. Tidak dicatat:
      // 253 dari 254 probe memang gagal, dan mencatatnya hanya membanjiri log.
      return null;
    }
  }

  Future<String?> _detectSubnet() async {
    final ip = await localIpv4();
    if (ip == null) return null;
    final parts = ip.split('.');
    if (parts.length != 4) return null;
    return '${parts[0]}.${parts[1]}.${parts[2]}';
  }
}

class TvScanResult {
  const TvScanResult({
    required this.agents,
    required this.subnet,
    required this.supported,
  });

  final List<TvAgentInfo> agents;

  /// `192.168.0` — ditampilkan supaya operator tahu subnet mana yang dipindai
  /// dan bisa menyadari kalau TV-nya ada di subnet lain.
  final String? subnet;

  /// `false` di web: browser tidak mengizinkan aplikasi membaca IP lokalnya.
  final bool supported;

  bool get isEmpty => agents.isEmpty;
}
