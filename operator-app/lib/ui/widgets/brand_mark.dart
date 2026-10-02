import 'package:flutter/material.dart';

import '../../core/brand.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';

/// Penanda merek di sidebar: logo pelanggan + namanya.
///
/// Logo ditempel apa adanya — tanpa alas, tanpa bingkai, tanpa kotak warna.
/// Bentuk logonya sendiri yang jadi bentuknya.
///
/// Semua teks dan aset diambil dari [Brand] — tidak ada string merek yang
/// ditulis di sini. Lihat OD-012: nama dan logo menyesuaikan per pelanggan,
/// jadi widget ini harus tetap benar tanpa diubah saat mereknya berganti.
class BrandMark extends StatelessWidget {
  const BrandMark({super.key, this.showName = true, this.size = 44});

  final bool showName;

  /// Tinggi logo. Lebarnya mengikuti bentuk aslinya — logo merek jarang
  /// persegi, dan memaksanya ke kotak membuatnya tampil lebih kecil.
  final double size;

  @override
  Widget build(BuildContext context) {
    final mark = _Logo(size: size);

    if (!showName) return mark;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        mark,
        const SizedBox(width: AppSpacing.sm + 2),
        // Teks merek HARUS boleh menyusut.
        //
        // Tanpa Flexible, Column ini meminta lebar alaminya dan overflow
        // begitu nama pelanggan sedikit lebih panjang dari ruang sidebar.
        // OD-012 memastikan panjang itu tidak bisa ditebak dari sini.
        Flexible(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                Brand.markTitle,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: AppTypography.headlineMd.copyWith(
                  color: AppColors.onSurface,
                  height: 1.05,
                ),
              ),
              Text(
                Brand.markSubtitle.toUpperCase(),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                // Font heading, bukan `labelSm`.
                //
                // `labelSm` memakai JetBrains Mono — itu font untuk angka dan
                // label teknis (UI-UX-SPEC §3). Nama merek bukan keduanya,
                // dan mono membuatnya terbaca seperti kode. Space Grotesk
                // geometris, jauh lebih dekat ke bentuk logonya.
                style: const TextStyle(
                  fontFamily: AppTypography.heading,
                  fontSize: 11,
                  height: 1.25,
                  fontWeight: FontWeight.w600,
                  color: AppColors.primary,
                  // Jarak huruf meniru kunci pada logo aslinya, dan membuat
                  // baris kedua terbaca sebagai bagian dari merek — bukan
                  // sebagai keterangan yang menggantung.
                  letterSpacing: 1.6,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/// Logo apa adanya.
///
/// Kalau belum ada aset logo sama sekali, jatuh ke monogram supaya pemasangan
/// merek baru tidak pernah menampilkan ruang kosong.
class _Logo extends StatelessWidget {
  const _Logo({required this.size});

  final double size;

  @override
  Widget build(BuildContext context) {
    final asset = Brand.logoAsset;

    if (asset == null) {
      return SizedBox(
        width: size,
        height: size,
        child: Center(
          child: Text(
            Brand.monogram,
            style: TextStyle(
              fontFamily: AppTypography.heading,
              fontSize: size * 0.72,
              fontWeight: FontWeight.w700,
              color: AppColors.primary,
              height: 1,
            ),
          ),
        ),
      );
    }

    return Image.asset(
      asset,
      height: size,
      // Lebar dibiarkan mengikuti rasio asli logo.
      fit: BoxFit.contain,
      // Logo dipakai jauh lebih kecil dari ukuran dasarnya; tanpa filter
      // yang baik, garis-garis tipisnya pecah.
      filterQuality: FilterQuality.medium,
      semanticLabel: Brand.fullName,
    );
  }
}
