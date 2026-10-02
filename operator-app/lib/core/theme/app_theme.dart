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
  static const List<FontFeature> tabular = [FontFeature.tabularFigures()];

  // ── Timer ────────────────────────────────────────────────────────

  /// Timer utama di layar detail sesi — elemen paling dominan.
  static const timerHero = TextStyle(
    fontFamily: monoFamily,
    fontSize: 56,
    fontWeight: FontWeight.w600,
    fontFeatures: tabular,
    height: 1.0,
    letterSpacing: -1.5,
  );

  static const timerLarge = TextStyle(
    fontFamily: monoFamily,
    fontSize: 44,
    fontWeight: FontWeight.w600,
    fontFeatures: tabular,
    height: 1.05,
    letterSpacing: -1,
  );

  static const timerCard = TextStyle(
    fontFamily: monoFamily,
    fontSize: 30,
    fontWeight: FontWeight.w600,
    fontFeatures: tabular,
    height: 1.05,
    letterSpacing: -0.5,
  );

  // ── Uang ─────────────────────────────────────────────────────────

  static const moneyLarge = TextStyle(
    fontFamily: monoFamily,
    fontSize: 24,
    fontWeight: FontWeight.w600,
    fontFeatures: tabular,
    letterSpacing: -0.3,
  );

  static const money = TextStyle(
    fontFamily: monoFamily,
    fontSize: 17,
    fontWeight: FontWeight.w500,
    fontFeatures: tabular,
  );

  static const moneySmall = TextStyle(
    fontFamily: monoFamily,
    fontSize: 14,
    fontWeight: FontWeight.w500,
    fontFeatures: tabular,
  );

  // ── Teks ─────────────────────────────────────────────────────────

  /// Nama station di kartu, judul panel besar.
  static const display = TextStyle(
    fontFamily: fontFamily,
    fontSize: 28,
    fontWeight: FontWeight.w700,
    height: 1.1,
    letterSpacing: -0.6,
  );

  static const screenTitle = TextStyle(
    fontFamily: fontFamily,
    fontSize: 22,
    fontWeight: FontWeight.w600,
    letterSpacing: -0.3,
  );

  static const sectionTitle = TextStyle(
    fontFamily: fontFamily,
    fontSize: 17,
    fontWeight: FontWeight.w600,
  );

  static const cardLabel = TextStyle(
    fontFamily: fontFamily,
    fontSize: 15,
    fontWeight: FontWeight.w500,
  );

  static const body = TextStyle(
    fontFamily: fontFamily,
    fontSize: 15,
    fontWeight: FontWeight.w400,
    height: 1.5,
  );

  static const caption = TextStyle(
    fontFamily: fontFamily,
    fontSize: 13,
    fontWeight: FontWeight.w400,
    height: 1.4,
  );

  /// Label kecil huruf besar — untuk judul bagian yang tidak boleh
  /// bersaing dengan isi.
  static const overline = TextStyle(
    fontFamily: fontFamily,
    fontSize: 11,
    fontWeight: FontWeight.w600,
    letterSpacing: 0.8,
  );
}

/// Tema gelap. Light mode TIDAK dibuat — lihat `UI-UX-SPEC.md` §1.
class AppTheme {
  AppTheme._();

