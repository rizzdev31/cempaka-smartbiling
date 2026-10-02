import 'package:flutter/material.dart';

import '../../core/brand.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/tokens.dart';

/// Penanda merek di sidebar: logo pelanggan + namanya.
///
/// Semua teks dan aset diambil dari [Brand] — tidak ada string merek yang
/// ditulis di sini. Lihat OD-012: nama dan logo menyesuaikan per pelanggan,
/// jadi widget ini harus tetap benar tanpa diubah saat mereknya berganti.
class BrandMark extends StatelessWidget {
  const BrandMark({super.key, this.showName = true, this.size = 46});

  final bool showName;

  /// Tinggi alas logo. Lebarnya mengikuti, karena emblem merek umumnya
  /// melebar — dipaksa ke kotak persegi, logo di dalamnya justru mengecil.
  final double size;

  @override
  Widget build(BuildContext context) {
    final mark = _LogoPlate(size: size);

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

/// Logo di atas alasnya.
///
/// Alas gelap dipakai kalau [Brand.logoNeedsDarkPlate] — lihat alasannya di
/// sana. Kalau belum ada aset logo sama sekali, jatuh ke monogram supaya
/// pemasangan merek baru tidak pernah menampilkan kotak kosong.
class _LogoPlate extends StatelessWidget {
  const _LogoPlate({required this.size});

  final double size;

  @override
  Widget build(BuildContext context) {
    final asset = Brand.logoAsset;
    final plated = Brand.logoNeedsDarkPlate;

    // Emblem merek melebar, jadi alasnya ikut melebar. Monogram tidak —
    // satu huruf di alas selebar ini akan terlihat hilang di tengah.
    final width = asset == null ? size : size * 1.5;

    return Container(
      width: width,
      height: size,
      decoration: BoxDecoration(
        color: plated ? AppColors.brandPlate : Colors.transparent,
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      alignment: Alignment.center,
      child: asset == null
          ? Text(
              Brand.monogram,
              style: TextStyle(
                fontFamily: AppTypography.heading,
                fontSize: size * 0.5,
                fontWeight: FontWeight.w700,
                color: plated ? Colors.white : AppColors.primary,
                height: 1,
              ),
            )
          : Padding(
              padding: EdgeInsets.symmetric(
                horizontal: size * 0.16,
                vertical: size * 0.18,
              ),
              child: Image.asset(
                asset,
                fit: BoxFit.contain,
                // Logo merek dipakai di ukuran kecil dan dasarnya besar;
                // tanpa filter yang baik, garis tipisnya pecah.
                filterQuality: FilterQuality.medium,
                semanticLabel: Brand.fullName,
              ),
            ),
    );
  }
}
