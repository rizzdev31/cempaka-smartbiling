/// Model agen TV.
///
/// Bentuknya mengikuti response `GET /health` milik `tv-agent`. Sengaja
/// dipisah dari model billing: agen TV adalah perangkat, bukan entitas
/// bisnis, dan saat kontrol langsung diganti Reverb (DEC-015) model ini
/// menyusut — bukan hilang.
library;

/// Tingkat kiosk yang dilaporkan agen.
enum KioskTier {
  /// Fullscreen + foreground service. Customer masih bisa keluar lewat HOME.
  soft('SOFT', 'Kiosk lunak'),

  /// Lock Task aktif — benar-benar tidak bisa keluar.
  lockTask('LOCK_TASK', 'Kiosk terkunci'),

  unknown('UNKNOWN', 'Tidak diketahui');

  const KioskTier(this.wire, this.label);
  final String wire;
  final String label;

  static KioskTier parse(String? w) =>
      values.firstWhere((v) => v.wire == w, orElse: () => KioskTier.unknown);
}

/// Hasil `GET /health` dari satu agen.
class TvAgentInfo {
  const TvAgentInfo({
    required this.baseUrl,
    required this.deviceUid,
    required this.paired,
    required this.requiresPairing,
    required this.pairingLocked,
    required this.kioskTier,
    this.stationCode,
    this.manufacturer,
    this.model,
    this.androidRelease,
    this.sdkInt,
    this.isTelevision = false,
    this.isDeviceOwner = false,
    this.appVersion,
    this.timeSynced = false,
    this.secondsSinceSync,
  });

  /// `http://192.168.0.77:8787`
  final String baseUrl;

  /// ID stabil agen. Dipakai mengenali TV yang sama walau IP-nya berubah.
  final String deviceUid;

  final bool paired;
  final bool requiresPairing;
  final bool pairingLocked;
  final KioskTier kioskTier;

  /// Station tempat agen ini sudah dipasangkan. Null kalau belum.
  final String? stationCode;

  final String? manufacturer;
  final String? model;
  final String? androidRelease;
  final int? sdkInt;
  final bool isTelevision;
  final bool isDeviceOwner;
  final String? appVersion;
  final bool timeSynced;
  final int? secondsSinceSync;

  /// Alamat tanpa skema, untuk ditampilkan. `192.168.0.77:8787`
  String get address => baseUrl.replaceFirst(RegExp(r'^https?://'), '');

  String get hardwareLabel {
    final parts = [manufacturer, model]
        .whereType<String>()
        .where((s) => s.isNotEmpty && s != '?');
    if (parts.isEmpty) return 'Perangkat belum teridentifikasi';
    return parts.join(' ');
  }

  String get osLabel =>
      androidRelease == null ? 'Android ?' : 'Android $androidRelease';

  factory TvAgentInfo.fromJson(String baseUrl, Map<String, dynamic> j) {
    final device = (j['device'] as Map<String, dynamic>?) ?? const {};
    final time = (j['time'] as Map<String, dynamic>?) ?? const {};

    return TvAgentInfo(
      baseUrl: baseUrl,
      deviceUid: j['device_uid'] as String? ?? '?',
      paired: j['paired'] as bool? ?? false,
      requiresPairing: j['requires_pairing'] as bool? ?? true,
      pairingLocked: j['pairing_locked'] as bool? ?? false,
      stationCode: j['station_code'] as String?,
      kioskTier: KioskTier.parse(device['kiosk_tier'] as String?),
      manufacturer: device['manufacturer'] as String?,
      model: device['model'] as String?,
      androidRelease: device['android_release'] as String?,
      sdkInt: (device['sdk_int'] as num?)?.toInt(),
      isTelevision: device['is_television'] as bool? ?? false,
      isDeviceOwner: device['is_device_owner'] as bool? ?? false,
      appVersion: device['app_version'] as String?,
      timeSynced: time['synced'] as bool? ?? false,
      secondsSinceSync: (time['seconds_since_sync'] as num?)?.toInt(),
    );
  }
}

/// Pasangan station ↔ agen TV yang sudah ditetapkan operator.
///
/// Disimpan di perangkat operator. Token device ada di sini karena agen
/// menerbitkannya saat pairing; nanti (Tahap 2) penerbitnya Laravel dan
/// kelas ini tinggal menyimpan referensi.
class TvLink {
  const TvLink({
    required this.stationId,
    required this.stationCode,
    required this.deviceUid,
    required this.deviceToken,
    required this.baseUrl,
    this.pairedAt,
  });

  final String stationId;
  final String stationCode;
  final String deviceUid;
  final String deviceToken;

  /// Alamat terakhir yang diketahui. Bisa berubah kalau DHCP memberi IP lain;
  /// [deviceUid] yang menentukan ini TV yang sama atau bukan.
  final String baseUrl;

  final DateTime? pairedAt;

  TvLink copyWith({String? baseUrl}) => TvLink(
        stationId: stationId,
        stationCode: stationCode,
        deviceUid: deviceUid,
        deviceToken: deviceToken,
        baseUrl: baseUrl ?? this.baseUrl,
        pairedAt: pairedAt,
      );

  Map<String, dynamic> toJson() => {
        'station_id': stationId,
        'station_code': stationCode,
        'device_uid': deviceUid,
        'device_token': deviceToken,
        'base_url': baseUrl,
        'paired_at': pairedAt?.toIso8601String(),
      };

  factory TvLink.fromJson(Map<String, dynamic> j) => TvLink(
        stationId: j['station_id'] as String,
        stationCode: j['station_code'] as String,
        deviceUid: j['device_uid'] as String,
        deviceToken: j['device_token'] as String,
        baseUrl: j['base_url'] as String,
        pairedAt: (j['paired_at'] as String?) == null
            ? null
            : DateTime.tryParse(j['paired_at'] as String),
      );
}

/// Kesehatan sambungan ke satu TV, dari sudut pandang operator.
enum TvLinkHealth {
  /// Belum ada TV yang dipasangkan ke station ini.
  unlinked,

  /// Perintah terakhir berhasil.
  online,

  /// Sudah dipasangkan tapi tidak merespons.
  unreachable,

  /// Merespons tapi menolak token — biasanya pairing dicabut di TV.
  rejected,
}

/// Ringkasan status sambungan + hasil push terakhir.
class TvLinkStatus {
  const TvLinkStatus({
    required this.health,
    this.link,
    this.info,
    this.lastOkAt,
    this.lastError,
  });

  final TvLinkHealth health;
  final TvLink? link;
  final TvAgentInfo? info;
  final DateTime? lastOkAt;
  final String? lastError;

  bool get isLinked => link != null;

  static const unlinked = TvLinkStatus(health: TvLinkHealth.unlinked);

  TvLinkStatus copyWith({
    TvLinkHealth? health,
    TvLink? link,
    TvAgentInfo? info,
    DateTime? lastOkAt,
    String? lastError,
    bool clearError = false,
  }) =>
      TvLinkStatus(
        health: health ?? this.health,
        link: link ?? this.link,
        info: info ?? this.info,
        lastOkAt: lastOkAt ?? this.lastOkAt,
        lastError: clearError ? null : (lastError ?? this.lastError),
      );
}
