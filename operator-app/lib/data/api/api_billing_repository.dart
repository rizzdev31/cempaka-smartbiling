import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../../domain/repositories/billing_repository.dart';
import 'api_client.dart';

/// [BillingRepository] yang bicara ke Laravel — DEC-012 syarat 2.
///
/// Menggantikan `FakeBillingRepository`. Fake tidak bisa membuktikan
/// kontraknya lengkap; hanya server asli yang mengungkap field yang kurang.
///
/// Kelas ini sengaja **tipis**: memetakan satu method ke satu endpoint, lalu
/// menyerahkan parsing ke `fromJson` milik model. Tidak ada aturan bisnis di
/// sini sama sekali — harga, pembulatan, kebolehan extend, dan status semua
/// ditentukan server (PRD §8/§33). Kalau ada perhitungan muncul di file ini,
/// itu tanda aturannya mulai diduplikasi.
class ApiBillingRepository implements BillingRepository {
  ApiBillingRepository(this._api);

  final ApiClient _api;

  /// Data dari server, bukan contoh — penanda "DATA CONTOH" di UI hilang.
  @override
  bool get isSample => false;

  // ── Master data ───────────────────────────────────────────────────

  @override
  Future<List<Station>> fetchStations() async {
    final data = await _api.get('/stations');
    return _list(data, Station.fromJson);
  }

  @override
  Future<List<Package>> fetchPackages() async {
    final data = await _api.get('/packages');
    return _list(data, Package.fromJson);
  }

  /// Paket yang sah untuk satu station saja — DEC-019.
  ///
  /// Tanpa penyaringan ini operator bisa memilih paket PS4 di station PS5 dan
  /// baru ditolak server setelah menekan tombol.
  Future<List<Package>> fetchPackagesForStation(String stationId) async {
    final data = await _api.get('/packages', query: {'station_id': stationId});
    return _list(data, Package.fromJson);
  }

  @override
  Future<List<Customer>> searchCustomers(String query) async {
    final data = await _api.get('/customers', query: {'q': query});
    return _list(data, Customer.fromJson);
  }

  // ── Session ───────────────────────────────────────────────────────

  @override
  Future<Session> fetchSession(String sessionId) async {
    final data = await _api.get('/sessions/$sessionId');
    return Session.fromJson(_map(data));
  }

  @override
  Future<List<Session>> fetchSessions({Set<SessionStatus>? statuses}) async {
    final data = await _api.get('/sessions', query: {
      if (statuses != null && statuses.isNotEmpty)
        'status': statuses.map((s) => s.wire).join(','),
    });
    return _list(data, Session.fromJson);
  }

  @override
  Future<Session> createSession({
    required String stationId,
    required String packageId,
    required SessionMode mode,
    required String idempotencyKey,
    String? customerId,
    String? customerName,
  }) async {
    final data = await _api.post(
      '/sessions',
      idempotencyKey: idempotencyKey,
      body: {
        'station_id': stationId,
        'package_id': packageId,
        'mode': mode.wire,
        // DEC-008: satu session satu customer. Kirim SALAH SATU; keduanya
        // kosong berarti walk-in dan server mengisinya "Walk-in".
        if (customerId != null) 'customer_id': customerId,
        if (customerId == null && customerName != null)
          'customer_name': customerName,
      },
    );
    return Session.fromJson(_map(data));
  }

  @override
  Future<PaymentResult> addPayment({
    required String sessionId,
    required PaymentMethod method,
    required int amount,
    required String idempotencyKey,
    String? reference,
    String? note,
  }) async {
    final data = _map(await _api.post(
      '/sessions/$sessionId/payments',
      idempotencyKey: idempotencyKey,
      body: {
        'method': method.wire,
        'amount': amount,
        if (reference != null) 'reference': reference,
        if (note != null) 'note': note,
      },
    ));

    /*
     * Session ikut dikirim balik karena pembayaran pertama Prepaid mengubah
     * status, started_at, dan end_at sekaligus. Tanpa itu timer baru mulai
     * setelah GET susulan — dan selisihnya terlihat di layar.
     */
    return PaymentResult(
      payment: Payment.fromJson(_map(data['payment'])),
      session: Session.fromJson(_map(data['session'])),
    );
  }

  @override
  Future<ExtendResult> extendSession({
    required String sessionId,
    required int durationMinutes,
    required String idempotencyKey,
  }) async {
    final data = _map(await _api.post(
      '/sessions/$sessionId/extend',
      idempotencyKey: idempotencyKey,
      body: {'duration_minutes': durationMinutes},
    ));

    final extend = _map(data['extend']);

    return ExtendResult(
      session: Session.fromJson(_map(data['session'])),
      durationMinutes: extend['duration_minutes'] as int,
      price: extend['price'] as int,
      previousEndAt: DateTime.parse(extend['previous_end_at'] as String),
      newEndAt: DateTime.parse(extend['new_end_at'] as String),
    );
  }

  @override
  Future<Session> swapStation({
    required String sessionId,
    required String targetStationId,
    required String idempotencyKey,
    String? reason,
  }) async {
    final data = await _api.post(
      '/sessions/$sessionId/swap',
      idempotencyKey: idempotencyKey,
      body: {
        'target_station_id': targetStationId,
        if (reason != null) 'reason': reason,
      },
    );
    return Session.fromJson(_map(data));
  }

  @override
  Future<CheckoutResult> checkout({
    required String sessionId,
    required List<({PaymentMethod method, int amount, String? reference})>
        payments,
    required String idempotencyKey,
  }) async {
    final data = _map(await _api.post(
      '/sessions/$sessionId/checkout',
      idempotencyKey: idempotencyKey,
      body: {
        'payments': [
          for (final p in payments)
            {
              'method': p.method.wire,
              'amount': p.amount,
              if (p.reference != null) 'reference': p.reference,
            },
        ],
      },
    ));

    return CheckoutResult(
      session: Session.fromJson(_map(data['session'])),
      receipt: _receipt(_map(data['receipt'])),
    );
  }

