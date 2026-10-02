import 'package:flutter/foundation.dart';

/// Server-time offset — DEC-003.
///
/// Jam tablet, laptop server, dan TV hampir pasti berbeda. Menghitung sisa
/// waktu dari `DateTime.now()` mentah membuat timer salah beberapa menit:
/// customer dirugikan, atau rental kehilangan uang.
///
/// Pola:
/// ```
/// offset    = server_time - device_time
/// remaining = end_at - (device_now + offset)
/// ```
///
/// [sync] dipanggil dari setiap response API (header `X-Server-Time`) dan
/// setiap payload event realtime (`server_time`).
class ServerTime extends ChangeNotifier {
  ServerTime._();

  static final ServerTime instance = ServerTime._();

  Duration _offset = Duration.zero;
  DateTime? _lastSyncedAt;

  Duration get offset => _offset;

  /// Belum pernah sinkron -> UI sebaiknya menandai timer sebagai belum pasti.
  bool get isSynced => _lastSyncedAt != null;

  /// Kapan terakhir sinkron (waktu device).
  DateTime? get lastSyncedAt => _lastSyncedAt;

  /// Waktu "sekarang" menurut server.
  DateTime get now => DateTime.now().toUtc().add(_offset);

  /// Sinkronkan dari waktu server.
  ///
  /// [roundTripTime] opsional: separuhnya dikompensasi untuk latensi jaringan.
  /// Di LAN nilainya kecil, tapi tetap benar untuk dihitung.
  void sync(DateTime serverTime, {Duration? roundTripTime}) {
    final deviceNow = DateTime.now().toUtc();
    var computed = serverTime.toUtc().difference(deviceNow);
    if (roundTripTime != null) {
      computed += Duration(microseconds: roundTripTime.inMicroseconds ~/ 2);
    }

    final changedMaterially =
        (computed - _offset).abs() > const Duration(seconds: 1);

    _offset = computed;
    _lastSyncedAt = deviceNow;

    // Hanya beri tahu listener kalau pergeserannya berarti — mencegah
    // rebuild setiap response API.
    if (changedMaterially || !_notifiedOnce) {
      _notifiedOnce = true;
      notifyListeners();
    }
  }

  bool _notifiedOnce = false;

  /// Sisa waktu sampai [endAt] menurut jam server. Negatif kalau sudah lewat.
  Duration remainingUntil(DateTime endAt) => endAt.toUtc().difference(now);

  @visibleForTesting
  void resetForTest() {
    _offset = Duration.zero;
    _lastSyncedAt = null;
    _notifiedOnce = false;
  }

  @visibleForTesting
  void setOffsetForTest(Duration value) {
    _offset = value;
    _lastSyncedAt = DateTime.now().toUtc();
  }
}
