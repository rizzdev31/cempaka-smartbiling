import 'package:flutter/material.dart';

import 'tokens.dart';

/// Tipografi.
///
/// Tiga family, masing-masing punya tugas jelas:
/// - **Space Grotesk** → judul, kode station. Geometris, membedakan diri dari
///   body tanpa jadi dekoratif.
/// - **Plus Jakarta Sans** → body dan label.
/// - **JetBrains Mono** → timer, uang, label teknis.
///
/// Mono untuk angka dipertahankan dari tema sebelumnya, dan itu memang tanda
/// perkakas operasional: kolom angka sejajar dan timer tidak bergoyang.
///
/// Font **dibundel** di `assets/fonts/` — lihat `assets/fonts/README.md`.
class AppTypography {
  AppTypography._();

  static const heading = 'SpaceGrotesk';
  static const body = 'PlusJakartaSans';
  static const mono = 'JetBrainsMono';

  static const List<FontFeature> tabular = [FontFeature.tabularFigures()];

  // ── Display & timer (mono) ────────────────────────────────────────

  static const displayLg = TextStyle(
    fontFamily: mono,
    fontSize: 44,
    height: 50 / 44,
    letterSpacing: -1.1,
    fontWeight: FontWeight.w700,
    fontFeatures: tabular,
  );

  static const timerCard = TextStyle(
    fontFamily: mono,
    fontSize: 33,
    height: 37 / 33,
    letterSpacing: -0.6,
    fontWeight: FontWeight.w700,
    fontFeatures: tabular,
  );

  // ── Headline (Space Grotesk) ──────────────────────────────────────

  static const headlineLg = TextStyle(
    fontFamily: heading,
    fontSize: 30,
    height: 38 / 30,
    letterSpacing: -0.6,
    fontWeight: FontWeight.w700,
  );

  static const headlineMd = TextStyle(
    fontFamily: heading,
    fontSize: 23,
    height: 30 / 23,
    letterSpacing: -0.3,
    fontWeight: FontWeight.w700,
  );

  static const headlineSm = TextStyle(
    fontFamily: heading,
    fontSize: 17,
    height: 23 / 17,
    letterSpacing: -0.1,
    fontWeight: FontWeight.w600,
  );

  // ── Body (Plus Jakarta Sans) ──────────────────────────────────────

  static const bodyLg = TextStyle(
    fontFamily: body,
    fontSize: 15,
    height: 22 / 15,
    fontWeight: FontWeight.w500,
  );

  static const bodyMd = TextStyle(
    fontFamily: body,
    fontSize: 14,
    height: 20 / 14,
    fontWeight: FontWeight.w400,
  );

  static const bodySm = TextStyle(
    fontFamily: body,
    fontSize: 12.5,
    height: 17 / 12.5,
    fontWeight: FontWeight.w400,
  );

  // ── Label (mono) ──────────────────────────────────────────────────

  static const labelLg = TextStyle(
    fontFamily: mono,
    fontSize: 13,
    height: 18 / 13,
    letterSpacing: 0.1,
    fontWeight: FontWeight.w600,
    fontFeatures: tabular,
  );

  static const labelMd = TextStyle(
    fontFamily: mono,
    fontSize: 12,
    height: 16 / 12,
    letterSpacing: 0.2,
    fontWeight: FontWeight.w500,
    fontFeatures: tabular,
  );

  /// Judul bagian. Huruf besar dengan tracking longgar — lazim di panel
  /// kontrol, dan menjaga judul tidak bersaing dengan isinya.
  static const labelSm = TextStyle(
    fontFamily: mono,
    fontSize: 10.5,
    height: 14 / 10.5,
    letterSpacing: 0.7,
    fontWeight: FontWeight.w600,
    fontFeatures: tabular,
  );

  // ── Uang (mono) ───────────────────────────────────────────────────

