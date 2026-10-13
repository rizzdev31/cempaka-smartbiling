// Paket harus disaring per tipe konsol — DEC-019.
//
// Kenapa test ini ada: data asli punya **dua paket bernama "1 Jam"**, PS3
// Rp 8.000 dan PS4 Rp 10.000. Tanpa penyaringan, operator di station PS4
// melihat dua tombol dengan tulisan yang sama persis — dan yang salah
// ditolak server 422 dengan pesan yang tidak bisa dia perbaiki dari layar
// itu. Harganya pun berbeda 2.000, jadi salah pilih bukan sekadar gangguan.

import 'package:flutter_test/flutter_test.dart';
import 'package:operator_app/domain/models/models.dart';

Package pkg(String name, {String? consoleType, int price = 10000}) => Package(
      id: '$name-${consoleType ?? 'umum'}',
      name: name,
      durationMinutes: 60,
      price: price,
      hourlyRate: price,
      isActive: true,
      consoleType: consoleType,
    );

/// Penyaringan yang sama dengan yang dipakai `start_session_sheet.dart`.
List<Package> tersedia(List<Package> semua, String? konsolStation) {
  final fit = semua.where((p) => p.fitsConsole(konsolStation)).toList();
  return fit.isEmpty ? semua : fit;
}

void main() {
  group('Package.fitsConsole', () {
    test('cocok hanya untuk tipe konsolnya sendiri', () {
      final ps4 = pkg('1 Jam', consoleType: 'PS4');

      expect(ps4.fitsConsole('PS4'), isTrue);
      expect(ps4.fitsConsole('PS3'), isFalse);
    });

    test('paket tanpa tipe berlaku umum', () {
      // Server yang memutuskan sah atau tidak; menyembunyikannya di client
      // justru membuat daftar kosong tanpa sebab yang terlihat.
      expect(pkg('Promo').fitsConsole('PS4'), isTrue);
      expect(pkg('Promo').fitsConsole(null), isTrue);
    });

    test('station tanpa tipe konsol tidak menyaring apa pun', () {
      expect(pkg('1 Jam', consoleType: 'PS4').fitsConsole(null), isTrue);
    });
  });

  group('Daftar paket di layar Start', () {
    // Persis bentuk data seeder server.
    final semua = [
      pkg('1 Jam', consoleType: 'PS3', price: 8000),
      pkg('3 Jam + 1 Jam Gratis', consoleType: 'PS3', price: 30000),
      pkg('1 Jam', consoleType: 'PS4', price: 10000),
      pkg('3 Jam + 1 Jam Gratis', consoleType: 'PS4', price: 35000),
    ];

    test('station PS4 hanya melihat paket PS4', () {
      final hasil = tersedia(semua, 'PS4');

      expect(hasil.length, 2);
      expect(hasil.every((p) => p.consoleType == 'PS4'), isTrue);
    });

    test('tidak ada dua paket dengan nama sama di satu layar', () {
      // Inti masalahnya: bukan jumlahnya, tapi ambiguitasnya.
      final nama = tersedia(semua, 'PS4').map((p) => p.name).toList();
      expect(nama.toSet().length, nama.length);
    });

    test('harga yang tampil adalah harga tipe konsol itu', () {
      final satuJam =
          tersedia(semua, 'PS4').firstWhere((p) => p.name == '1 Jam');
      expect(satuJam.price, 10000, reason: 'PS3 Rp 8.000, PS4 Rp 10.000');
    });

    test('tipe konsol tak dikenal tetap menampilkan semua, bukan kosong', () {
      // Daftar kosong membuat station tidak bisa dibuka sama sekali — lebih
      // buruk daripada satu penolakan yang bisa dibaca operator.
      final hasil = tersedia(semua, 'PS5 VIP');
      expect(hasil.length, semua.length);
    });
  });

  group('Package.fromJson', () {
    test('membaca console_type dan station_type_id dari server', () {
      // Keduanya sebelumnya DIBUANG saat parsing — itu sebab penyaringannya
      // tidak mungkin dilakukan.
      final p = Package.fromJson(const {
        'id': 'pkg-1',
        'name': '1 Jam',
        'duration_minutes': 60,
        'price': 10000,
        'hourly_rate': 10000,
        'is_active': true,
        'console_type': 'PS4',
        'station_type_id': 'st-ps4',
      });

      expect(p.consoleType, 'PS4');
      expect(p.stationTypeId, 'st-ps4');
    });
  });
}
