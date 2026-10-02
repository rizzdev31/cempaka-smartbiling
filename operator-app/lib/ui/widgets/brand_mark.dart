import 'package:flutter/material.dart';

import '../../core/brand.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';

/// Logo mark + nama aplikasi untuk header.
///
/// Semua teks diambil dari [Brand] — tidak ada string merek yang ditulis
/// di sini. Lihat OD-012: nama dan logo akan menyesuaikan per pengguna,
/// jadi widget ini harus tetap benar tanpa diubah saat mereknya berganti.
///
/// Selama [Brand.logoAsset] masih `null`, dipakai monogram. Begitu ada
/// aset logo, widget ini otomatis memakai gambarnya.
class BrandMark extends StatelessWidget {
  const BrandMark({super.key, this.showName = true, this.size = 34});

  final bool showName;
  final double size;

  @override
  Widget build(BuildContext context) {
    final mark = Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.primary, AppColors.primaryContainer],
        ),
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      alignment: Alignment.center,
      child: Brand.logoAsset != null
          ? Padding(
              padding: EdgeInsets.all(size * 0.18),
              child: Image.asset(Brand.logoAsset!, fit: BoxFit.contain),
            )
          : Text(
              Brand.monogram,
              style: TextStyle(
                fontFamily: AppTypography.heading,
                fontSize: size * 0.5,
                fontWeight: FontWeight.w700,
                color: AppColors.onPrimary,
                height: 1,
              ),
            ),
    );

    if (!showName) return mark;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        mark,
        const SizedBox(width: AppSpacing.sm + 2),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text(
              Brand.appName,
              style: AppTypography.headlineSm.copyWith(
                color: AppColors.onSurface,
                height: 1.1,
              ),
            ),
            Text(
              Brand.tagline,
              style: AppTypography.bodySm.copyWith(
                color: AppColors.outline,
                fontSize: 11,
                height: 1.2,
              ),
            ),
          ],
        ),
      ],
    );
  }
}
