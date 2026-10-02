import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../domain/errors/api_error.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../../domain/models/tv_agent.dart';
import 'tv_agent_client.dart';
import 'tv_link_store.dart';

/// Mengirim keadaan sesi ke TV yang terpasang.
///
/// **Ini inti kontrol billing → TV.** Satu-satunya tempat yang memutuskan
/// kapan TV perlu diberi kabar.
///
/// ## Dikirim saat berubah, bukan terus-menerus
///
/// Dashboard menyegarkan data setiap kali operator kembali ke layar monitor.
/// Mengirim ulang ke TV setiap kali itu berarti belasan permintaan per menit
/// tanpa ada yang berubah. Jadi yang dikirim hanya **perubahan**: service ini
/// menyimpan sidik keadaan terakhir per station dan membandingkannya.
///
/// ## Yang dianggap perubahan
///
/// `session_id`, `end_at`, dan mode (berjalan / menunggu bayar / kosong).
/// Nama customer ikut dikirim tapi tidak memicu pengiriman sendiri — kalau
/// hanya namanya berubah, TV tidak perlu diganggu.
///
/// `end_at` masuk sidik karena itu yang membuat **extend** terkirim: menambah
/// durasi tidak mengubah `session_id`, hanya `end_at`.
class TvSyncService extends ChangeNotifier {
  TvSyncService({
    required TvAgentClient client,
    required TvLinkStore store,
  })  : _client = client,
        _store = store;

  final TvAgentClient _client;
  final TvLinkStore _store;

  final Map<String, TvLinkStatus> _status = {};
  final Map<String, String> _lastPushed = {};
  final Set<String> _inFlight = {};

  /// Status per `stationId`.
  TvLinkStatus statusFor(String stationId) =>
      _status[stationId] ??
      (_store.forStation(stationId) == null
          ? TvLinkStatus.unlinked
          : TvLinkStatus(
              health: TvLinkHealth.unreachable,
              link: _store.forStation(stationId),
            ));

  bool isLinked(String stationId) => _store.forStation(stationId) != null;

  bool isBusy(String stationId) => _inFlight.contains(stationId);

  int get linkedCount => _store.all.length;

  int get onlineCount => _store.all.keys
      .where((id) => statusFor(id).health == TvLinkHealth.online)
      .length;

  int get problemCount => _store.all.keys.where((id) {
        final h = statusFor(id).health;
        return h == TvLinkHealth.unreachable || h == TvLinkHealth.rejected;
      }).length;

  // ── Sinkronisasi ──────────────────────────────────────────────────

  /// Samakan semua TV terpasang dengan keadaan station terkini.
  ///
  /// Dipanggil setiap kali data station dimuat ulang. Aman dipanggil sering —
  /// yang tidak berubah tidak dikirim.
  Future<void> syncAll(List<Station> stations) async {
    final futures = <Future<void>>[];

    for (final station in stations) {
      if (!isLinked(station.id)) continue;
      futures.add(_syncStation(station));
    }

    if (futures.isEmpty) return;
    await Future.wait(futures);
  }

  /// Paksa kirim ulang untuk satu station, melewati pembandingan sidik.
  ///
  /// Dipakai setelah TV dipasangkan, dan saat operator menekan "Kirim ulang"
  /// karena curiga TV-nya tidak sinkron.
  Future<void> forcePush(Station station) async {
    _lastPushed.remove(station.id);
    await _syncStation(station);
  }

  Future<void> _syncStation(Station station) async {
    final link = _store.forStation(station.id);
    if (link == null) return;

    final fingerprint = _fingerprint(station);
    if (_lastPushed[station.id] == fingerprint) return;

    if (_inFlight.contains(station.id)) return;
    _inFlight.add(station.id);
    notifyListeners();

    try {
      final session = station.session;

      if (session == null) {
        await _client.endSession(link);
      } else {
        await _client.pushSession(
          link,
          sessionId: session.id,
          startedAt: session.startedAt,
          endAt: session.endAt,
          pendingPayment: session.status == SessionStatus.pendingPayment,
          customerLabel: session.customerLabel,
        );
      }

      _lastPushed[station.id] = fingerprint;
      _status[station.id] = statusFor(station.id).copyWith(
        health: TvLinkHealth.online,
        link: link,
        lastOkAt: DateTime.now().toUtc(),
        clearError: true,
      );
    } on ApiError catch (e) {
      // Sidik TIDAK disimpan saat gagal, supaya percobaan berikutnya
      // mengirim lagi. Kalau disimpan, TV akan tertinggal sampai ada
      // perubahan berikutnya — dan itu bisa berjam-jam.
      _status[station.id] = statusFor(station.id).copyWith(
        health: _healthFor(e),
        link: link,
        lastError: e.message,
      );
    } catch (_) {
      _status[station.id] = statusFor(station.id).copyWith(
        health: TvLinkHealth.unreachable,
        link: link,
        lastError: 'Tidak bisa menghubungi TV.',
      );
    } finally {
      _inFlight.remove(station.id);
      notifyListeners();
    }
  }

