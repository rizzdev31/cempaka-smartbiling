// Mengunci pemasangan merek pelanggan di shell.
//
// Semuanya lewat `Brand` (OD-012). Test ini menjaga dua hal yang mudah
// hilang tanpa disadari: logo yang tampil terlalu kecil, dan string merek
// yang kembali ditulis langsung di widget.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:operator_app/core/brand.dart';
import 'package:operator_app/core/theme/app_theme.dart';
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

    testWidgets('logo setinggi yang diminta dan melebar apa adanya',
        (tester) async {
      await tester.pumpWidget(_wrap(const BrandMark(size: 44)));

      final img = tester.getSize(find.byType(Image));
      expect(img.height, 44);

      // Lebarnya mengikuti rasio asli logo, tidak dipaksa ke kotak persegi —
      // dipaksa persegi, logo di dalamnya menyusut sampai setengahnya.
      expect(img.width, greaterThan(img.height));
    });

    testWidgets('logo ditempel apa adanya, tanpa alas atau bingkai',
        (tester) async {
      await tester.pumpWidget(_wrap(const BrandMark()));

      // Tidak ada bidang berwarna atau bergaris yang membungkus logo.
      // Bentuk logonya sendiri yang jadi bentuknya.
      final wrappers = find.ancestor(
        of: find.byType(Image),
        matching: find.byType(DecoratedBox),
      );
      for (final box in tester.widgetList<DecoratedBox>(wrappers)) {
        final d = box.decoration;
        if (d is BoxDecoration) {
          expect(d.color, anyOf(isNull, const Color(0x00000000)));
          expect(d.border, isNull);
          expect(d.gradient, isNull);
        }
      }
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

    test('penanda memakai emblem, bukan lockup penuh', () {
      // Lockup penuh memuat wordmark-nya sendiri; dipakai di sidebar, nama
      // merek akan tampil dua kali.
      expect(Brand.logoAsset, isNot(Brand.logoFullAsset));
      expect(Brand.logoAsset, contains('mark'));
    });
  });
}
