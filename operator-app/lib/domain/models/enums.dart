/// Enum ditranskrip dari `docs/contracts/API.md`.
///
/// ATURAN: nilai wire pakai UPPER_SNAKE_CASE. Setiap enum punya `unknown`
/// sebagai fallback — nilai baru dari server TIDAK BOLEH membuat app crash
/// (kontrak §11: client wajib menangani kegagalan dengan baik).
library;

T _parse<T>(
  String? wire,
  Map<String, T> map,
  T fallback,
) =>
    wire == null ? fallback : (map[wire] ?? fallback);

/// `session.status` — kontrak §7.
/// Catatan: TIDAK ada `AVAILABLE` di sini. Itu status station, bukan session.
enum SessionStatus {
  pendingPayment('PENDING_PAYMENT'),
  active('ACTIVE'),
  warning('WARNING'),
  expired('EXPIRED'),
  checkout('CHECKOUT'),
  completed('COMPLETED'),
  cancelled('CANCELLED'),
  unknown('UNKNOWN');

  const SessionStatus(this.wire);
  final String wire;

  static SessionStatus parse(String? w) => _parse(w, _map, SessionStatus.unknown);
  static final _map = {for (final v in SessionStatus.values) v.wire: v};

  /// Session yang masih memakai station.
  bool get occupiesStation => const {
        SessionStatus.pendingPayment,
        SessionStatus.active,
        SessionStatus.warning,
        SessionStatus.expired,
        SessionStatus.checkout,
      }.contains(this);

  /// Timer boleh ditampilkan.
  bool get hasTimer => const {
        SessionStatus.active,
        SessionStatus.warning,
        SessionStatus.expired,
      }.contains(this);
}

/// `session.mode` — kontrak §7.
enum SessionMode {
  prepaid('PREPAID'),
  postpaid('POSTPAID'),
  unknown('UNKNOWN');

  const SessionMode(this.wire);
  final String wire;

  static SessionMode parse(String? w) => _parse(w, _map, SessionMode.unknown);
  static final _map = {for (final v in SessionMode.values) v.wire: v};

  String get label => switch (this) {
        SessionMode.prepaid => 'Prepaid',
        SessionMode.postpaid => 'Postpaid',
        SessionMode.unknown => '-',
      };
}

/// `station.status` — master data, BUKAN status sesi. Kontrak §6.
enum StationMasterStatus {
  active('ACTIVE'),
  maintenance('MAINTENANCE'),
  disabled('DISABLED'),
  unknown('UNKNOWN');

  const StationMasterStatus(this.wire);
  final String wire;

  static StationMasterStatus parse(String? w) =>
      _parse(w, _map, StationMasterStatus.unknown);
  static final _map = {for (final v in StationMasterStatus.values) v.wire: v};
}

/// `session_item.type` — kontrak §7.
enum SessionItemType {
  rental('RENTAL'),
  fnb('FNB'),
  extend('EXTEND'),
  discount('DISCOUNT'),
  adjustment('ADJUSTMENT'),
  unknown('UNKNOWN');

  const SessionItemType(this.wire);
  final String wire;

  static SessionItemType parse(String? w) =>
      _parse(w, _map, SessionItemType.unknown);
  static final _map = {for (final v in SessionItemType.values) v.wire: v};

  String get label => switch (this) {
        SessionItemType.rental => 'Rental',
        SessionItemType.fnb => 'F&B',
        SessionItemType.extend => 'Extend',
        SessionItemType.discount => 'Diskon',
        SessionItemType.adjustment => 'Penyesuaian',
        SessionItemType.unknown => 'Lain-lain',
      };
}

/// Kontrak §7 — hanya dua metode di v1 (PRD §21).
enum PaymentMethod {
  cash('CASH'),
  qrisStatic('QRIS_STATIC'),
  unknown('UNKNOWN');

  const PaymentMethod(this.wire);
  final String wire;