  static const moneyLg = TextStyle(
    fontFamily: mono,
    fontSize: 19,
    height: 25 / 19,
    letterSpacing: -0.2,
    fontWeight: FontWeight.w700,
    fontFeatures: tabular,
  );

  static const money = TextStyle(
    fontFamily: mono,
    fontSize: 14,
    height: 19 / 14,
    fontWeight: FontWeight.w600,
    fontFeatures: tabular,
  );

  static const moneySm = TextStyle(
    fontFamily: mono,
    fontSize: 12,
    height: 16 / 12,
    fontWeight: FontWeight.w600,
    fontFeatures: tabular,
  );
}

/// Tema terang.
///
/// Dark mode tidak lagi dibuat. Alasan versi sebelumnya memilih gelap tetap
/// tercatat di `UI-UX-SPEC.md` §1 dan di DECISION-LOG — kalau ternyata layar
/// terang mengganggu di ruang rental, alasannya tidak perlu digali ulang.
class AppTheme {
  AppTheme._();

  static ThemeData light() {
    const scheme = ColorScheme.light(
      primary: AppColors.primary,
      onPrimary: AppColors.onPrimary,
      primaryContainer: AppColors.primaryContainer,
      onPrimaryContainer: AppColors.onPrimaryContainer,
      secondary: AppColors.secondary,
      onSecondary: AppColors.onSecondary,
      secondaryContainer: AppColors.secondaryContainer,
      onSecondaryContainer: AppColors.onSecondaryContainer,
      tertiary: AppColors.tertiary,
      onTertiary: AppColors.onTertiary,
      tertiaryContainer: AppColors.tertiaryContainer,
      surface: AppColors.surface,
      onSurface: AppColors.onSurface,
      onSurfaceVariant: AppColors.onSurfaceVariant,
      surfaceContainerLowest: AppColors.surfaceLowest,
      surfaceContainerLow: AppColors.surfaceLow,
      surfaceContainer: AppColors.surfaceContainer,
      surfaceContainerHigh: AppColors.surfaceHigh,
      surfaceContainerHighest: AppColors.surfaceHighest,
      error: AppColors.error,
      onError: AppColors.onError,
      errorContainer: AppColors.errorContainer,
      onErrorContainer: AppColors.onErrorContainer,
      outline: AppColors.outline,
      outlineVariant: AppColors.outlineVariant,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColors.surface,
      canvasColor: AppColors.surface,
      fontFamily: AppTypography.body,

      // Ripple Material 3 pada permukaan putih terbaca sebagai genangan.
      // InkRipple klasik lebih tenang dan lebih cepat hilang.
      splashFactory: InkRipple.splashFactory,

      textTheme: const TextTheme(
        displayLarge: AppTypography.displayLg,
        headlineLarge: AppTypography.headlineLg,
        headlineMedium: AppTypography.headlineMd,
        headlineSmall: AppTypography.headlineSm,
        titleLarge: AppTypography.headlineSm,
        titleMedium: AppTypography.bodyLg,
        bodyLarge: AppTypography.bodyLg,
        bodyMedium: AppTypography.bodyMd,
        bodySmall: AppTypography.bodySm,
        labelLarge: AppTypography.labelLg,
        labelMedium: AppTypography.labelMd,
        labelSmall: AppTypography.labelSm,
      ).apply(
        bodyColor: AppColors.onSurface,
        displayColor: AppColors.onSurface,
      ),

      dividerTheme: const DividerThemeData(
        color: AppColors.outlineVariant,
        thickness: 1,
        space: 1,
      ),

      cardTheme: CardThemeData(
        color: AppColors.surfaceLow,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
          side: const BorderSide(color: AppColors.surfaceHigh),
        ),
      ),

      appBarTheme: AppBarTheme(
        backgroundColor: AppColors.surfaceLowest,
        surfaceTintColor: Colors.transparent,
        foregroundColor: AppColors.onSurface,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        toolbarHeight: AppSize.headerHeight,
        titleTextStyle: AppTypography.headlineSm.copyWith(
          color: AppColors.onSurface,
        ),
        iconTheme:
            const IconThemeData(color: AppColors.onSurfaceVariant, size: 21),
        // Garis, bukan shadow, yang memisahkan header dari isi.
        shape: const Border(
          bottom: BorderSide(color: AppColors.surfaceHigh),
        ),
      ),

      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.primaryContainer,
          foregroundColor: AppColors.onPrimaryContainer,
          minimumSize: const Size(0, AppSize.minTouchTarget),
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
          textStyle: AppTypography.labelLg,
          disabledBackgroundColor: AppColors.surfaceContainer,
          disabledForegroundColor: AppColors.outline,
        ),
      ),

      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(0, AppSize.minTouchTarget),
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          side: const BorderSide(color: AppColors.surfaceHigh),
          foregroundColor: AppColors.onSurface,
          backgroundColor: AppColors.surfaceLow,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
          textStyle: AppTypography.labelLg,
          disabledForegroundColor: AppColors.outline,
        ),
      ),

      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          minimumSize: const Size(0, AppSize.minTouchTarget),
          foregroundColor: AppColors.primary,
          textStyle: AppTypography.labelLg,
        ),
      ),

      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          minimumSize:
              const Size(AppSize.minTouchTarget, AppSize.minTouchTarget),
          foregroundColor: AppColors.onSurfaceVariant,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
        ),
      ),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surfaceLow,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.md,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: const BorderSide(color: AppColors.surfaceHigh),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: const BorderSide(color: AppColors.surfaceHigh),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: const BorderSide(color: AppColors.primary, width: 1.6),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: const BorderSide(color: AppColors.error),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: const BorderSide(color: AppColors.error, width: 1.6),
        ),
        hintStyle: AppTypography.bodySm.copyWith(color: AppColors.outline),
        labelStyle: AppTypography.bodyMd.copyWith(
          color: AppColors.onSurfaceVariant,
        ),
        helperStyle: AppTypography.bodySm.copyWith(color: AppColors.outline),
        errorStyle: AppTypography.bodySm.copyWith(color: AppColors.error),
      ),

      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.surfaceLow,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.modal),
        ),
        titleTextStyle: AppTypography.headlineSm.copyWith(
          color: AppColors.onSurface,
        ),
      ),

      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: AppColors.surfaceLow,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius:
              BorderRadius.vertical(top: Radius.circular(AppRadius.modal)),
        ),
      ),

      snackBarTheme: SnackBarThemeData(
        // Snackbar gelap di atas UI terang: cukup kontras untuk terbaca
        // sekilas tanpa membuat seluruh layar bergeser nadanya.
        backgroundColor: AppColors.onSurface,
        contentTextStyle: AppTypography.bodyMd.copyWith(color: Colors.white),
        actionTextColor: AppColors.primarySurface,
        behavior: SnackBarBehavior.floating,
        elevation: 4,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
      ),

      chipTheme: ChipThemeData(
        backgroundColor: AppColors.surfaceLow,
        selectedColor: AppColors.primaryContainer,
        side: const BorderSide(color: AppColors.surfaceHigh),
        labelStyle: AppTypography.labelMd,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.pill),
        ),
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm + 2,
          vertical: AppSpacing.sm,
        ),
      ),

      listTileTheme: const ListTileThemeData(
        minVerticalPadding: AppSpacing.sm,
        iconColor: AppColors.onSurfaceVariant,
      ),

      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.primary,
        linearTrackColor: AppColors.surfaceHighest,
        linearMinHeight: AppSize.progressBar,
      ),

      tooltipTheme: TooltipThemeData(
        decoration: BoxDecoration(
          color: AppColors.onSurface,
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        textStyle: AppTypography.bodySm.copyWith(color: Colors.white),
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm + 2,
          vertical: AppSpacing.sm,
        ),
      ),
    );
  }
}