  @override
  Future<Session> cancelSession({
    required String sessionId,
    required String idempotencyKey,
    String? reason,
  }) async {
    final data = await _api.post(
      '/sessions/$sessionId/cancel',
      idempotencyKey: idempotencyKey,
      body: {if (reason != null) 'reason': reason},
    );
    return Session.fromJson(_map(data));
  }

  // ── F&B ───────────────────────────────────────────────────────────

  @override
  Future<List<FnbProduct>> fetchFnbProducts() async {
    final data = await _api.get('/fnb/products');
    return _list(data, FnbProduct.fromJson);
  }

  @override
  Future<FnbOrderResult> createFnbOrder({
    required String sessionId,
    required List<({String productId, int qty})> items,
    required String idempotencyKey,
    String? note,
  }) async {
    final data = _map(await _api.post(
      '/sessions/$sessionId/fnb/orders',
      idempotencyKey: idempotencyKey,
      body: {
        // Harga TIDAK dikirim — selalu dari server (PRD §8).
        'items': [
          for (final i in items) {'product_id': i.productId, 'qty': i.qty},
        ],
        if (note != null) 'note': note,
      },
    ));

    return FnbOrderResult(
      order: FnbOrder.fromJson(_map(data['order'])),
      session: Session.fromJson(_map(data['session'])),
    );
  }

  @override
  Future<List<FnbOrder>> fetchFnbOrders({Set<FnbOrderStatus>? statuses}) async {
    final data = await _api.get('/fnb/orders', query: {
      if (statuses != null && statuses.isNotEmpty)
        'status': statuses.map((s) => s.wire).join(','),
    });
    return _list(data, FnbOrder.fromJson);
  }

  @override
  Future<FnbOrder> updateFnbOrderStatus({
    required String orderId,
    required FnbOrderStatus status,
  }) async {
    final data = _map(await _api.post(
      '/fnb/orders/$orderId/status',
      body: {'status': status.wire},
    ));
    return FnbOrder.fromJson(_map(data['order']));
  }

  // ── Device ────────────────────────────────────────────────────────

  @override
  Future<DeviceList> fetchDevices() async {
    final result = await _api.getWithMeta('/devices');

    /*
     * Ambang offline datang dari `meta`, bukan ditulis ulang di client —
     * kalau server mengubahnya, tablet ikut tanpa perlu rilis baru.
     */
    final seconds = result.meta['offline_threshold_seconds'] as int?;

    return DeviceList(
      devices: _list(result.data, Device.fromJson),
      offlineThreshold: Duration(seconds: seconds ?? 120),
    );
  }

  // ── Shift ─────────────────────────────────────────────────────────

  @override
  Future<Shift?> fetchCurrentShift() async {
    final data = await _api.get('/shifts/current');
    // `null` berarti belum buka shift — keadaan normal di awal hari,
    // bukan error.
    return data == null ? null : Shift.fromJson(_map(data));
  }

  @override
  Future<Shift> openShift({
    required int openingCash,
    required String idempotencyKey,
  }) async {
    final data = await _api.post(
      '/shifts/open',
      idempotencyKey: idempotencyKey,
      body: {'opening_cash': openingCash},
    );
    return Shift.fromJson(_map(data));
  }

  @override
  Future<Shift> closeShift({
    required String shiftId,
    required int closingCash,
    required String idempotencyKey,
    String? note,
  }) async {
    final data = await _api.post(
      '/shifts/$shiftId/close',
      idempotencyKey: idempotencyKey,
      body: {
        'closing_cash': closingCash,
        if (note != null) 'note': note,
      },
    );
    return Shift.fromJson(_map(data));
  }

  @override
  Future<List<Shift>> fetchShiftHistory({int limit = 20}) async {
    final data = await _api.get('/shifts', query: {'limit': '$limit'});
    return _list(data, Shift.fromJson);
  }

  // ── Pembantu ──────────────────────────────────────────────────────

  /// Struk — kontrak §7.
  ///
  /// Dirakit di sini, bukan lewat `Receipt.fromJson`, supaya model milik UI
  /// tidak perlu ikut tahu bentuk JSON-nya. Kalau nanti dibutuhkan di tempat
  /// lain, barulah dipindah jadi factory di model.
  static Receipt _receipt(Map<String, dynamic> j) => Receipt(
        number: j['number'] as String,
        issuedAt: DateTime.parse(j['issued_at'] as String),
        // Dua-duanya WAJIB ada — supaya operator bisa menjelaskan ke customer
        // kenapa 63 menit ditagih 60 (DEC-009).
        billableDurationMinutes: j['billable_duration_minutes'] as int,
        actualDurationMinutes: j['actual_duration_minutes'] as int,
        lines: ((j['lines'] as List?) ?? const [])
            .map((e) {
              final l = _map(e);
              return ReceiptLine(
                name: l['name'] as String,
                qty: l['qty'] as int,
                subtotal: l['subtotal'] as int,
              );
            })
            .toList(growable: false),
        totals: SessionTotals.fromJson(_map(j['totals'])),
        payments: _list(j['payments'], Payment.fromJson),
        operator: j['operator'] == null
            ? null
            : ActorRef.fromJson(_map(j['operator'])),
      );

  static Map<String, dynamic> _map(dynamic value) =>
      (value as Map).cast<String, dynamic>();

  static List<T> _list<T>(dynamic data, T Function(Map<String, dynamic>) parse) =>
      ((data as List?) ?? const [])
          .map((e) => parse(_map(e)))
          .toList(growable: false);
}
