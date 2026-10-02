import 'package:flutter/foundation.dart';
import 'package:uuid/uuid.dart';

import '../../domain/errors/api_error.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../../data/tv/tv_sync_service.dart';
import '../../domain/repositories/billing_repository.dart';
import '../widgets/station_card.dart';

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
  DashboardController(this._repo, {TvSyncService? tvSync}) : _tvSync = tvSync;

  final BillingRepository _repo;

  /// Pengirim keadaan sesi ke TV. Null berarti kontrol TV tidak aktif —
  /// dashboard tetap berfungsi penuh tanpanya.
  final TvSyncService? _tvSync;
  static const _uuid = Uuid();

  List<Station> _stations = const [];
  List<Package> _packages = const [];
  int _fnbActionable = 0;
  Shift? _currentShift;
  bool _loading = false;
  Object? _error;

  /// Shift yang sedang berjalan. Dipakai sidebar untuk menampilkan nama
  /// operator dan jam buka — `null` kalau belum ada shift.
  Shift? get currentShift => _currentShift;

  List<Station> get stations => _stations;
  List<Package> get packages => _packages;
  bool get loading => _loading;
  Object? get error => _error;

  /// Jumlah order F&B yang masih menuntut tindakan — untuk badge di
  /// tombol antrian. Hanya `PENDING` dan `PROCESSING`; yang sudah `READY`
  /// tidak dihitung karena tinggal diantar, bukan dikerjakan.
  int get fnbActionableCount => _fnbActionable;

  bool get hasData => _stations.isNotEmpty;

  // ── Filter & pencarian ────────────────────────────────────────────

  StationFilter _filter = StationFilter.all;
  String _query = '';

  StationFilter get filter => _filter;
  String get query => _query;

  void setFilter(StationFilter f) {
    if (_filter == f) return;
    _filter = f;
    notifyListeners();
  }

  void setQuery(String q) {
    final v = q.trim();
    if (_query == v) return;
    _query = v;
    notifyListeners();
  }

  /// Station yang lolos filter + pencarian.
  ///
  /// Status WARNING/EXPIRED diturunkan dari `end_at` di sini juga, sama
  /// seperti di kartu — kalau tidak, filter "Hampir Habis" akan memakai
  /// snapshot server yang bisa terlambat beberapa detik.
  List<Station> get visibleStations {
    Iterable<Station> list = _stations;

    if (_query.isNotEmpty) {
      final q = _query.toLowerCase();
      list = list.where((s) =>
          s.code.toLowerCase().contains(q) ||
          s.name.toLowerCase().contains(q) ||
          (s.consoleType ?? '').toLowerCase().contains(q) ||
          (s.session?.customerLabel ?? '').toLowerCase().contains(q));
    }

    if (_filter != StationFilter.all) {
      list = list.where((s) => _matches(s, _filter));
    }

    return list.toList(growable: false);
  }

  int countFor(StationFilter f) => f == StationFilter.all
      ? _stations.length
      : _stations.where((s) => _matches(s, f)).length;

  bool _matches(Station s, StationFilter f) {
    final view = deriveStationViewStatus(s);
    return switch (f) {
      StationFilter.all => true,
      StationFilter.playing => view == StationViewStatus.active,
      StationFilter.available => view == StationViewStatus.available,
      StationFilter.warning => view == StationViewStatus.warning ||
          view == StationViewStatus.expired,
      StationFilter.pending => view == StationViewStatus.pendingPayment ||
          view == StationViewStatus.checkout,
    };
  }

  /// Tarif per jam termurah dari paket aktif — ditampilkan pada station
  /// kosong sebagai ancang-ancang harga. Harga final tetap dari server.
  int? get cheapestHourlyRate {
    if (_packages.isEmpty) return null;
    return _packages.map((p) => p.hourlyRate).reduce((a, b) => a < b ? a : b);
  }

  int get activeCount => _stations
      .where((s) => s.session?.status.hasTimer ?? false)
      .length;

  int get availableCount => _stations
      .where((s) =>
          s.session == null && s.status == StationMasterStatus.active)
      .length;

  /// Sambungan TV **tidak** dihitung di sini.
  ///
  /// Satu-satunya kebenaran soal TV ada di `TvSyncService`: sambungannya
  /// nyata, sementara data station masih contoh. Menghitungnya di dua tempat
  /// berarti dua angka yang bisa berbeda untuk hal yang sama, dan operator
  /// berhenti mempercayai keduanya.
  bool get isSampleData => _repo.isSample;

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
        _repo.fetchCurrentShift(),
      ]);
      _stations = results[0] as List<Station>;
      _packages = results[1] as List<Package>;
      _fnbActionable = (results[2] as List<FnbOrder>).length;
      _currentShift = results[3] as Shift?;
      _error = null;

      // TV disamakan dengan keadaan station terkini. Dijalankan tanpa
      // ditunggu: TV yang tidak merespons tidak boleh membuat dashboard
      // terasa lambat, dan statusnya ditampilkan terpisah.
      _syncTv();
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

  void _syncTv() {
    final sync = _tvSync;
    if (sync == null) return;
    sync.syncAll(_stations);
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
        consoleType: old.consoleType,
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

/// Filter kartu station di dashboard.
///
/// Dengan enam station filter terasa berlebihan — tapi PRD §10 menyatakan
/// jumlah station "dapat ditambah", dan pada 12–20 station mencari satu
/// station yang hampir habis tanpa filter jadi melelahkan.
enum StationFilter {
  all('Semua'),
  playing('Bermain'),
  available('Tersedia'),
  warning('Hampir Habis'),
  pending('Menunggu Bayar');

  const StationFilter(this.label);
  final String label;
}