  static PaymentMethod parse(String? w) =>
      _parse(w, _map, PaymentMethod.unknown);
  static final _map = {for (final v in PaymentMethod.values) v.wire: v};

  String get label => switch (this) {
        PaymentMethod.cash => 'Tunai',
        PaymentMethod.qrisStatic => 'QRIS',
        PaymentMethod.unknown => '-',
      };

  /// QRIS wajib nomor referensi — kontrak: PAYMENT_REFERENCE_REQUIRED.
  bool get requiresReference => this == PaymentMethod.qrisStatic;
}

/// `fnb_order.status` — kontrak §8.
enum FnbOrderStatus {
  pending('PENDING'),
  processing('PROCESSING'),
  ready('READY'),
  delivered('DELIVERED'),
  cancelled('CANCELLED'),
  unknown('UNKNOWN');

  const FnbOrderStatus(this.wire);
  final String wire;

  static FnbOrderStatus parse(String? w) =>
      _parse(w, _map, FnbOrderStatus.unknown);
  static final _map = {for (final v in FnbOrderStatus.values) v.wire: v};

  String get label => switch (this) {
        FnbOrderStatus.pending => 'Baru',
        FnbOrderStatus.processing => 'Diproses',
        FnbOrderStatus.ready => 'Siap',
        FnbOrderStatus.delivered => 'Diantar',
        FnbOrderStatus.cancelled => 'Dibatalkan',
        FnbOrderStatus.unknown => '-',
      };

  /// Transisi sah: PENDING -> PROCESSING -> READY -> DELIVERED.
  /// Server tetap yang menegakkan (FNB_STATUS_TRANSITION_INVALID);
  /// ini hanya untuk menonaktifkan tombol di UI.
  FnbOrderStatus? get next => switch (this) {
        FnbOrderStatus.pending => FnbOrderStatus.processing,
        FnbOrderStatus.processing => FnbOrderStatus.ready,
        FnbOrderStatus.ready => FnbOrderStatus.delivered,
        _ => null,
      };

  /// Kontrak §8: cancel hanya dari `PENDING` atau `PROCESSING`.
  /// Order yang sudah siap atau diantar tidak bisa dibatalkan — barangnya
  /// sudah dibuat. UI memakai ini untuk menyembunyikan tombol batal, supaya
  /// operator tidak pernah menemui error yang bisa dicegah.
  bool get canCancel => const {
        FnbOrderStatus.pending,
        FnbOrderStatus.processing,
      }.contains(this);
}

/// `device.status` — kontrak §6.
enum DeviceStatus {
  online('ONLINE'),
  offline('OFFLINE'),
  unknown('UNKNOWN');

  const DeviceStatus(this.wire);
  final String wire;

  static DeviceStatus parse(String? w) => _parse(w, _map, DeviceStatus.unknown);
  static final _map = {for (final v in DeviceStatus.values) v.wire: v};
}

/// Status yang DITAMPILKAN di kartu station.
///
/// Ini konsep UI, bukan field dari server: gabungan "station kosong"
/// (`session == null` -> available) dengan status session. Dipakai supaya
/// dashboard punya satu sumber untuk warna + ikon + label.
enum StationViewStatus {
  available,
  pendingPayment,
  active,
  warning,
  expired,
  checkout,
  offline,
  maintenance;

  static StationViewStatus from({
    required StationMasterStatus master,
    SessionStatus? session,
  }) {
    if (master == StationMasterStatus.maintenance ||
        master == StationMasterStatus.disabled) {
      return StationViewStatus.maintenance;
    }
    return switch (session) {
      null => StationViewStatus.available,
      SessionStatus.pendingPayment => StationViewStatus.pendingPayment,
      SessionStatus.active => StationViewStatus.active,
      SessionStatus.warning => StationViewStatus.warning,
      SessionStatus.expired => StationViewStatus.expired,
      SessionStatus.checkout => StationViewStatus.checkout,
      _ => StationViewStatus.available,
    };
  }
}