  static ThemeData dark() {
    const scheme = ColorScheme.dark(
      primary: AppColors.primary,
      onPrimary: AppColors.onPrimary,
      primaryContainer: AppColors.primaryDim,
      secondary: AppColors.accent,
      onSecondary: AppColors.bg,
      surface: AppColors.surface,
      onSurface: AppColors.text,
      surfaceContainerLowest: AppColors.bg,
      surfaceContainerLow: AppColors.surfaceSunken,
      surfaceContainer: AppColors.surface,
      surfaceContainerHigh: AppColors.surfaceRaised,
      error: AppColors.danger,
      onError: AppColors.onPrimary,
      outline: AppColors.border,
      outlineVariant: AppColors.borderSubtle,
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
        displaySmall: AppTypography.display,
        headlineMedium: AppTypography.screenTitle,
        titleLarge: AppTypography.sectionTitle,
        titleMedium: AppTypography.cardLabel,
        bodyLarge: AppTypography.body,
        bodyMedium: AppTypography.body,
        bodySmall: AppTypography.caption,
        labelSmall: AppTypography.overline,
      ).apply(
        bodyColor: AppColors.text,
        displayColor: AppColors.text,
      ),

      // Garis dipakai hemat — kedalaman utama dari nada permukaan.
      dividerTheme: const DividerThemeData(
        color: AppColors.borderSubtle,
        thickness: 1,
        space: 1,
      ),

      cardTheme: CardThemeData(
        color: AppColors.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.card),
        ),
      ),

      appBarTheme: AppBarTheme(
        backgroundColor: AppColors.bg,
        surfaceTintColor: Colors.transparent,
        foregroundColor: AppColors.text,
        elevation: 0,
        centerTitle: false,
        toolbarHeight: AppSize.headerHeight,
        titleTextStyle: AppTypography.screenTitle.copyWith(
          color: AppColors.text,
        ),
        iconTheme: const IconThemeData(color: AppColors.textMuted, size: 22),
      ),

      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(0, AppSize.minTouchTarget),
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.button),
          ),
          textStyle: AppTypography.cardLabel,
          disabledBackgroundColor: AppColors.overlayMedium,
          disabledForegroundColor: AppColors.textFaint,
        ),
      ),

      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(0, AppSize.minTouchTarget),
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md + 2),
          side: const BorderSide(color: AppColors.border),
          foregroundColor: AppColors.text,
          backgroundColor: AppColors.overlaySubtle,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.button),
          ),
          textStyle: AppTypography.cardLabel,
          disabledForegroundColor: AppColors.textFaint,
        ),
      ),

      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          minimumSize: const Size(0, AppSize.minTouchTarget),
          foregroundColor: AppColors.primary,
          textStyle: AppTypography.cardLabel,
        ),
      ),

      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          minimumSize: const Size(AppSize.minTouchTarget, AppSize.minTouchTarget),
          foregroundColor: AppColors.textMuted,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.button),
          ),
        ),
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surfaceSunken,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.md,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.field),
          borderSide: const BorderSide(color: AppColors.borderSubtle),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.field),
          borderSide: const BorderSide(color: AppColors.borderSubtle),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.field),
          borderSide: const BorderSide(color: AppColors.primary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.field),
          borderSide: const BorderSide(color: AppColors.danger),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.field),
          borderSide: const BorderSide(color: AppColors.danger, width: 2),
        ),
        labelStyle: AppTypography.body.copyWith(color: AppColors.textMuted),
        helperStyle: AppTypography.caption.copyWith(color: AppColors.textFaint),
        errorStyle: AppTypography.caption.copyWith(color: AppColors.danger),
      ),

      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.surfaceRaised,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.modal),
        ),
        titleTextStyle: AppTypography.screenTitle.copyWith(
          color: AppColors.text,
        ),
      ),

      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: AppColors.surfaceRaised,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(
            top: Radius.circular(AppRadius.modal),
          ),
        ),
      ),

      snackBarTheme: SnackBarThemeData(
        backgroundColor: AppColors.surfaceRaised,
        contentTextStyle: AppTypography.body.copyWith(color: AppColors.text),
        behavior: SnackBarBehavior.floating,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.button),
        ),
      ),

      chipTheme: ChipThemeData(
        backgroundColor: AppColors.surfaceSunken,
        selectedColor: AppColors.primary,
        side: const BorderSide(color: AppColors.borderSubtle),
        labelStyle: AppTypography.cardLabel,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.button),
        ),
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm + 2,
          vertical: AppSpacing.sm + 2,
        ),
      ),

      listTileTheme: const ListTileThemeData(
        minVerticalPadding: AppSpacing.sm,
        iconColor: AppColors.textMuted,
      ),

      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.primary,
        linearTrackColor: AppColors.surfaceSunken,
        linearMinHeight: AppSize.progressBar,
      ),

      tooltipTheme: TooltipThemeData(
        decoration: BoxDecoration(
          color: AppColors.surfaceRaised,
          borderRadius: BorderRadius.circular(AppRadius.button),
          boxShadow: AppShadow.raised,
        ),
        textStyle: AppTypography.caption.copyWith(color: AppColors.text),
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm + 2,
          vertical: AppSpacing.sm,
        ),
      ),
    );
  }
}
