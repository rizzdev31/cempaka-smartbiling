// Mengunci pemasangan merek pelanggan di shell.
//
// Semuanya lewat `Brand` (OD-012). Test ini menjaga dua hal yang mudah
// hilang tanpa disadari: logo yang tampil terlalu kecil, dan string merek
// yang kembali ditulis langsung di widget.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:operator_app/core/brand.dart';
import 'package:operator_app/core/theme/app_theme.dart';
import 'package:operator_app/core/theme/tokens.dart';
import 'package:operator_app/ui/widgets/brand_mark.dart';

Widget _wrap(Widget child) => MaterialApp(
      theme: AppTheme.light(),
      home: Scaffold(body: Center(child: child)),
    );

void main() {
  group('BrandMark', () {
    testWidgets('memakai aset logo, bukan monogram', (tester) async {
      await tester.pumpWidget(_wrap(const BrandMark()));

      expect(find.byType(Image), findsOneWidget);
      expect(find.text(Brand.monogram), findsNothing);

      final image = tester.widget<Image>(find.byType(Image));
      expect((image.image as AssetImage).assetName, Brand.logoAsset);
    });

    testWidgets('nama merek tampil dua baris', (tester) async {
      await tester.pumpWidget(_wrap(const BrandMark()));

      expect(find.text(Brand.markTitle), findsOneWidget);
      expect(find.text(Brand.markSubtitle.toUpperCase()), findsOneWidget);
    });

    testWidgets('logo tidak tampil kecil', (tester) async {
      await tester.pumpWidget(_wrap(const BrandMark(size: 46)));

      // Alasnya melebar mengikuti bentuk emblem; dipaksa persegi, logo di
      // dalamnya menyusut sampai setengahnya.
      final plate = tester.getSize(
        find.ancestor(
          of: find.byType(Image),
          matching: find.byType(Container),
        ).first,
      );
      expect(plate.height, 46);
      expect(plate.width, greaterThan(plate.height));

      // Gambar itu sendiri harus mengisi sebagian besar alasnya.
      final img = tester.getSize(find.byType(Image));
      expect(img.width, greaterThan(plate.width * 0.6));
    });

    testWidgets('tanpa nama, hanya alas logo yang tampil', (tester) async {
      await tester.pumpWidget(_wrap(const BrandMark(showName: false)));

      expect(find.byType(Image), findsOneWidget);
      expect(find.text(Brand.markTitle), findsNothing);
    });
  });

  group('Brand', () {
    test('atribusi naungan tetap ada dan berbentuk "Powered by"', () {
      // Nama pelanggan boleh berganti; atribusinya tidak ikut hilang.
      expect(Brand.poweredBy, 'Cempaka Smart Billing');
      expect(Brand.poweredByLabel, 'Powered by Cempaka Smart Billing');
      expect(Brand.poweredByLabel.contains(Brand.poweredBy), isTrue);
    });

    test('nama dua baris menyusun nama penuh', () {
      // Kalau salah satunya diubah tanpa yang lain, sidebar akan menampilkan
      // nama yang berbeda dari judul aplikasi.
      expect('${Brand.markTitle} ${Brand.markSubtitle}', Brand.fullName);
    });

    test('logo yang terang menuntut alas gelap', () {
      // Logo kiriman 60% nyaris putih; tanpa alas, hilang di chrome putih.
      expect(Brand.logoNeedsDarkPlate, isTrue);
      expect(AppColors.brandPlate.computeLuminance(), lessThan(0.05));
    });
  });
}
