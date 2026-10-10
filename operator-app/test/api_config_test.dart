import 'package:flutter_test/flutter_test.dart';
import 'package:operator_app/core/config/api_config.dart';

/// Alamat server yang diketik operator — DEC-002.
///
/// Layar Pengaturan ada supaya salah IP tidak berarti rebuild APK di lokasi.
/// Itu hanya berguna kalau apa yang diketik buru-buru tetap menghasilkan
/// alamat yang benar.
void main() {
  group('normalizeBaseUrl', () {
    test('menambahkan skema dan port default', () {
      expect(
        ApiConfig.normalizeBaseUrl('192.168.0.50'),
        'http://192.168.0.50:8000',
      );
    });

    test('membiarkan alamat yang sudah lengkap', () {
      expect(
        ApiConfig.normalizeBaseUrl('http://192.168.0.50:8000'),
        'http://192.168.0.50:8000',
      );
    });

    test('membuang garis miring di ujung', () {
      expect(
        ApiConfig.normalizeBaseUrl('http://192.168.0.50:8000/'),
        'http://192.168.0.50:8000',
      );
    });

    test('port 80 dan 443 tidak digandakan', () {
      /*
       * `Uri.hasPort` bernilai false untuk port default skemanya, jadi
       * pemeriksaan naif mengira alamat ini belum punya port dan
       * menghasilkan `...:80:8000` — alamat yang tidak bisa dihubungi,
       * dengan pesan error yang tidak menjelaskan apa pun.
       */
      expect(
        ApiConfig.normalizeBaseUrl('http://192.168.0.50:80'),
        'http://192.168.0.50:80',
      );
      expect(
        ApiConfig.normalizeBaseUrl('https://billing.example.com:443'),
        'https://billing.example.com:443',
      );
    });

    test('nama domain tanpa port tetap dapat 8000', () {
      // Tahap 3A: masih di port 8000 sampai ada reverse proxy.
      expect(
        ApiConfig.normalizeBaseUrl('https://billing.example.com'),
        'https://billing.example.com:8000',
      );
    });
  });

  group('validateBaseUrl', () {
    test('kosong ditolak', () {
      expect(ApiConfig.validateBaseUrl('  '), isNotNull);
    });

    test('alamat wajar diterima', () {
      expect(ApiConfig.validateBaseUrl('192.168.0.50:8000'), isNull);
    });
  });
}
