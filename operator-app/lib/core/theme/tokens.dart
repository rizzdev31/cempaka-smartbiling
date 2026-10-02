import 'dart:ui';

/// Design tokens — sumber tunggal.
/// Referensi: `docs/UI-UX-SPEC.md` §2.
///
/// JANGAN menulis hex mentah di widget. Semua warna lewat file ini.
class AppColors {
  AppColors._();

  // Surface
  static const bg = Color(0xFF0B1120);
  static const surface = Color(0xFF111C33);
  static const surfaceRaised = Color(0xFF1A2742);
  static const border = Color(0xFF24324D);

  // Brand
  static const primary = Color(0xFF3B82F6);
  static const onPrimary = Color(0xFFFFFFFF);
  static const accent = Color(0xFFF59E0B);

  // Text
  static const text = Color(0xFFF1F5F9);
  static const textMuted = Color(0xFF94A3B8);

  // Semantic
  static const danger = Color(0xFFEF4444);

  // Status station — lihat AppStatusStyle untuk pemakaiannya
  static const statusAvailable = Color(0xFF22C55E);
  static const statusPendingPayment = Color(0xFFF59E0B);
  static const statusActive = Color(0xFF3B82F6);
  static const statusWarning = Color(0xFFFB923C);
  static const statusExpired = Color(0xFFEF4444);
  static const statusCheckout = Color(0xFFA855F7);
  static const statusOffline = Color(0xFF64748B);

  /// Scrim modal — 50% hitam (spec: 40–60%).
  static const scrim = Color(0x80000000);
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

  static const card = 12.0;
  static const button = 8.0;
  static const modal = 16.0;
  static const chip = 999.0;
}

class AppSize {
  AppSize._();

  /// Material: minimum 48dp. Tablet dipakai berdiri & terburu-buru.
  static const minTouchTarget = 48.0;
  static const headerHeight = 64.0;
  static const bottomBarHeight = 64.0;
}

class AppMotion {
  AppMotion._();

  static const fast = Duration(milliseconds: 150);
  static const normal = Duration(milliseconds: 200);
  static const slow = Duration(milliseconds: 300);

  /// Animasi keluar ~60–70% durasi masuk.
  static const exit = Duration(milliseconds: 130);
}
