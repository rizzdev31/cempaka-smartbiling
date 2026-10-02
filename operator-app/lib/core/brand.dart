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

  /// Nama yang tampil di header. Pendek — header tablet sempit.
  static const String appName = 'Cempaka Billing';

  /// Nama panjang untuk splash, Tentang, dan struk.
  static const String fullName = 'Cempaka Smart Billing';

  /// Inisial untuk logo mark sementara, sebelum ada aset logo.
  static const String monogram = 'C';

  /// Atribusi naungan. Tetap tampil walau merek pelanggan berbeda (OD-012).
  static const String poweredBy = 'Cempaka Smart Billing';

  static const String tagline = 'Billing & Operasional Rental';

  /// Belum ada aset logo. Kalau sudah ada, isi path-nya dan
  /// `BrandMark` otomatis memakai gambar daripada monogram.
  static const String? logoAsset = null;
}
