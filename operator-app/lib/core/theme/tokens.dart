import 'package:flutter/material.dart';

/// Design tokens — sumber tunggal.
/// Referensi: `docs/UI-UX-SPEC.md` §2.
///
/// Arah visual: **Dark Mode (OLED)** sebagai dasar, dilapisi **Soft UI
/// Evolution** — kedalaman halus lewat lapisan permukaan dan shadow lembut
/// alih-alih garis tegas di mana-mana. Modern tanpa jadi ramai.
///
/// JANGAN menulis hex mentah di widget. Semua warna lewat file ini.
class AppColors {
  AppColors._();

  // ── Permukaan: empat tingkat, bukan dua ──────────────────────────
  // Kedalaman disampaikan lewat nada permukaan, bukan garis. Ini yang
  // membuat tampilan terasa modern tanpa menambah elemen.

  /// Latar layar — paling gelap.
  static const bg = Color(0xFF080D18);

  /// Permukaan cekung: track progress, input, bidang di dalam kartu.
  static const surfaceSunken = Color(0xFF0D1524);

  /// Kartu dan panel.
  static const surface = Color(0xFF131E33);

  /// Header, modal, bottom sheet — paling terang.
  static const surfaceRaised = Color(0xFF1B2941);

  /// Garis pemisah. Dipakai hemat — hanya saat nada permukaan tidak cukup.
  static const border = Color(0xFF26354F);

  /// Garis tipis di dalam kartu.
  static const borderSubtle = Color(0xFF1E2B42);

  // ── Brand ────────────────────────────────────────────────────────
  static const primary = Color(0xFF4C8DFF);
  static const primaryDim = Color(0xFF2F6AD9);
  static const onPrimary = Color(0xFFFFFFFF);
  static const accent = Color(0xFFFFB020);

  // ── Teks ─────────────────────────────────────────────────────────
  static const text = Color(0xFFF2F5FA);

  /// Label sekunder — kontras ≥ 4.5:1 terhadap `surface`.
  static const textMuted = Color(0xFF9AA8C0);

  /// Teks paling redup. Hanya untuk informasi yang benar-benar tersier.
  static const textFaint = Color(0xFF6B7A94);

  static const danger = Color(0xFFFF5A5A);

  // ── Status station ───────────────────────────────────────────────
  // Selalu dipakai bersama ikon dan label — lihat AppStatusStyle.
  static const statusAvailable = Color(0xFF2ED47A);
  static const statusPendingPayment = Color(0xFFFFB020);
  static const statusActive = Color(0xFF4C8DFF);
  static const statusWarning = Color(0xFFFF8C42);
  static const statusExpired = Color(0xFFFF5A5A);
  static const statusCheckout = Color(0xFFB36BFF);
  static const statusOffline = Color(0xFF6B7A94);

  // ── Lapisan transparan ───────────────────────────────────────────
  /// Untuk hover/press dan bidang netral di atas permukaan apa pun.
  static const overlaySubtle = Color(0x0FFFFFFF);
  static const overlayMedium = Color(0x1AFFFFFF);

  /// Scrim modal — 55% hitam (spec: 40–60%).
  static const scrim = Color(0x8C000000);
}

/// Grid 8dp. Hanya nilai di sini yang boleh dipakai.
class AppSpacing {
  AppSpacing._();

  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 16.0;
  static const lg = 24.0;
  static const xl = 32.0;
  static const xxl = 48.0;
}

class AppRadius {
  AppRadius._();

  static const card = 16.0;
  static const button = 10.0;
  static const field = 10.0;
  static const modal = 20.0;
  static const chip = 999.0;
}

class AppSize {
  AppSize._();

  /// Material: minimum 48dp. Tablet dipakai berdiri & terburu-buru.
  static const minTouchTarget = 48.0;
  static const headerHeight = 68.0;

  /// Rail status di tepi kiri kartu station.
  static const statusRail = 4.0;

  /// Tinggi bar progress waktu di kartu.
  static const progressBar = 4.0;
}

/// Shadow lembut berlapis — "Soft UI Evolution".
///
/// Di dark mode shadow harus **halus**; shadow pekat membuat kartu tampak
/// kotor, bukan terangkat. Dua lapisan tipis lebih baik daripada satu tebal.
class AppShadow {
  AppShadow._();

  static const card = <BoxShadow>[
    BoxShadow(color: Color(0x2E000000), blurRadius: 2, offset: Offset(0, 1)),
    BoxShadow(color: Color(0x1F000000), blurRadius: 8, offset: Offset(0, 3)),
  ];

  static const raised = <BoxShadow>[
    BoxShadow(color: Color(0x3D000000), blurRadius: 4, offset: Offset(0, 2)),
    BoxShadow(color: Color(0x2E000000), blurRadius: 18, offset: Offset(0, 8)),
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
