import 'package:flutter/material.dart';

/// Design tokens — sumber tunggal.
///
/// ## Arah visual: terang, datar, padat
///
/// Acuannya perkakas operasional yang dipakai berjam-jam — Linear, Stripe
/// Dashboard, aplikasi kasir yang benar-benar dipakai — bukan halaman
/// pemasaran.
///
/// Tiga aturan yang menjaganya tidak terlihat seperti template:
///
/// 1. **Lapisan dari nada putih, bukan dari shadow.** Kanvas abu sangat muda,
///    kartu putih, garis setipis mungkin. Shadow hanya untuk yang benar-benar
///    melayang di atas layar (modal, popup).
/// 2. **Satu warna aksen.** Teal dipakai untuk aksi; warna lain hanya status,
///    dan setiap status selalu disertai ikon dan teks.
/// 3. **Radius kecil dan berbeda sesuai peran.** Chrome data 6 px, kartu
///    10 px. Semuanya membulat seragam besar adalah ciri paling cepat terbaca
///    dari UI yang tidak dirancang.
///
/// JANGAN menulis hex mentah di widget. Semua warna lewat file ini.
class AppColors {
  AppColors._();

  // ── Lapisan putih ─────────────────────────────────────────────────
  //
  // Nama token dipertahankan dari tema sebelumnya supaya perubahan ini tidak
  // menyentuh puluhan widget. Perannya yang berubah: pada tema terang,
  // "lowest" adalah chrome yang paling putih, bukan yang paling gelap.

  /// Sidebar, header, footer. Putih bersih — chrome harus terbaca sebagai
  /// bidang tetap, bukan sebagai kartu lain.
  static const surfaceLowest = Color(0xFFFFFFFF);

  /// Kanvas area kerja. Abu sangat muda supaya kartu putih di atasnya punya
  /// tepi tanpa perlu shadow.
  static const surface = Color(0xFFF6F7F9);
  static const background = surface;

  /// Kartu dan panel.
  static const surfaceLow = Color(0xFFFFFFFF);

  /// Bidang cekung: input, tombol sekunder, blok di dalam kartu.
  static const surfaceContainer = Color(0xFFF1F3F5);

  /// Garis tipis. Dipakai di hampir semua tepi kartu.
  static const surfaceHigh = Color(0xFFE3E6EA);

  /// Track bar progres, pembatas yang perlu sedikit lebih terbaca.
  static const surfaceHighest = Color(0xFFE8EBEF);

  /// Hover pada permukaan putih.
  static const surfaceBright = Color(0xFFEDEFF2);

  // ── Teks ──────────────────────────────────────────────────────────

  /// Teks utama. Hampir hitam, bukan hitam murni — hitam penuh pada putih
  /// terasa keras setelah beberapa jam.
  static const onSurface = Color(0xFF15181D);

  /// Teks sekunder. 7,1:1 pada putih.
  static const onSurfaceVariant = Color(0xFF5B6472);

  /// Teks tersier dan placeholder. 3,6:1 — hanya untuk label pendukung,
  /// tidak pernah untuk informasi yang harus dibaca.
  static const outline = Color(0xFF8A939F);

  /// Garis paling halus, pemisah di dalam kartu.
  static const outlineVariant = Color(0xFFD7DCE2);

  // ── Aksen: teal ───────────────────────────────────────────────────
  //
  // Garis keturunan cyan dari `contoh.html`, tapi gelap supaya terbaca di
  // atas putih. Cyan neon pada latar terang tidak bisa memenuhi kontras apa
  // pun — dipaksakan, hasilnya teks yang tidak terbaca.

  /// Teks dan ikon aksen. 4,9:1 pada putih.
  static const primary = Color(0xFF0E7490);

  /// Isian tombol utama dan nav aktif.
  static const primaryContainer = Color(0xFF0E7490);

  static const onPrimaryContainer = Color(0xFFFFFFFF);
  static const onPrimary = Color(0xFFFFFFFF);

  /// Pressed / hover pada aksi utama.
  static const primaryFixedDim = Color(0xFF155E75);

  /// Latar sangat muda untuk area terpilih.
  static const primarySurface = Color(0xFFE8F4F7);

  // ── Hijau: sesi berjalan, keadaan sehat ───────────────────────────

