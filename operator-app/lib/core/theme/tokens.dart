import 'package:flutter/material.dart';

/// Design tokens — sumber tunggal.
///
/// Diturunkan dari `contoh.html` yang disetujui user: **Material 3 dark**
/// dengan aksen cyan + mint, permukaan bertingkat biru-gelap, dan glow
/// halus pada elemen aktif.
///
/// Penamaan mengikuti peran Material 3 (`surfaceContainer*`, `onSurface*`)
/// supaya cocok dengan `ColorScheme` dan mudah dirujuk balik ke contoh.
///
/// JANGAN menulis hex mentah di widget. Semua warna lewat file ini.
class AppColors {
  AppColors._();

  // ── Permukaan: enam tingkat ───────────────────────────────────────
  // Kedalaman dari nada permukaan, bukan garis. Sidebar memakai tingkat
  // paling gelap supaya area kerja terasa terangkat di atasnya.

  /// Sidebar, header, footer — paling gelap.
  static const surfaceLowest = Color(0xFF0A0E18);

  /// Latar layar / area kerja.
  static const surface = Color(0xFF0F131D);
  static const background = surface;

  /// Kartu dan panel.
  static const surfaceLow = Color(0xFF171B26);

  /// Tombol sekunder, bidang di dalam kartu.
  static const surfaceContainer = Color(0xFF1C1F2A);

  /// Hover, track progress, pembatas tebal.
  static const surfaceHigh = Color(0xFF262A35);

  /// Permukaan paling terang — track bar, chip terpilih.
  static const surfaceHighest = Color(0xFF313540);

  /// Permukaan terang untuk hover tingkat atas.
  static const surfaceBright = Color(0xFF353944);

  // ── Teks ──────────────────────────────────────────────────────────

  /// Teks utama.
  static const onSurface = Color(0xFFDFE2F1);

  /// Teks sekunder.
  static const onSurfaceVariant = Color(0xFFBAC9CC);

  /// Teks tersier / placeholder. Setara `outline` di contoh.
  static const outline = Color(0xFF849396);

  /// Garis pemisah halus.
  static const outlineVariant = Color(0xFF3B494C);

  // ── Primary: cyan ─────────────────────────────────────────────────

  /// Teks/ikon di atas permukaan gelap — cyan terang.
  static const primary = Color(0xFFC3F5FF);

  /// Isian tombol utama, nav aktif, bar progress.
  static const primaryContainer = Color(0xFF00E5FF);

  /// Teks di atas [primaryContainer].
  static const onPrimaryContainer = Color(0xFF00626E);

  /// Teks di atas [primary].
  static const onPrimary = Color(0xFF00363D);

  static const primaryFixedDim = Color(0xFF00DAF3);

  // ── Secondary: mint — dipakai untuk status "berjalan/sehat" ───────

  static const secondary = Color(0xFF4EDEA3);
  static const secondaryContainer = Color(0xFF00A572);
  static const onSecondary = Color(0xFF003824);
  static const onSecondaryContainer = Color(0xFF00311F);

  // ── Tertiary: amber — uang & peringatan lembut ────────────────────

  static const tertiary = Color(0xFFFFE9D3);
  static const tertiaryContainer = Color(0xFFFFC681);
  static const tertiaryFixedDim = Color(0xFFFFB95F);
  static const onTertiary = Color(0xFF472A00);

  // ── Error ─────────────────────────────────────────────────────────

  static const error = Color(0xFFFFB4AB);
  static const errorContainer = Color(0xFF93000A);
  static const onError = Color(0xFF690005);
  static const onErrorContainer = Color(0xFFFFDAD6);

  // ── Status station ────────────────────────────────────────────────
  // Selalu dipakai bersama ikon dan label — lihat StatusStyle.

  /// Tersedia — cyan, karena station kosong adalah peluang, bukan masalah.
  static const statusAvailable = primary;

  /// Bermain — mint.
  static const statusActive = secondary;

  /// Hampir habis — amber.
  static const statusWarning = tertiaryFixedDim;

  /// Waktu habis — merah.
  static const statusExpired = error;

  /// Menunggu bayar — amber pucat, berbeda dari "hampir habis".
  static const statusPendingPayment = tertiary;

  /// Checkout.
  static const statusCheckout = Color(0xFFD0BCFF);

  /// Offline / maintenance.
  static const statusOffline = outline;

  // ── Lapisan transparan ────────────────────────────────────────────

