import 'package:flutter/material.dart';

import 'tokens.dart';

/// Tipografi — mengikuti skala `contoh.html`.
///
/// Tiga family, masing-masing punya tugas jelas:
/// - **Space Grotesk** → judul, kode station. Karakter geometris yang
///   membedakannya dari body tanpa terasa dekoratif.
/// - **Plus Jakarta Sans** → body dan label. Netral, mudah dibaca kecil.
/// - **JetBrains Mono** → timer, uang, label teknis. Angka berlebar sama.
///
/// Font **dibundel** di `assets/fonts/`, bukan diunduh runtime — lihat
/// `assets/fonts/README.md` dan DEC-002.
class AppTypography {
  AppTypography._();

  static const heading = 'SpaceGrotesk';
  static const body = 'PlusJakartaSans';
  static const mono = 'JetBrainsMono';

  /// Angka berlebar sama. JetBrains Mono memang monospace, tapi fitur ini
  /// tetap disetel supaya benar kalau font-nya diganti nanti.
  static const List<FontFeature> tabular = [FontFeature.tabularFigures()];

  // ── Display & timer (mono) ────────────────────────────────────────

  /// Timer utama di layar detail sesi.
  static const displayLg = TextStyle(
    fontFamily: mono,
    fontSize: 44,
    height: 52 / 44,
    letterSpacing: -0.88,
    fontWeight: FontWeight.w700,
    fontFeatures: tabular,
  );

  /// Timer di kartu station.
  static const timerCard = TextStyle(
    fontFamily: mono,
    fontSize: 34,
    height: 38 / 34,
    letterSpacing: -0.34,
    fontWeight: FontWeight.w700,
    fontFeatures: tabular,
  );

  // ── Headline (Space Grotesk) ──────────────────────────────────────

  static const headlineLg = TextStyle(
    fontFamily: heading,
    fontSize: 32,
    height: 40 / 32,
    letterSpacing: -0.64,
    fontWeight: FontWeight.w700,
  );

  /// Kode station di kartu, judul layar.
  static const headlineMd = TextStyle(
    fontFamily: heading,
    fontSize: 24,
    height: 32 / 24,
    letterSpacing: -0.24,
    fontWeight: FontWeight.w600,
  );

  /// Judul panel, nama merek di sidebar.
  static const headlineSm = TextStyle(
    fontFamily: heading,
    fontSize: 18,
    height: 24 / 18,
    fontWeight: FontWeight.w600,
  );

  // ── Body (Plus Jakarta Sans) ──────────────────────────────────────

  static const bodyLg = TextStyle(
    fontFamily: body,
    fontSize: 16,
    height: 24 / 16,
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
    fontSize: 12,
    height: 16 / 12,
    fontWeight: FontWeight.w400,
  );

  // ── Label (JetBrains Mono) ────────────────────────────────────────
  // Label teknis pakai mono: memberi kesan panel kontrol, dan angka di
  // dalamnya ikut sejajar.

  static const labelLg = TextStyle(
    fontFamily: mono,
    fontSize: 14,
    height: 20 / 14,
    letterSpacing: 0.28,
    fontWeight: FontWeight.w600,
    fontFeatures: tabular,
  );

  static const labelMd = TextStyle(
    fontFamily: mono,
    fontSize: 12,
    height: 16 / 12,
    letterSpacing: 0.48,
    fontWeight: FontWeight.w500,
    fontFeatures: tabular,
  );

  /// Label kecil huruf besar — judul bagian yang tidak boleh bersaing.
  static const labelSm = TextStyle(
    fontFamily: mono,
    fontSize: 11,
    height: 14 / 11,
    letterSpacing: 0.66,
    fontWeight: FontWeight.w500,
    fontFeatures: tabular,
  );

  // ── Uang (mono) ───────────────────────────────────────────────────

  static const moneyLg = TextStyle(
    fontFamily: mono,
    fontSize: 20,
    height: 26 / 20,
    letterSpacing: -0.2,
    fontWeight: FontWeight.w700,
    fontFeatures: tabular,
  );

  static const money = TextStyle(
    fontFamily: mono,
    fontSize: 14,
    height: 20 / 14,
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

/// Tema gelap. Light mode TIDAK dibuat — lihat `UI-UX-SPEC.md` §1.
class AppTheme {
  AppTheme._();

  static ThemeData dark() {
    const scheme = ColorScheme.dark(
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
      brightness: Brightness.dark,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColors.surface,
      canvasColor: AppColors.surface,
      fontFamily: AppTypography.body,
      splashFactory: InkSparkle.splashFactory,

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
        color: AppColors.surfaceHigh,
        thickness: 1,
        space: 1,
      ),

      cardTheme: CardThemeData(
        color: AppColors.surfaceLow,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
      ),

      appBarTheme: AppBarTheme(
        backgroundColor: AppColors.surfaceLowest,
        surfaceTintColor: Colors.transparent,
        foregroundColor: AppColors.onSurface,
        elevation: 0,
        centerTitle: false,
        toolbarHeight: AppSize.headerHeight,
        titleTextStyle: AppTypography.headlineSm.copyWith(
          color: AppColors.onSurface,
        ),
        iconTheme:
            const IconThemeData(color: AppColors.onSurfaceVariant, size: 22),
      ),

      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AppColors.primaryContainer,
          foregroundColor: AppColors.onPrimaryContainer,
          minimumSize: const Size(0, AppSize.minTouchTarget),
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.lg),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
          textStyle: AppTypography.labelLg,
          disabledBackgroundColor: AppColors.surfaceHigh,
          disabledForegroundColor: AppColors.outline,
        ),
      ),

      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(0, AppSize.minTouchTarget),
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          side: const BorderSide(color: AppColors.surfaceHigh),
          foregroundColor: AppColors.onSurface,
          backgroundColor: AppColors.surfaceContainer,
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
          borderSide: const BorderSide(color: AppColors.primaryContainer),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: const BorderSide(color: AppColors.error),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(AppRadius.md),
          borderSide: const BorderSide(color: AppColors.error, width: 2),
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
        backgroundColor: AppColors.surfaceHigh,
        contentTextStyle:
            AppTypography.bodyMd.copyWith(color: AppColors.onSurface),
        behavior: SnackBarBehavior.floating,
        elevation: 0,
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
        color: AppColors.primaryContainer,
        linearTrackColor: AppColors.surfaceHighest,
        linearMinHeight: AppSize.progressBar,
      ),

      tooltipTheme: TooltipThemeData(
        decoration: BoxDecoration(
          color: AppColors.surfaceHigh,
          borderRadius: BorderRadius.circular(AppRadius.md),
          boxShadow: AppShadow.panel,
        ),
        textStyle: AppTypography.bodySm.copyWith(color: AppColors.onSurface),
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.sm + 2,
          vertical: AppSpacing.sm,
        ),
      ),
    );
  }
}
