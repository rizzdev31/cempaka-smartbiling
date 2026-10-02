/// Identitas merek — **satu-satunya** tempat nama dan atribusi ditulis.
///
/// ATURAN: jangan pernah menulis nama merek langsung di widget.
/// Selalu lewat file ini.
///
/// Alasannya ada di OD-012: aplikasi akan dijual ke beberapa pengguna dengan
/// nama dan logo menyesuaikan, tetap di bawah naungan Cempaka Smart Billing.
/// Dengan semua string merek terkumpul di sini, mengubahnya nanti berarti
/// mengubah satu file — bukan berburu string di puluhan widget.
///
/// Ini **bukan** implementasi white-label. Keputusan model-nya
/// (per-instance vs multi-tenant) masih OD-012 dan belum diambil.
/// Nanti kelas ini yang diisi dari konfigurasi, bukan konstanta.
library;

class Brand {
  Brand._();

  /// Nama penuh pelanggan — judul jendela, Tentang, struk.
  static const String appName = 'Amor Gaming Space';
  static const String fullName = 'Amor Gaming Space';

  /// Dua baris untuk penanda di sidebar.
  ///
  /// Dipisah karena logo aslinya memang dikunci dua baris (AMOR di atas,
  /// GAMING SPACE di bawah). Ditulis satu baris, nama sepanjang ini akan
  /// terpotong di sidebar 268 px — dua baris justru memberi ruang untuk
  /// membuatnya lebih besar, bukan lebih kecil.
  static const String markTitle = 'Amor';
  static const String markSubtitle = 'Gaming Space';

  /// Inisial, dipakai hanya kalau [logoAsset] kosong.
  static const String monogram = 'A';

  /// Atribusi naungan. Tetap tampil walau merek pelanggan berbeda (OD-012).
  static const String poweredBy = 'Cempaka Smart Billing';

  /// Bentuk yang tampil di footer.
  static const String poweredByLabel = 'Powered by $poweredBy';

  static const String tagline = 'Billing & Operasional Rental';

  /// Nama aplikasi di televisi, sebagaimana operator melihatnya di layar TV.
  ///
  /// Masih memakai nama naungan: APK TV belum ikut di-rebrand. Kalau nanti
  /// ikut, cukup ubah di sini (OD-012).
  static const String tvAppName = 'Cempaka TV';

  /// Versi aplikasi yang ditampilkan di footer. Sinkron dengan
  /// `version` di `pubspec.yaml`.
  static const String version = '0.1.0';

  /// Penanda sidebar — **emblem saja**, tanpa wordmark.
  ///
  /// Wordmark-nya sengaja dipotong: di sebelahnya sudah ada nama merek
  /// sebagai teks, dan dua-duanya bersamaan jadi berulang.
  ///
  /// Aset ini juga dipangkas dari kiriman asli yang 62%-nya ruang kosong.
  /// Tanpa pemangkasan itu, logo tampil jauh lebih kecil dari ruang yang
  /// diberikan padanya.
  ///
  /// Getter, bukan konstanta: tipenya harus tetap nullable supaya jalur
  /// monogram di `BrandMark` tidak jadi kode mati. Pelanggan berikutnya bisa
  /// saja dipasang sebelum logonya tersedia.
  static String? get logoAsset => 'assets/images/logo-amor-mark.png';

  /// Lockup penuh (emblem + wordmark) untuk splash dan Tentang nanti.
  static const String logoFullAsset = 'assets/images/logo-amor-full.png';
}
