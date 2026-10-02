import 'package:flutter/material.dart';

import 'tokens.dart';

/// Tipografi aplikasi.
///
/// CATATAN FONT — keputusan sadar, jangan diubah tanpa alasan:
/// `UI-UX-SPEC.md` menetapkan Fira Sans + Fira Code. Paket `google_fonts`
/// sengaja TIDAK dipakai karena mengunduh font saat runtime, sementara app ini
/// justru dirancang untuk jaringan lokal tanpa internet. Sampai file font
/// dibundel ke `assets/fonts/`, [fontFamily] dibiarkan null (font sistem).
///
/// Yang WAJIB tetap jalan tanpa Fira: tabular figures pada timer dan uang.
/// Roboto (default Android) mendukung fitur `tnum`, jadi [tabular] tetap benar.
///
/// Untuk membundel Fira nanti: taruh TTF di `assets/fonts/`, daftarkan di
/// `pubspec.yaml`, lalu isi [fontFamily] dan [monoFamily].
class AppTypography {
  AppTypography._();

  static const String? fontFamily = null; // -> 'FiraSans' setelah dibundel
  static const String? monoFamily = null; // -> 'FiraCode' setelah dibundel

  /// Angka berlebar sama. WAJIB untuk timer dan uang: tanpa ini countdown
  /// bergoyang kiri-kanan setiap detik karena '1' lebih sempit dari '8'.
  static const List<FontFeature> tabular = [
    FontFeature.tabularFigures(),
  ];

  // Timer
  static const timerLarge = TextStyle(
    fontFamily: monoFamily,
    fontSize: 48,
    fontWeight: FontWeight.w600,
    fontFeatures: tabular,
    height: 1.1,
  );

  static const timerCard = TextStyle(
    fontFamily: monoFamily,
    fontSize: 28,
    fontWeight: FontWeight.w600,
    fontFeatures: tabular,
    height: 1.1,
  );

  // Uang
  static const moneyLarge = TextStyle(
    fontFamily: monoFamily,
    fontSize: 24,
    fontWeight: FontWeight.w500,
    fontFeatures: tabular,
  );

  static const money = TextStyle(
    fontFamily: monoFamily,
    fontSize: 18,
    fontWeight: FontWeight.w500,
    fontFeatures: tabular,
  );

  static const moneySmall = TextStyle(
    fontFamily: monoFamily,
    fontSize: 14,
    fontWeight: FontWeight.w500,
    fontFeatures: tabular,
  );

  // Teks
  static const screenTitle = TextStyle(
    fontFamily: fontFamily,
    fontSize: 24,
    fontWeight: FontWeight.w600,
  );

  static const cardLabel = TextStyle(
    fontFamily: fontFamily,
    fontSize: 16,
    fontWeight: FontWeight.w500,
  );

  static const body = TextStyle(
    fontFamily: fontFamily,
    fontSize: 16,
    fontWeight: FontWeight.w400,
    height: 1.5,
  );

  static const caption = TextStyle(
    fontFamily: fontFamily,
    fontSize: 14,
    fontWeight: FontWeight.w400,
  );
}

/// Tema gelap. Light mode TIDAK dibuat — lihat `UI-UX-SPEC.md` §1.
class AppTheme {
  AppTheme._();

  static ThemeData dark() {
    const scheme = ColorScheme.dark(
      primary: AppColors.primary,
      onPrimary: AppColors.onPrimary,
      secondary: AppColors.accent,
      onSecondary: AppColors.bg,
      surface: AppColors.surface,
      onSurface: AppColors.text,
      error: AppColors.danger,
      onError: AppColors.onPrimary,
      outline: AppColors.border,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColors.bg,
      canvasColor: AppColors.bg,
      fontFamily: AppTypography.fontFamily,
      splashFactory: InkSparkle.splashFactory,
      textTheme: const TextTheme(
        headlineMedium: AppTypography.screenTitle,
        titleMedium: AppTypography.cardLabel,
        bodyLarge: AppTypography.body,
        bodyMedium: AppTypography.body,
        bodySmall: AppTypography.caption,
      ).apply(
        bodyColor: AppColors.text,
        displayColor: AppColors.text,
      ),
      dividerTheme: const DividerThemeData(
        color: AppColors.border,
        thickness: 1,
        space: 1,
      ),
      cardTheme: CardThemeData(
        color: AppColors.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.card),
          side: const BorderSide(color: AppColors.border),
        ),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.surfaceRaised,
        foregroundColor: AppColors.text,
        elevation: 0,
        centerTitle: false,
        toolbarHeight: AppSize.headerHeight,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(0, AppSize.minTouchTarget),
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.button),
          ),
          textStyle: AppTypography.cardLabel,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(0, AppSize.minTouchTarget),
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
          side: const BorderSide(color: AppColors.border),
          foregroundColor: AppColors.text,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.button),
          ),
          textStyle: AppTypography.cardLabel,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          minimumSize: const Size(0, AppSize.minTouchTarget),
          foregroundColor: AppColors.primary,
          textStyle: AppTypography.cardLabel,
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surfaceRaised,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.md,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.button),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.button),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.button),
          borderSide: const BorderSide(color: AppColors.primary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.button),
          borderSide: const BorderSide(color: AppColors.danger),
        ),
        labelStyle: const TextStyle(color: AppColors.textMuted),
        helperStyle: const TextStyle(color: AppColors.textMuted),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.surfaceRaised,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.modal),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: AppColors.surfaceRaised,
        contentTextStyle: const TextStyle(color: AppColors.text),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.button),
        ),
      ),
      listTileTheme: const ListTileThemeData(
        minVerticalPadding: AppSpacing.sm,
        iconColor: AppColors.textMuted,
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.primary,
      ),
    );
  }
}