  static const secondary = Color(0xFF047857);
  static const secondaryContainer = Color(0xFF047857);
  static const onSecondary = Color(0xFFFFFFFF);
  static const onSecondaryContainer = Color(0xFFFFFFFF);

  // ── Amber: uang dan hal yang menuntut perhatian ───────────────────

  static const tertiary = Color(0xFF92400E);
  static const tertiaryContainer = Color(0xFFB45309);
  static const tertiaryFixedDim = Color(0xFFB45309);
  static const onTertiary = Color(0xFFFFFFFF);

  // ── Merah ─────────────────────────────────────────────────────────

  static const error = Color(0xFFB91C1C);
  static const errorContainer = Color(0xFFFEE2E2);
  static const onError = Color(0xFFFFFFFF);
  static const onErrorContainer = Color(0xFF7F1D1D);

  // ── Status station ────────────────────────────────────────────────
  //
  // Semua memenuhi 4,5:1 pada putih. Selalu dipakai bersama ikon dan teks —
  // lihat StatusStyle.

  /// Tersedia. Memakai warna aksen: station kosong adalah peluang.
  static const statusAvailable = primary;

  /// Bermain.
  static const statusActive = secondary;

  /// Hampir habis.
  static const statusWarning = Color(0xFFB45309);

  /// Waktu habis.
  static const statusExpired = error;

  /// Menunggu pembayaran. Indigo, sengaja jauh dari amber supaya tidak
  /// tertukar dengan "hampir habis" — keduanya menuntut tindakan berbeda.
  static const statusPendingPayment = Color(0xFF4338CA);

  /// Checkout. Satu-satunya pemakaian ungu di seluruh aplikasi.
  static const statusCheckout = Color(0xFF7E22CE);

  /// Offline, maintenance, order dibatalkan.
  ///
  /// Slate-600, bukan slate-500. Order yang dibatalkan dirender di atas
  /// `surfaceContainer`, dan di sana slate-500 hanya mencapai 4,28:1 —
  /// gagal. Yang membuat status ini terasa tenang adalah hue-nya yang
  /// nyaris tanpa saturasi, bukan kontrasnya yang rendah.
  static const statusOffline = Color(0xFF475569);

  // ── Lapisan transparan ────────────────────────────────────────────
  //
  // Pada tema terang, lapisan interaksi menggelapkan — bukan menerangkan.

  static const overlaySubtle = Color(0x0A000000);
  static const overlayMedium = Color(0x14000000);

  /// Scrim modal.
  static const scrim = Color(0x66000000);
}

/// Spacing. Tetap dari tema sebelumnya — ritmenya sudah benar.
class AppSpacing {
  AppSpacing._();

  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 14.0;
  static const lg = 20.0;
  static const xl = 28.0;
  static const xxl = 40.0;

  static const gutter = 16.0;
  static const gutterLg = 24.0;
}

/// Radius.
///
/// Dirapatkan dari tema gelap. Perkakas operasional yang dipercaya terlihat
/// presisi, bukan lembut; dan radius seragam besar di setiap elemen adalah
/// ciri yang paling cepat terbaca dari UI yang tidak dirancang.
class AppRadius {
  AppRadius._();

  /// Badge, tombol kecil, chrome data.
  static const sm = 4.0;

  /// Tombol, input, nav item.
  static const md = 6.0;

  /// Kartu dan panel.
  static const lg = 10.0;

  /// Modal dan bottom sheet.
  static const modal = 12.0;

  /// Hanya untuk chip filter dan titik status — bukan untuk tombol.
  static const pill = 999.0;
}

class AppSize {
  AppSize._();

  /// Material: minimum 48dp. Tablet dipakai berdiri & terburu-buru.
  static const minTouchTarget = 48.0;

  static const headerHeight = 60.0;

  static const sidebarWidth = 268.0;
  static const sidebarRailWidth = 72.0;
  static const sidebarExpandBreakpoint = 1040.0;

  /// Di bawah lebar ini, penanda di header (status koneksi, DATA CONTOH)
  /// tampil sebagai ikon saja.
  ///
  /// Keduanya tetap ada — hanya labelnya yang dilepas. Yang dihindari di sini
  /// bukan hanya overflow, tapi juga memaksa judul section menyusut demi
  /// teks penanda; judul yang terpotong lebih merugikan.
  static const headerCompactBreakpoint = 620.0;

