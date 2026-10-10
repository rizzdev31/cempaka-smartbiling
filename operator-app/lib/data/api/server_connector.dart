import '../../core/config/api_config.dart';
import 'server_discovery.dart';

/// Hasil percobaan menyambung.
enum ConnectOutcome {
  /// Alamat yang tersimpan masih benar. Tidak ada pemindaian.
  remembered,

  /// Alamat lama tidak menjawab; server ditemukan di alamat baru dan
  /// alamat itu sudah disimpan.
  rediscovered,

  /// Tidak ada server billing di jaringan ini. Alamat lama **tidak**
  /// dihapus — lihat [ServerConnector.connect].
  notFound,
}

class ConnectResult {
  const ConnectResult(this.outcome, [this.server]);

  final ConnectOutcome outcome;
  final DiscoveredServer? server;

  bool get connected => outcome != ConnectOutcome.notFound;

  /// Alamat berganti — pemanggil perlu membangun ulang koneksi WebSocket.
  bool get addressChanged => outcome == ConnectOutcome.rediscovered;
}

/// Menyambung ke server tanpa operator mengetik IP — DEC-041.
///
/// Urutannya: coba yang diingat dulu, baru memindai.
///
/// Urutan itu bukan sekadar optimasi. Pemindaian memakan 2–5 detik dan
/// membuka ratusan soket; melakukannya setiap buka aplikasi akan terasa
/// lambat setiap hari untuk masalah yang muncul sebulan sekali.
class ServerConnector {
  ServerConnector({ServerDiscovery? discovery, ApiConfig? config})
      : _discovery = discovery ?? ServerDiscovery(),
        _config = config ?? ApiConfig.instance;

  final ServerDiscovery _discovery;
  final ApiConfig _config;

  /// Reverb selalu di port terpisah dari HTTP, dan selalu di host yang sama
  /// dengan Laravel karena keduanya satu laptop (Tahap 0/1).
  static const wsPort = 8080;

  Future<ConnectResult> connect() async {
    final saved = await _discovery.probe(_config.baseUrl);
    if (saved != null) {
      return ConnectResult(ConnectOutcome.remembered, saved);
    }

    final found = await _discovery.discover();
    if (found == null) {
      /*
       * Dua sebab yang tidak bisa dibedakan dari sini: server pindah alamat,
       * atau server memang sedang mati. Alamat lama sengaja DIPERTAHANKAN —
       * kalau dihapus, mematikan laptop sebentar akan membuat setiap tablet
       * lupa alamatnya dan harus diisi ulang manual saat laptop hidup lagi.
       */
      return const ConnectResult(ConnectOutcome.notFound);
    }

    await remember(found);
    return ConnectResult(ConnectOutcome.rediscovered, found);
  }

  /// Simpan alamat temuan. Dipakai juga saat teknisi memilih sendiri dari
  /// daftar hasil pemindaian di layar Pengaturan.
  Future<void> remember(DiscoveredServer server) {
    // `wsHost` ikut diubah. Kalau tidak, API pindah tapi realtime tetap
    // menunjuk IP lama — tablet bisa memuat data namun diam total.
    return _config.update(
      baseUrl: server.baseUrl,
      wsHost: server.host,
      wsPort: wsPort,
    );
  }

  /// Semua server di jaringan — untuk daftar pilihan di Pengaturan.
  Future<List<DiscoveredServer>> scan() => _discovery.discoverAll();

  void close() => _discovery.close();
}