  TvLinkHealth _healthFor(ApiError e) => switch (e.code) {
        // Token dicabut di TV, atau TV dipasangkan ulang ke station lain.
        // Dibedakan dari "tidak terjangkau" karena penanganannya berbeda:
        // ini perlu pairing ulang, bukan menunggu jaringan membaik.
        ApiErrorCode.deviceTokenInvalid => TvLinkHealth.rejected,
        'DEVICE_NOT_PAIRED' => TvLinkHealth.rejected,
        _ => TvLinkHealth.unreachable,
      };

  /// Sidik keadaan yang relevan untuk TV.
  ///
  /// Sengaja TIDAK memasukkan `balance_due` dan nama customer: tagihan
  /// berubah setiap item F&B ditambahkan, dan TV tidak menampilkannya. Kalau
  /// dimasukkan, setiap teh manis memicu satu permintaan ke TV tanpa ada yang
  /// berubah di layarnya.
  String _fingerprint(Station station) {
    final s = station.session;
    if (s == null) return 'empty';
    return [
      s.id,
      s.status == SessionStatus.pendingPayment ? 'pending' : 'running',
      s.endAt?.millisecondsSinceEpoch ?? 0,
    ].join('|');
  }

  // ── Pairing ───────────────────────────────────────────────────────

  /// Pasangkan TV ke station, lalu segera kirim keadaan terkini.
  ///
  /// Pengiriman langsung itu penting: tanpanya TV menampilkan layar idle
  /// walau station-nya sedang ada sesi berjalan, dan operator menyimpulkan
  /// pairing-nya gagal.
  Future<TvLink> pair({
    required Station station,
    required String baseUrl,
    required String code,
  }) async {
    final link = await _client.pair(
      baseUrl: baseUrl,
      code: code,
      stationId: station.id,
      stationCode: station.code,
    );

    await _store.save(link);
    _status[station.id] = TvLinkStatus(
      health: TvLinkHealth.online,
      link: link,
      lastOkAt: DateTime.now().toUtc(),
    );
    _lastPushed.remove(station.id);
    notifyListeners();

    await _syncStation(station);
    return link;
  }

  /// Lepas pairing. Tetap melepas di sisi operator walau TV tidak merespons.
  ///
  /// Kalau TV mati atau sudah dibawa pergi, operator harus tetap bisa
  /// melepasnya — kalau tidak, station itu terjebak dengan TV yang tidak ada.
  Future<void> unpair(String stationId) async {
    final link = _store.forStation(stationId);
    if (link == null) return;

    try {
      await _client.unpair(link);
    } catch (_) {
      // Diabaikan dengan sengaja; lihat komentar di atas.
    }

    await _store.remove(stationId);
    _status.remove(stationId);
    _lastPushed.remove(stationId);
    notifyListeners();
  }

  /// Perbarui alamat TV setelah IP-nya berubah.
  Future<void> rebind(String stationId, String baseUrl) async {
    await _store.updateAddress(
      stationId,
      TvAgentClient.normalizeBaseUrl(baseUrl),
    );
    _lastPushed.remove(stationId);
    _status.remove(stationId);
    notifyListeners();
  }

  /// Periksa satu TV tanpa mengubah apa pun.
  Future<void> refreshStatus(String stationId) async {
    final link = _store.forStation(stationId);
    if (link == null) return;

    _inFlight.add(stationId);
    notifyListeners();

    try {
      final info = await _client.health(link.baseUrl);
      await _client.state(link); // menguji token, bukan hanya jangkauan

      _status[stationId] = statusFor(stationId).copyWith(
        health: TvLinkHealth.online,
        link: link,
        info: info,
        lastOkAt: DateTime.now().toUtc(),
        clearError: true,
      );
    } on ApiError catch (e) {
      _status[stationId] = statusFor(stationId).copyWith(
        health: _healthFor(e),
        link: link,
        lastError: e.message,
      );
    } catch (_) {
      _status[stationId] = statusFor(stationId).copyWith(
        health: TvLinkHealth.unreachable,
        link: link,
        lastError: 'Tidak bisa menghubungi TV.',
      );
    } finally {
      _inFlight.remove(stationId);
      notifyListeners();
    }
  }

  @visibleForTesting
  String fingerprintOf(Station station) => _fingerprint(station);

  @visibleForTesting
  String? lastPushedFor(String stationId) => _lastPushed[stationId];
}