  /// Penanda nav aktif di tepi kiri. Menggantikan glow dari tema gelap:
  /// pada latar terang, glow terbaca sebagai hiasan, bukan sebagai keadaan.
  static const navIndicator = 3.0;

  static const progressBar = 5.0;
  static const statusRail = 3.0;

  /// Tinggi minimum kartu station pada skala teks normal.
  static const stationCardMinHeight = 264.0;
  static const stationCardMinWidth = 300.0;

  /// Tinggi minimum kartu yang mengikuti skala teks sistem.
  ///
  /// Kartu memuat baris padat — countdown, jam mulai/selesai, nama customer,
  /// empat tombol aksi — dan semuanya tumbuh bersama skala teks. Menahan
  /// tingginya tetap 264 membuat isinya overflow pada 1,3x (UI-UX-SPEC §10
  /// mewajibkan tahan sampai situ).
  ///
  /// Jadi kartunya yang tumbuh dan grid-nya yang di-scroll — pilihan yang
  /// sama dengan yang sudah dipakai saat layarnya pendek. "Enam station tanpa
  /// scroll" berlaku pada skala teks normal; operator yang memperbesar teks
  /// memilih keterbacaan di atas kepadatan, dan itu pilihan yang sah.
  ///
  /// Dibatasi 1,4x: di atas itu kartunya jadi terlalu tinggi untuk berguna,
  /// dan teksnya tetap membesar — hanya tingginya yang berhenti mengikuti.
  static double stationCardMinHeightFor(double textScale) =>
      stationCardMinHeight * textScale.clamp(1.0, 1.4);
}

/// Shadow — dipakai sangat hemat.
///
/// Pada tema terang, shadow di setiap kartu adalah penanda paling jelas dari
/// UI yang tidak dirancang. Kartu dipisahkan dari kanvas oleh **nada dan
/// garis**; shadow disimpan untuk yang benar-benar melayang.
class AppShadow {
  AppShadow._();

  /// Kartu di atas kanvas: nyaris tidak terlihat, hanya memberi tepi bawah.
  /// Pemisah utamanya tetap garis.
  static const card = <BoxShadow>[
    BoxShadow(color: Color(0x08000000), blurRadius: 2, offset: Offset(0, 1)),
  ];

  /// Chrome yang menempel di tepi layar — sidebar, header.
  static const panel = <BoxShadow>[
    BoxShadow(color: Color(0x0D000000), blurRadius: 3, offset: Offset(0, 1)),
  ];

  /// Modal, bottom sheet, popup. Di sini shadow memang bertugas.
  static const modal = <BoxShadow>[
    BoxShadow(color: Color(0x1F000000), blurRadius: 24, offset: Offset(0, 8)),
    BoxShadow(color: Color(0x14000000), blurRadius: 6, offset: Offset(0, 2)),
  ];
}

class AppMotion {
  AppMotion._();

  static const fast = Duration(milliseconds: 120);
  static const normal = Duration(milliseconds: 180);
  static const slow = Duration(milliseconds: 240);

  /// Animasi keluar ~60–70% durasi masuk.
  static const exit = Duration(milliseconds: 110);

  static const easeOut = Curves.easeOutCubic;
  static const easeIn = Curves.easeInCubic;
}

/// Dekorasi yang dipakai berulang.
///
/// Dikumpulkan di sini supaya tepi kartu konsisten di seluruh aplikasi.
/// Sebelumnya setiap layar menyusun `BoxDecoration` sendiri, dan pada tema
/// terang perbedaan satu nada garis langsung terlihat.
class AppDecoration {
  AppDecoration._();

  /// Kartu putih di atas kanvas.
  static BoxDecoration card({double radius = AppRadius.lg}) => BoxDecoration(
        color: AppColors.surfaceLow,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: AppColors.surfaceHigh),
        boxShadow: AppShadow.card,
      );

  /// Bidang cekung di dalam kartu: ringkasan, blok customer, track.
  static BoxDecoration inset({double radius = AppRadius.md}) => BoxDecoration(
        color: AppColors.surfaceContainer,
        borderRadius: BorderRadius.circular(radius),
      );

  /// Kartu dalam keadaan terpilih.
  static BoxDecoration selected({double radius = AppRadius.lg}) =>
      BoxDecoration(
        color: AppColors.primarySurface,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: AppColors.primary),
      );
}
