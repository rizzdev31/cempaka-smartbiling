import 'package:flutter/foundation.dart';
import 'package:uuid/uuid.dart';

import '../../domain/errors/api_error.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../../domain/repositories/billing_repository.dart';

/// State satu sesi.
///
/// Semua aturan bisnis tetap milik server. Controller ini hanya meneruskan
/// perintah dan menyimpan hasilnya. Satu-satunya "logika" di sini adalah
/// pembuatan `Idempotency-Key` per niat aksi (kontrak §3).
class SessionDetailController extends ChangeNotifier {
  SessionDetailController(this._repo, this.sessionId);

  final BillingRepository _repo;
  final String sessionId;
  static const _uuid = Uuid();

  Session? _session;
  List<FnbProduct> _products = const [];
  List<Station> _stations = const [];
  bool _loading = false;
  Object? _error;

  Session? get session => _session;
  List<FnbProduct> get products => _products;
  bool get loading => _loading;
  Object? get error => _error;

  String? get errorMessage => switch (_error) {
        ApiError e => e.message,
        null => null,
        _ => 'Terjadi kesalahan tidak terduga.',
      };

  /// Station yang bisa jadi tujuan swap: AVAILABLE dan bukan station ini.
  /// Server tetap memvalidasi ulang (`STATION_NOT_AVAILABLE`).
  List<Station> get swapTargets => _stations
      .where((s) =>
          s.session == null &&
          s.status == StationMasterStatus.active &&
          s.id != _session?.station.id)
      .toList(growable: false);

  Future<void> load({bool silent = false}) async {
    if (!silent) {
      _loading = true;
      _error = null;
      notifyListeners();
    }
    try {
      final results = await Future.wait([
        _repo.fetchSession(sessionId),
        _repo.fetchFnbProducts(),
        _repo.fetchStations(),
      ]);
      _session = results[0] as Session;
      _products = results[1] as List<FnbProduct>;
      _stations = results[2] as List<Station>;
      _error = null;
    } catch (e) {
      _error = e;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> refresh() => load(silent: true);

  void _apply(Session s) {
    _session = s;
    notifyListeners();
  }

  // ── Aksi ──────────────────────────────────────────────────────────

  Future<PaymentResult> pay({
    required PaymentMethod method,
    required int amount,
    required String idempotencyKey,
    String? reference,
  }) async {
    final result = await _repo.addPayment(
      sessionId: sessionId,
      method: method,
      amount: amount,
      reference: reference,
      idempotencyKey: idempotencyKey,
    );
    _apply(result.session);
    return result;
  }

  /// Kelipatan 30 menit (DEC-007). Server menolak nilai lain.
  Future<ExtendResult> extend({
    required int durationMinutes,
    required String idempotencyKey,
  }) async {
    final result = await _repo.extendSession(
      sessionId: sessionId,
      durationMinutes: durationMinutes,
      idempotencyKey: idempotencyKey,
    );
    _apply(result.session);
    return result;
  }

  Future<Session> swap({
    required String targetStationId,
    required String idempotencyKey,
    String? reason,
  }) async {
    final s = await _repo.swapStation(
      sessionId: sessionId,
      targetStationId: targetStationId,
      reason: reason,
      idempotencyKey: idempotencyKey,
    );
    _apply(s);
    await _reloadStations();
    return s;
  }

  Future<FnbOrderResult> addFnb({
    required List<({String productId, int qty})> items,
    required String idempotencyKey,
    String? note,
  }) async {
    final result = await _repo.createFnbOrder(
      sessionId: sessionId,
      items: items,
      note: note,
      idempotencyKey: idempotencyKey,
    );
    _apply(result.session);
    return result;
  }

  Future<CheckoutResult> checkout({
    required List<({PaymentMethod method, int amount, String? reference})>
        payments,
    required String idempotencyKey,
  }) async {
    final result = await _repo.checkout(
      sessionId: sessionId,
      payments: payments,
      idempotencyKey: idempotencyKey,
    );
    _apply(result.session);
    return result;
  }

  Future<Session> cancel({
    required String idempotencyKey,
    String? reason,
  }) async {
    final s = await _repo.cancelSession(
      sessionId: sessionId,
      reason: reason,
      idempotencyKey: idempotencyKey,
    );
    _apply(s);
    return s;
  }

  Future<void> _reloadStations() async {
    try {
      _stations = await _repo.fetchStations();
      notifyListeners();
    } catch (_) {
      // Daftar station hanya untuk pilihan swap — kegagalan di sini tidak
      // boleh menutupi hasil aksi yang sudah berhasil.
    }
  }

  static String newIdempotencyKey() => _uuid.v4();
}
