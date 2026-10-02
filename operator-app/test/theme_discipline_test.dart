// Menegakkan aturan visual DEC-016 / UI-UX-SPEC §1 secara mekanis.
//
// Daftar "yang dilarang" di spec tidak ada gunanya kalau hanya berupa tulisan:
// gradasi dan glow masuk kembali satu widget pada satu waktu, dan tidak ada
// yang menyadarinya sampai UI kembali terlihat seperti dibuat AI. Test ini
// membaca source dan menolaknya.
//
// Kontras juga dihitung di sini — bukan hanya di `docs/tools/contrast.py` —
// supaya ikut berjalan di `flutter test`. Keduanya membaca warna dari
// `AppColors`, jadi palet tidak bisa menyimpang; **test ini yang mengikat.**

import 'dart:io';
import 'dart:math' as math;

import 'package:operator_app/core/theme/tokens.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// Semua file Dart di `lib/`, berpasangan dengan path relatifnya.
List<(String, String)> _libSources() {
  final dir = Directory('lib');
  expect(dir.existsSync(), isTrue,
      reason: 'test harus dijalankan dari root paket operator-app');

  return dir
      .listSync(recursive: true)
      .whereType<File>()
      .where((f) => f.path.endsWith('.dart'))
      .map((f) => (f.path.replaceAll(r'\', '/'), f.readAsStringSync()))
      .toList();
}

double _relativeLuminance(Color c) {
  // `.r/.g/.b` sudah bernilai 0..1, jadi tidak perlu dibagi 255.
  double channel(double s) =>
      s <= 0.03928 ? s / 12.92 : math.pow((s + 0.055) / 1.055, 2.4) as double;

  return 0.2126 * channel(c.r) +
      0.7152 * channel(c.g) +
      0.0722 * channel(c.b);
}

double _contrast(Color fg, Color bg) {
  final a = _relativeLuminance(fg);
  final b = _relativeLuminance(bg);
  final hi = math.max(a, b);
  final lo = math.min(a, b);
  return (hi + 0.05) / (lo + 0.05);
}

void main() {
  group('Aturan visual DEC-016 ditegakkan di source', () {
    test('tidak ada gradasi apa pun di lib/', () {
      final offenders = <String>[];
      for (final (path, src) in _libSources()) {
        if (src.contains('Gradient')) offenders.add(path);
      }

      expect(offenders, isEmpty,
          reason: 'Gradasi adalah penanda "dibuat AI" yang paling cepat '
              'terbaca (DEC-016). Pakai satu nada permukaan, bukan gradasi.');
    });

    test('warna hanya lewat AppColors — tidak ada hex mentah di widget', () {
      final offenders = <String>[];
      for (final (path, src) in _libSources()) {
        if (path.endsWith('core/theme/tokens.dart')) continue;
        if (RegExp(r'Color\(0x').hasMatch(src)) offenders.add(path);
      }

      expect(offenders, isEmpty,
          reason: 'Hex mentah melewati verifikasi kontras. Tambahkan token '
              'di tokens.dart, lalu daftarkan pasangannya di test ini.');
    });

    test('shadow hanya didefinisikan di tokens.dart', () {
      final offenders = <String>[];
      for (final (path, src) in _libSources()) {
        if (path.endsWith('core/theme/tokens.dart')) continue;
        if (src.contains('BoxShadow(')) offenders.add(path);
      }

      expect(offenders, isEmpty,
          reason: 'Pada tema terang, kartu dipisahkan garis + nada '
              'permukaan. Shadow hanya untuk yang benar-benar melayang: '
              'pakai AppShadow.modal / .panel.');
    });

    test('tidak ada dark theme yang tertinggal', () {
      final offenders = <String>[];
      for (final (path, src) in _libSources()) {
        if (src.contains('Brightness.dark')) offenders.add(path);
      }

      expect(offenders, isEmpty,
          reason: 'Dark mode dihentikan di DEC-016. Operator app terang, '
              'tv-agent hitam — dan tv-agent bukan Flutter.');
    });
  });

  group('Kontras WCAG', () {
    // Teks yang harus dibaca: 4,5:1.
    const textPairs = <(String, Color, Color)>[
      ('teks utama / kartu', AppColors.onSurface, AppColors.surfaceLow),
      ('teks utama / kanvas', AppColors.onSurface, AppColors.surface),
      ('teks utama / inset', AppColors.onSurface, AppColors.surfaceContainer),
      ('teks sekunder / kartu',
          AppColors.onSurfaceVariant, AppColors.surfaceLow),
      ('teks sekunder / kanvas',
          AppColors.onSurfaceVariant, AppColors.surface),
      ('teks sekunder / inset',
          AppColors.onSurfaceVariant, AppColors.surfaceContainer),
      ('aksen / kartu', AppColors.primary, AppColors.surfaceLow),
      ('aksen / kanvas', AppColors.primary, AppColors.surface),
      ('aksen / inset', AppColors.primary, AppColors.surfaceContainer),
      ('aksen / tint aksen', AppColors.primary, AppColors.primarySurface),
      ('aksen gelap / kartu',
          AppColors.primaryFixedDim, AppColors.surfaceLow),
      ('teks error / tint error',
          AppColors.onErrorContainer, AppColors.errorContainer),
      ('teks di tombol aksen', AppColors.onPrimary, AppColors.primary),
      ('teks di tombol hijau', AppColors.onSecondary, AppColors.secondary),
      ('teks di tombol merah', AppColors.onError, AppColors.error),
      ('teks di tombol amber',
          AppColors.onTertiary, AppColors.tertiaryContainer),
      ('teks di chip terpilih', AppColors.surfaceLow, AppColors.onSurface),
      // Monogram tampil di alas merek kalau pelanggan belum punya aset logo.
      ('monogram di alas merek', AppColors.surfaceLow, AppColors.brandPlate),
    ];

    for (final (name, fg, bg) in textPairs) {
      test('$name ≥ 4.5:1', () {
        expect(_contrast(fg, bg), greaterThanOrEqualTo(4.5));
      });
    }

    // Setiap warna status dipakai sebagai teks label, di kartu biasa
    // maupun di kartu read-only yang berlatar `surfaceContainer`.
    // Keduanya diuji: order yang dibatalkan pernah lolos review di latar
    // putih lalu gagal di latar inset.
    const statuses = <(String, Color)>[
      ('tersedia', AppColors.statusAvailable),
      ('bermain', AppColors.statusActive),
      ('hampir habis', AppColors.statusWarning),
      ('habis', AppColors.statusExpired),
      ('menunggu bayar', AppColors.statusPendingPayment),
      ('checkout', AppColors.statusCheckout),
      ('offline', AppColors.statusOffline),
    ];

    for (final (name, color) in statuses) {
      test('status $name terbaca di kartu dan di bidang cekung', () {
        expect(_contrast(color, AppColors.surfaceLow),
            greaterThanOrEqualTo(4.5),
            reason: 'label status di kartu biasa');
        expect(_contrast(color, AppColors.surfaceContainer),
            greaterThanOrEqualTo(4.5),
            reason: 'label status di kartu read-only / bidang cekung');
      });
    }

    test('teks pendukung ≥ 3:1 — sengaja di bawah 4.5', () {
      // `outline` hanya untuk label pendukung (kode order, jam, alamat).
      // Kalau dipakai untuk informasi yang harus dibaca, itu bug — tapi
      // yang menangkapnya adalah review, bukan test ini.
      final ratio = _contrast(AppColors.outline, AppColors.surfaceLow);
      expect(ratio, greaterThanOrEqualTo(3.0));
      expect(ratio, lessThan(4.5),
          reason: 'kalau sudah ≥4.5, naikkan perannya jadi teks biasa '
              'dan perbarui DEC-016 — jangan biarkan catatannya basi');
    });

    test('garis kartu masih terlihat di atas kanvas putih', () {
      // Hanya perlu terlihat, bukan terbaca. Di bawah ~1,1 tepi kartu
      // hilang dan seluruh layar terasa rata.
      expect(_contrast(AppColors.surfaceHigh, AppColors.surface),
          greaterThanOrEqualTo(1.1));
      expect(_contrast(AppColors.surfaceHigh, AppColors.surfaceLow),
          greaterThanOrEqualTo(1.1));
    });

    test('menunggu bayar tidak tertukar dengan hampir habis', () {
      // Keduanya menuntut tindakan berbeda. Kalau hue-nya berdekatan,
      // operator akan salah membaca di bawah tekanan.
      final pending = HSLColor.fromColor(AppColors.statusPendingPayment);
      final warning = HSLColor.fromColor(AppColors.statusWarning);
      var delta = (pending.hue - warning.hue).abs();
      if (delta > 180) delta = 360 - delta;

      expect(delta, greaterThan(60),
          reason: 'jarak hue hanya ${delta.toStringAsFixed(0)}° — '
              'indigo vs amber harus jelas berbeda (DEC-016)');
    });
  });
}
