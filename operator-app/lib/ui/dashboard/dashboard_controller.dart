import 'package:flutter/foundation.dart';
import 'package:uuid/uuid.dart';

import '../../domain/errors/api_error.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../../domain/repositories/billing_repository.dart';

/// State dashboard.
///
/// Sengaja TIDAK memakai polling berkala. Kartu station sudah mengoreksi
/// dirinya sendiri karena timer dan status diturunkan dari `end_at` di client
/// (lihat `deriveStationViewStatus`). Refresh dilakukan: saat layar dibuka,
/// saat pull-to-refresh, dan setelah setiap mutasi.
///
/// Saat Reverb tersambung (Tahap 0), `session.*` dari WebSocket memanggil
/// [applySession] — tidak perlu mengubah UI.
class DashboardController extends ChangeNotifier {
  DashboardController(this._repo);

  final BillingRepository _repo;
  static const _uuid = Uuid();

  List<Station> _stations = const [];
  List<Package> _packages = const [];
  int _fnbActionable = 0;
  bool _loading = false;
  Object? _error;

  List<Station> get stations => _stations;
  List<Package> get packages => _packages;
  bool get loading => _loading;
  Object? get error => _error;

  /// Jumlah order F&B yang masih menuntut tindakan — untuk badge di
  /// tombol antrian. Hanya `PENDING` dan `PROCESSING`; yang sudah `READY`
  /// tidak dihitung karena tinggal diantar, bukan dikerjakan.
  int get fnbActionableCount => _fnbActionable;

  bool get hasData => _stations.isNotEmpty;

  int get activeCount => _stations
      .where((s) => s.session?.status.hasTimer ?? false)
      .length;

  int get availableCount => _stations
      .where((s) =>
          s.session == null && s.status == StationMasterStatus.active)
      .length;

  int get offlineDeviceCount =>
      _stations.where((s) => s.device?.status == DeviceStatus.offline).length;

  /// Total tagihan berjalan seluruh station — angka yang paling sering
  /// ditanya pemilik.
  int get openBalance =>
      _stations.fold(0, (a, s) => a + (s.session?.balanceDue ?? 0));

  Future<void> load({bool silent = false}) async {
    if (!silent) {
      _loading = true;
      _error = null;
      notifyListeners();
    }

    try {
      final results = await Future.wait([
        _repo.fetchStations(),
        _repo.fetchPackages(),
        _repo.fetchFnbOrders(statuses: const {
          FnbOrderStatus.pending,
          FnbOrderStatus.processing,
        }),
      ]);
      _stations = results[0] as List<Station>;
      _packages = results[1] as List<Package>;
      _fnbActionable = (results[2] as List<FnbOrder>).length;
      _error = null;
    } catch (e) {
      _error = e;
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  Future<void> refresh() => load(silent: true);

  /// Buat sesi baru.
  ///
  /// [idempotencyKey] dibuat SEKALI di sini, lalu dipakai ulang kalau
  /// pemanggil melakukan retry — bukan key baru setiap percobaan
  /// (kontrak §3). Ini yang mencegah sesi ganda saat jaringan buruk.
  Future<Session> startSession({
    required String stationId,
    required String packageId,
    required SessionMode mode,
    String? customerId,
    String? customerName,
    String? idempotencyKey,
  }) async {
    final session = await _repo.createSession(
      stationId: stationId,
      packageId: packageId,
      mode: mode,
      customerId: customerId,
      customerName: customerName,
      idempotencyKey: idempotencyKey ?? _uuid.v4(),
    );
    await refresh();
    return session;
  }

  /// Dipanggil oleh event realtime nanti, atau setelah mutasi dari layar lain.
  void applySession(Session session) {
    final idx =
        _stations.indexWhere((s) => s.id == session.station.id);
    if (idx < 0) {
      refresh();
      return;
    }

    final old = _stations[idx];
    _stations = List.of(_stations)
      ..[idx] = Station(
        id: old.id,
        code: old.code,
        name: old.name,
        status: old.status,
        device: old.device,
        session: session.status.occupiesStation
            ? StationSessionSummary(
                id: session.id,
                status: session.status,
                startedAt: session.startedAt,
                endAt: session.endAt,
                customerLabel: session.customerLabel,
                balanceDue: session.totals.balanceDue,
              )
            : null,
      );
    notifyListeners();
  }

  String? get errorMessage => switch (_error) {
        ApiError e => e.message,
        null => null,
        _ => 'Terjadi kesalahan tidak terduga.',
      };
}