  static const overlaySubtle = Color(0x0FFFFFFF);
  static const overlayMedium = Color(0x1AFFFFFF);

  /// Scrim modal — 60% hitam (spec: 40–60%).
  static const scrim = Color(0x99000000);
}

/// Spacing mengikuti skala `contoh.html`, dibulatkan ke grid 4dp.
class AppSpacing {
  AppSpacing._();

  /// 0.25rem
  static const xs = 4.0;

  /// 0.5rem
  static const sm = 8.0;

  /// 0.875rem
  static const md = 14.0;

  /// 1.25rem
  static const lg = 20.0;

  /// 1.75rem
  static const xl = 28.0;

  /// 2.5rem
  static const xxl = 40.0;

  /// Gutter antar kartu.
  static const gutter = 16.0;

  /// Gutter tepi area kerja.
  static const gutterLg = 24.0;
}

class AppRadius {
  AppRadius._();

  /// 0.25rem — tombol kecil, badge kotak.
  static const sm = 4.0;

  /// 0.5rem — tombol, input, nav item.
  static const md = 8.0;

  /// 0.75rem — kartu, panel.
  static const lg = 12.0;

  /// Modal & bottom sheet.
  static const modal = 16.0;

  static const pill = 999.0;
}

class AppSize {
  AppSize._();

  /// Material: minimum 48dp. Tablet dipakai berdiri & terburu-buru.
  static const minTouchTarget = 48.0;

  /// Tinggi header, sama dengan contoh (h-16).
  static const headerHeight = 64.0;

  /// Sidebar penuh (w-72).
  static const sidebarWidth = 288.0;

  /// Sidebar ringkas — hanya ikon, dipakai saat layar kurang lebar.
  static const sidebarRailWidth = 76.0;

  /// Di bawah lebar ini sidebar menyusut jadi rail.
  static const sidebarExpandBreakpoint = 1040.0;

  /// Tinggi bar progres waktu di kartu.
  static const progressBar = 6.0;

  /// Rail warna status di tepi kartu daftar (antrian F&B, device).
  static const statusRail = 4.0;

  /// Tinggi minimum kartu station agar isinya tidak overflow.
  ///
  /// Dipilih agar enam kartu tetap muat tanpa scroll pada tablet 1280x800
  /// landscape: setelah header, bar filter, strip shift, dan footer,
  /// tersisa sekitar 265 px per baris.
  static const stationCardMinHeight = 264.0;

  /// Lebar minimum kartu station.
  ///
  /// Grid dashboard menjaminnya lewat breakpoint jumlah kolom: 3 kolom
  /// hanya di atas 1100 px, 2 kolom di atas 700 px, di bawah itu 1 kolom.
  static const stationCardMinWidth = 300.0;
}

/// Shadow.
///
/// Di contoh, kedalaman disampaikan lewat nada permukaan dan **glow** pada
/// elemen aktif — bukan drop shadow tebal. Shadow di sini dibuat sangat
/// halus; yang memberi karakter adalah [glowPrimary].
class AppShadow {
  AppShadow._();

  static const card = <BoxShadow>[
    BoxShadow(color: Color(0x1F000000), blurRadius: 6, offset: Offset(0, 2)),
  ];

  static const panel = <BoxShadow>[
    BoxShadow(color: Color(0x4D000000), blurRadius: 16, offset: Offset(0, 1)),
  ];

  static const modal = <BoxShadow>[
    BoxShadow(color: Color(0x66000000), blurRadius: 24, offset: Offset(0, 8)),
  ];

  /// Glow cyan untuk nav aktif dan chip terpilih.
  /// Ini efek khas desainnya — jangan dipakai di mana-mana.
  static const glowPrimary = <BoxShadow>[
    BoxShadow(color: Color(0x4D00E5FF), blurRadius: 12),
  ];

  static List<BoxShadow> glow(Color color, {double alpha = 0.3}) => [
        BoxShadow(color: color.withValues(alpha: alpha), blurRadius: 12),
      ];
}

class AppMotion {
  AppMotion._();

  static const fast = Duration(milliseconds: 150);
  static const normal = Duration(milliseconds: 220);
  static const slow = Duration(milliseconds: 300);

  /// Animasi keluar ~60–70% durasi masuk.
  static const exit = Duration(milliseconds: 140);

  static const easeOut = Curves.easeOutCubic;
  static const easeIn = Curves.easeInCubic;
}
