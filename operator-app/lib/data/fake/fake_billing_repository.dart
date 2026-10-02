import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:uuid/uuid.dart';

import '../../core/time/server_time.dart';
import '../../domain/errors/api_error.dart';
import '../../domain/models/enums.dart';
import '../../domain/models/models.dart';
import '../../domain/repositories/billing_repository.dart';

/// Implementasi in-memory untuk membangun UI sebelum Laravel ada.
///
/// SIFAT YANG DISENGAJA — jangan "disederhanakan":
/// 1. Meniru kontrak APA ADANYA, termasuk delay jaringan dan error code.
///    Fake yang selalu sukses membuat UI tidak pernah menangani kegagalan,
///    dan kegagalan itu baru muncul di lokasi saat testing.
/// 2. Aturan bisnis dihitung DI SINI, persis seperti server akan melakukannya
///    (DEC-007 extend, DEC-009 rounding). Jadi UI tidak pernah tergoda
///    menghitungnya sendiri — saat diganti `ApiBillingRepository`, UI tidak
///    perlu diubah sama sekali.
/// 3. Idempotency di-cache, supaya double-tap bisa diuji sejak sekarang (T14).
///
/// BATASNYA: fake TIDAK bisa membuktikan kontraknya lengkap. Hanya Laravel
/// asli yang mengungkap field yang kurang. Karena itu DEC-012 syarat 2
/// mewajibkan penggantian pada vertical slice pertama.
class FakeBillingRepository implements BillingRepository {
  FakeBillingRepository({int? seed}) : _rand = Random(seed ?? 7) {
    _seedData();
  }

  final Random _rand;
  static const _uuid = Uuid();

  @override
  bool get isSample => true;

  final List<Station> _stations = [];
  final List<Package> _packages = [];
  final List<Customer> _customers = [];
  final List<FnbProduct> _products = [];
  final Map<String, _SessionState> _sessions = {};
  final Map<String, FnbOrder> _orders = {};

  /// Cache idempotency — kontrak §3.
  final Map<String, Object> _idempotency = {};

  /// Log pembayaran. Server punya tabel `payments` (PRD §22); di sini
  /// dibutuhkan agar ringkasan shift bisa dihitung dari data nyata,
  /// bukan angka karangan.
  final List<_PaymentRecord> _paymentLog = [];

  _ShiftState? _currentShift;
  final List<_ShiftState> _shiftHistory = [];

  int _sessionSeq = 0;
  int _orderSeq = 0;
  int _receiptSeq = 0;

  // ── Simulasi jaringan ─────────────────────────────────────────────

  /// Delay realistis LAN. Bukan 0 — UI harus tahu rasanya menunggu.
  Future<void> _latency([int minMs = 120, int maxMs = 320]) =>
      Future<void>.delayed(
        Duration(milliseconds: minMs + _rand.nextInt(maxMs - minMs)),
      );

  /// Jam "server" palsu. Bisa dimajukan di test untuk mensimulasikan waktu
  /// berjalan — tanpa ini, aturan yang bergantung waktu (grace extend
  /// DEC-007, rounding durasi DEC-009) tidak bisa diuji sama sekali.
  Duration _clockSkew = Duration.zero;

  DateTime get _serverNow => DateTime.now().toUtc().add(_clockSkew);

  /// Majukan jam server palsu.
  @visibleForTesting
  void advanceClock(Duration by) {
    _clockSkew += by;
    _touchServerTime();
  }

  /// Setiap response menyegarkan offset, persis seperti header
  /// `X-Server-Time` nanti (DEC-003).
  void _touchServerTime() => ServerTime.instance.sync(_serverNow);

  /// Pembungkus setiap "request": delay + segarkan offset, lalu jalankan.
  /// Body-nya sinkron karena state ada di memori.
  Future<T> _call<T>(T Function() body) async {
    await _latency();
    _touchServerTime();
    return body();
  }

  /// Replay idempotent — kontrak §3 poin 3.
  Future<T> _idempotent<T extends Object>(
    String key,
    Future<T> Function() body,
  ) async {
    final cached = _idempotency[key];
    if (cached != null) {
      await _latency(60, 120);
      _touchServerTime();
      return cached as T;
    }
    final result = await body();
    _idempotency[key] = result;
    return result;
  }

  // ── Aturan bisnis — cerminan dari server ──────────────────────────

  /// DEC-007: `ceil(tarif_per_jam ÷ 2 × (menit ÷ 30))`, setara dengan
  /// `ceil(tarif_per_jam × menit ÷ 60)`.
  static int extendPrice({required int hourlyRate, required int minutes}) =>
      ((hourlyRate * minutes) + 59) ~/ 60;

  /// DEC-009: per 30 menit, toleransi 5 menit. Minimum 30.
  /// `35 -> 30` · `63 -> 60` · `70 -> 90` · `95 -> 90`
  static int billableMinutes(int actualMinutes) {
    if (actualMinutes <= 0) return 30;
    final sisa = actualMinutes % 30;
    final int rounded;
    if (sisa == 0) {
      rounded = actualMinutes;
    } else if (sisa <= 5) {
      rounded = actualMinutes - sisa;
    } else {
      rounded = actualMinutes + (30 - sisa);
    }
    return rounded < 30 ? 30 : rounded;
  }

  static const graceWindow = Duration(minutes: 10);

  void _recordPayment(PaymentMethod method, int amount, DateTime at) {
    _paymentLog.add(_PaymentRecord(method: method, amount: amount, at: at));
  }

  /// Ringkasan shift.
  ///
  /// SEMANTIK YANG DIPAKAI — lihat OD-013, belum dikonfirmasi tim:
  /// - `cash` / `qris` / `total` = **uang masuk** selama shift, dari log
  ///   pembayaran. Ini angka yang dipakai menghitung kas di kotak.
  /// - `rental` / `fnb` = **nilai transaksi** yang tercatat selama shift,
  ///   dari `session_items` berdasarkan waktu dibuat.
  ///
  /// Keduanya bisa berbeda, dan itu benar: Open Tab yang dibuka di shift
  /// pagi tapi dibayar di shift malam menghasilkan nilai transaksi di pagi
  /// dan uang masuk di malam.
  ShiftSummary _summaryFor(_ShiftState shift) {
    final from = shift.openedAt;
    final to = shift.closedAt ?? ServerTime.instance.now;

    bool inWindow(DateTime t) => !t.isBefore(from) && !t.isAfter(to);

    var cash = 0;
    var qris = 0;
    for (final p in _paymentLog) {
      if (!inWindow(p.at)) continue;
      if (p.method == PaymentMethod.cash) {
        cash += p.amount;
      } else if (p.method == PaymentMethod.qrisStatic) {
        qris += p.amount;
      }
    }

    var rental = 0;
    var fnb = 0;
    for (final s in _sessions.values) {
      for (final i in s.items) {
        if (!inWindow(i.createdAt)) continue;
        final subtotal = i.unitPrice * i.qty;
        switch (i.type) {
          case SessionItemType.rental:
          case SessionItemType.extend:
            rental += subtotal;
          case SessionItemType.fnb:
            fnb += subtotal;
          case SessionItemType.discount:
          case SessionItemType.adjustment:
          case SessionItemType.unknown:
            break;
        }
      }
    }

    return ShiftSummary(
      rental: rental,
      fnb: fnb,
      cash: cash,
      qris: qris,
      total: cash + qris,
    );
  }

  Shift _projectShift(_ShiftState s) => Shift(
        id: s.id,
        operator: s.operator,
        openedAt: s.openedAt,
        closedAt: s.closedAt,
        openingCash: s.openingCash,
        closingCash: s.closingCash,
        summary: _summaryFor(s),
        note: s.note,
      );

  // ── Seed ──────────────────────────────────────────────────────────

  void _seedData() {
    _packages.addAll([
      const Package(
        id: 'pkg-30',
        name: '30 Menit',
        durationMinutes: 30,
        price: 10000,
        hourlyRate: 20000,
        isActive: true,
      ),
      const Package(
        id: 'pkg-60',
        name: '1 Jam',
        durationMinutes: 60,
        price: 20000,
        hourlyRate: 20000,
        isActive: true,
      ),
      const Package(
        id: 'pkg-120',
        name: '2 Jam',
        durationMinutes: 120,
        price: 38000,
        hourlyRate: 19000,
        isActive: true,
      ),
      const Package(
        id: 'pkg-180',
        name: '3 Jam (Paket Hemat)',
        durationMinutes: 180,
        price: 54000,
        hourlyRate: 18000,
        isActive: true,
      ),
    ]);

    _customers.addAll([
      const Customer(
        id: 'cus-1',
        name: 'Budi Santoso',
        phone: '081234567890',
        membership: Membership(tier: 'SILVER', isActive: true),
      ),
      const Customer(id: 'cus-2', name: 'Rina Wijaya', phone: '081298765432'),
      const Customer(
        id: 'cus-3',
        name: 'Agus Pratama',
        phone: '085711122233',
        membership: Membership(tier: 'GOLD', isActive: true),
      ),
      const Customer(
        id: 'cus-4',
        name: 'Dewi Lestari',
        phone: '081355566677',
        membership: Membership(tier: 'SILVER', isActive: true),
      ),
      const Customer(id: 'cus-5', name: 'Bagus Nugroho', phone: '087812345678'),
      const Customer(
        id: 'cus-6',
        name: 'Siti Rahayu',
        phone: '089966677788',
        // Membership kedaluwarsa — harus terlihat berbeda dari yang aktif,
        // supaya operator tidak memberi harga member kepada yang sudah habis.
        membership: Membership(tier: 'SILVER', isActive: false),
      ),
      const Customer(id: 'cus-7', name: 'Rizky Maulana', phone: '082199988877'),
    ]);

    _products.addAll([
      const FnbProduct(
          id: 'fnb-1',
          category: 'Minuman',
          name: 'Teh Manis',
          price: 5000,
          isAvailable: true,
          stock: 24),
      const FnbProduct(
          id: 'fnb-2',
          category: 'Minuman',
          name: 'Kopi Hitam',
          price: 7000,
          isAvailable: true,
          stock: 18),
      const FnbProduct(
          id: 'fnb-3',
          category: 'Minuman',
          name: 'Air Mineral',
          price: 4000,
          isAvailable: true,
          stock: 40),
      const FnbProduct(
          id: 'fnb-4',
          category: 'Makanan',
          name: 'Mie Goreng',
          price: 12000,
          isAvailable: true,
          stock: 10),
      const FnbProduct(
          id: 'fnb-5',
          category: 'Makanan',
          name: 'Nasi Goreng',
          price: 15000,
          isAvailable: true,
          stock: 8),
      const FnbProduct(
          id: 'fnb-6',
          category: 'Snack',
          name: 'Kentang Goreng',
          price: 10000,
          isAvailable: true,
          stock: 0),
    ]);

    for (var i = 1; i <= 6; i++) {
      final code = 'ST${i.toString().padLeft(2, '0')}';
      _stations.add(Station(
        id: 'sta-$i',
        code: code,
        name: 'Station $i',
        consoleType: _seedConsoleFor(code),
        status: i == 6
            ? StationMasterStatus.maintenance
            : StationMasterStatus.active,
        // device SENGAJA null.
        //
        // Dalam mode kontrol langsung (DEC-015) tidak ada server yang melacak
        // heartbeat TV, jadi satu-satunya kebenaran soal sambungan TV ada di
        // `TvSyncService` — dan itu nyata. Dulu di sini ada device palsu
        // dengan status ONLINE/OFFLINE karangan; hasilnya kartu station dan
        // layar Status TV menampilkan dua angka berbeda untuk hal yang sama,
        // dan operator tidak tahu mana yang benar.
      ));
    }

    // Kondisi awal dibuat beragam supaya dashboard langsung bisa
    // ditunjukkan ke operator asli untuk memvalidasi alur kasir.
    final now = DateTime.now().toUtc();

    // Shift dibuka lebih dulu agar pembayaran seed jatuh di dalam
    // jendela waktunya dan ringkasan shift tidak nol.
    _currentShift = _ShiftState(
      id: _uuid.v4(),
      operator: const ActorRef(id: 'usr-op', name: 'Operator'),
      openedAt: now.subtract(const Duration(hours: 4)),
      openingCash: 200000,
    );

    _spawnSeedSession(
      stationId: 'sta-1',
      packageId: 'pkg-60',
      mode: SessionMode.prepaid,
      customerId: 'cus-1',
      startedAt: now.subtract(const Duration(minutes: 18)),
      rentalPaid: true,
      // (produk, qty, status order, dibuat berapa menit lalu)
      extraFnb: [
        ('fnb-1', 2, FnbOrderStatus.delivered, 14),
        ('fnb-4', 1, FnbOrderStatus.ready, 6),
        ('fnb-3', 2, FnbOrderStatus.pending, 1),
      ],
    );

    _spawnSeedSession(
      stationId: 'sta-3',
      packageId: 'pkg-60',
      mode: SessionMode.postpaid,
      customerName: 'Walk-in',
      startedAt: now.subtract(const Duration(minutes: 52)),
      rentalPaid: false,
      extraFnb: [
        ('fnb-2', 1, FnbOrderStatus.processing, 4),
        ('fnb-5', 1, FnbOrderStatus.pending, 12),
      ],
    );

    _spawnSeedSession(
      stationId: 'sta-5',
      packageId: 'pkg-120',
      mode: SessionMode.prepaid,
      customerId: 'cus-3',
      startedAt: null, // PENDING_PAYMENT
      rentalPaid: false,
    );
  }

  void _spawnSeedSession({
    required String stationId,
    required String packageId,
    required SessionMode mode,
    DateTime? startedAt,
    bool rentalPaid = false,
    String? customerId,
    String? customerName,
    List<(String, int, FnbOrderStatus, int)> extraFnb = const [],
  }) {
    final pkg = _packages.firstWhere((p) => p.id == packageId);
    final st = _stations.firstWhere((s) => s.id == stationId);
    final id = _uuid.v4();
    _sessionSeq++;

    final state = _SessionState(
      id: id,
      code: 'S-${_dateStamp()}-${_sessionSeq.toString().padLeft(4, '0')}',
      station: StationRef(id: st.id, code: st.code, name: st.name),
      package: pkg,
      mode: mode,
      customer: customerId == null
          ? null
          : CustomerRef(
              id: customerId,
              name: _customers.firstWhere((c) => c.id == customerId).name),
      customerName: customerName,
      createdAt: startedAt ?? DateTime.now().toUtc(),
    );

    state.items.add(_SessionItemState(
      id: _uuid.v4(),
      type: SessionItemType.rental,
      name: 'Paket ${pkg.name}',
      qty: 1,
      unitPrice: pkg.price,
      isPaid: rentalPaid,
      meta: {'duration_minutes': pkg.durationMinutes},
      createdAt: state.createdAt,
    ));

    if (rentalPaid) {
      state.paid += pkg.price;
      _recordPayment(
        PaymentMethod.cash,
        pkg.price,
        startedAt ?? DateTime.now().toUtc(),
      );
    }

    if (startedAt != null) {
      state.status = SessionStatus.active;
      state.startedAt = startedAt;
      state.endAt = startedAt.add(Duration(minutes: pkg.durationMinutes));
    }

    // Setiap F&B seed juga dibuatkan FnbOrder, bukan hanya session item.
    // Tanpa ini antrian F&B kosong saat app pertama dibuka dan layarnya
    // tidak bisa dinilai.
    for (final (pid, qty, status, agoMinutes) in extraFnb) {
      final p = _products.firstWhere((e) => e.id == pid);
      final at = DateTime.now().toUtc().subtract(Duration(minutes: agoMinutes));

      state.items.add(_SessionItemState(
        id: _uuid.v4(),
        type: SessionItemType.fnb,
        name: p.name,
        qty: qty,
        unitPrice: p.price,
        isPaid: status == FnbOrderStatus.delivered,
        meta: const {},
        createdAt: at,
      ));

      if (status == FnbOrderStatus.delivered) {
        state.paid += p.price * qty;
        _recordPayment(PaymentMethod.cash, p.price * qty, at);
      }

      _orderSeq++;
      final order = FnbOrder(
        id: _uuid.v4(),
        code: 'FB-${_orderSeq.toString().padLeft(4, '0')}',
        status: status,
        sessionId: state.id,
        stationCode: state.station.code,
        items: [
          FnbOrderItem(
            productId: p.id,
            name: p.name,
            qty: qty,
            unitPrice: p.price,
            subtotal: p.price * qty,
          ),
        ],
        total: p.price * qty,
        source: 'OPERATOR',
        createdAt: at,
      );
      _orders[order.id] = order;
    }

    _sessions[id] = state;
    _attachToStation(state);
  }

  String _dateStamp() {
    final n = DateTime.now();
    return '${n.year}${n.month.toString().padLeft(2, '0')}'
        '${n.day.toString().padLeft(2, '0')}';
  }

  // ── Proyeksi state -> model kontrak ───────────────────────────────

  Session _project(_SessionState s) {
    final now = ServerTime.instance.now;

    // Status turunan: WARNING / EXPIRED dihitung dari end_at, persis seperti
    // scheduler server nanti.
    var status = s.status;
    if (s.endAt != null && status == SessionStatus.active) {
      final left = s.endAt!.difference(now);
      if (left.isNegative) {
        status = SessionStatus.expired;
      } else if (left <= const Duration(minutes: 10)) {
        status = SessionStatus.warning;
      }
    }

    final items = s.items
        .map((i) => SessionItem(
              id: i.id,
              type: i.type,
              name: i.name,
              qty: i.qty,
              unitPrice: i.unitPrice,
              subtotal: i.unitPrice * i.qty,
              isPaid: i.isPaid,
              meta: i.meta,
              createdAt: i.createdAt,
              createdBy: const ActorRef(id: 'usr-op', name: 'Operator'),
            ))
        .toList(growable: false);

    int sumOf(SessionItemType t) => items
        .where((i) => i.type == t)
        .fold(0, (a, i) => a + i.subtotal);

    final rental = sumOf(SessionItemType.rental);
    final fnb = sumOf(SessionItemType.fnb);
    final extend = sumOf(SessionItemType.extend);
    final discount = sumOf(SessionItemType.discount);
    final adjustment = sumOf(SessionItemType.adjustment);
    final grand = rental + fnb + extend + adjustment - discount;

    final deadline = s.endAt?.add(graceWindow);
    final extendable = s.endAt != null &&
        const {
          SessionStatus.active,
          SessionStatus.warning,
          SessionStatus.expired,
        }.contains(status) &&
        !now.isAfter(deadline!);

    return Session(
      id: s.id,
      code: s.code,
      status: status,
      mode: s.mode,
      station: s.station,
      customer: s.customer,
      customerName: s.customerName,
      package: s.package,
      hourlyRate: s.package.hourlyRate,
      startedAt: s.startedAt,
      endAt: s.endAt,
      endedAt: s.endedAt,
      extendable: extendable,
      extendDeadlineAt: deadline,
      items: items,
      totals: SessionTotals(
        rental: rental,
        fnb: fnb,
        extend: extend,
        discount: discount,
        adjustment: adjustment,
        grandTotal: grand,
        paid: s.paid,
        balanceDue: (grand - s.paid).clamp(0, 1 << 31),
      ),
      createdAt: s.createdAt,
      updatedAt: s.updatedAt,
    );
  }

  void _attachToStation(_SessionState s) {
    final idx = _stations.indexWhere((st) => st.id == s.station.id);
    if (idx < 0) return;
    final projected = _project(s);
    final old = _stations[idx];
    _stations[idx] = Station(
      id: old.id,
      code: old.code,
      name: old.name,
      consoleType: old.consoleType,
      status: old.status,
      device: old.device,
      session: projected.status.occupiesStation
          ? StationSessionSummary(
              id: projected.id,
              status: projected.status,
              startedAt: projected.startedAt,
              endAt: projected.endAt,
              customerLabel: projected.customerLabel,
              balanceDue: projected.totals.balanceDue,
            )
          : null,
    );
  }

  void _detachFromStation(String stationId) {
    final idx = _stations.indexWhere((st) => st.id == stationId);
    if (idx < 0) return;
    final old = _stations[idx];
    _stations[idx] = Station(
      id: old.id,
      code: old.code,
      name: old.name,
      consoleType: old.consoleType,
      status: old.status,
      device: old.device,
    );
  }

  _SessionState _require(String id) {
    final s = _sessions[id];
    if (s == null) {
      throw const ApiError(
        code: ApiErrorCode.notFound,
        message: 'Sesi tidak ditemukan.',
        httpStatus: 404,
      );
    }
    return s;
  }

  // ── Master data ───────────────────────────────────────────────────

  @override
  Future<List<Station>> fetchStations() => _call(() {
        for (final s in _sessions.values) {
          if (_project(s).status.occupiesStation) _attachToStation(s);
        }
        return List<Station>.unmodifiable(_stations);
      });

  @override
  Future<List<Package>> fetchPackages() =>
      _call(() => _packages.where((p) => p.isActive).toList(growable: false));

  @override
  Future<List<Customer>> searchCustomers(String query) => _call(() {
        final q = query.trim().toLowerCase();
        if (q.isEmpty) return List<Customer>.unmodifiable(_customers);
        return _customers
            .where((c) =>
                c.name.toLowerCase().contains(q) ||
                (c.phone ?? '').contains(q))
            .toList(growable: false);
      });

  // ── Session ───────────────────────────────────────────────────────

  @override
  Future<Session> fetchSession(String sessionId) =>
      _call(() => _project(_require(sessionId)));

  @override
  Future<List<Session>> fetchSessions({Set<SessionStatus>? statuses}) =>
      _call(() {
        final all = _sessions.values.map(_project);
        if (statuses == null || statuses.isEmpty) {
          return all.toList(growable: false);
        }
        return all
            .where((s) => statuses.contains(s.status))
            .toList(growable: false);
      });

  @override
  Future<Session> createSession({
    required String stationId,
    required String packageId,
    required SessionMode mode,
    required String idempotencyKey,
    String? customerId,
    String? customerName,
  }) =>
      _idempotent(idempotencyKey, () => _call(() {
            final station = _stations.firstWhere(
              (s) => s.id == stationId,
              orElse: () => throw const ApiError(
                code: ApiErrorCode.notFound,
                message: 'Station tidak ditemukan.',
                httpStatus: 404,
              ),
            );

            if (station.status != StationMasterStatus.active) {
              throw const ApiError(
                code: ApiErrorCode.stationNotAvailable,
                message: 'Station sedang maintenance atau dinonaktifkan.',
                httpStatus: 409,
              );
            }
            if (station.session != null) {
              throw const ApiError(
                code: ApiErrorCode.stationHasActiveSession,
                message: 'Station ini sudah punya sesi berjalan.',
                httpStatus: 409,
              );
            }

            final pkg = _packages.firstWhere(
              (p) => p.id == packageId,
              orElse: () => throw const ApiError(
                code: ApiErrorCode.notFound,
                message: 'Paket tidak ditemukan.',
                httpStatus: 404,
              ),
            );

            _sessionSeq++;
            final now = ServerTime.instance.now;
            final state = _SessionState(
              id: _uuid.v4(),
              code:
                  'S-${_dateStamp()}-${_sessionSeq.toString().padLeft(4, '0')}',
              station: StationRef(
                  id: station.id, code: station.code, name: station.name),
              package: pkg,
              mode: mode,
              // Dulu memakai `firstWhere` tanpa pengaman: customer_id yang
              // tidak ada akan melempar StateError mentah, bukan ApiError,
              // sehingga UI menampilkan "kesalahan tidak terduga" alih-alih
              // pesan yang berguna.
              customer: customerId == null
                  ? null
                  : CustomerRef(
                      id: customerId,
                      name: _customers
                          .firstWhere(
                            (c) => c.id == customerId,
                            orElse: () => throw const ApiError(
                              code: ApiErrorCode.notFound,
                              message: 'Customer tidak ditemukan.',
                              httpStatus: 404,
                            ),
                          )
                          .name),
              customerName: customerId == null
                  ? (customerName?.trim().isNotEmpty == true
                      ? customerName!.trim()
                      : 'Walk-in')
                  : null,
              createdAt: now,
            );

            state.items.add(_SessionItemState(
              id: _uuid.v4(),
              type: SessionItemType.rental,
              name: 'Paket ${pkg.name}',
              qty: 1,
              unitPrice: pkg.price,
              isPaid: false,
              meta: {'duration_minutes': pkg.durationMinutes},
              createdAt: now,
            ));

            // Kontrak §7: Prepaid menunggu pembayaran; Postpaid langsung jalan.
            if (mode == SessionMode.postpaid) {
              state.status = SessionStatus.active;
              state.startedAt = now;
              state.endAt = now.add(Duration(minutes: pkg.durationMinutes));
            } else {
              state.status = SessionStatus.pendingPayment;
            }

            _sessions[state.id] = state;
            _attachToStation(state);
            return _project(state);
          }));

  @override
  Future<PaymentResult> addPayment({
    required String sessionId,
    required PaymentMethod method,
    required int amount,
    required String idempotencyKey,
    String? reference,
    String? note,
  }) =>
      _idempotent(idempotencyKey, () => _call(() {
            final s = _require(sessionId);
            final before = _project(s);

            if (method.requiresReference &&
                (reference == null || reference.trim().isEmpty)) {
              throw const ApiError(
                code: ApiErrorCode.paymentReferenceRequired,
                message: 'Pembayaran QRIS wajib mencantumkan nomor referensi.',
                httpStatus: 422,
              );
            }
            if (amount <= 0) {
              throw const ApiError(
                code: ApiErrorCode.validationFailed,
                message: 'Data yang dikirim tidak valid.',
                details: {'amount': ['Nominal harus lebih dari nol.']},
                httpStatus: 422,
              );
            }
            if (amount > before.totals.balanceDue) {
              throw ApiError(
                code: ApiErrorCode.paymentAmountExceedsBalance,
                message: 'Nominal melebihi sisa tagihan '
                    '(${before.totals.balanceDue}).',
                details: {'balance_due': before.totals.balanceDue},
                httpStatus: 422,
              );
            }
            if (before.status == SessionStatus.completed ||
                before.status == SessionStatus.cancelled) {
              throw const ApiError(
                code: ApiErrorCode.sessionStatusInvalid,
                message: 'Sesi sudah selesai. Pembayaran tidak bisa ditambah.',
                httpStatus: 409,
              );
            }

            final now = ServerTime.instance.now;
            s.paid += amount;
            _recordPayment(method, amount, now);

            // Bayar rental -> sesi mulai (kontrak §7).
            final rental = s.items
                .firstWhere((i) => i.type == SessionItemType.rental);
            if (!rental.isPaid && s.paid >= rental.unitPrice * rental.qty) {
              rental.isPaid = true;
              if (s.status == SessionStatus.pendingPayment) {
                s.status = SessionStatus.active;
                s.startedAt = now;
                s.endAt =
                    now.add(Duration(minutes: s.package.durationMinutes));
              }
            }

            s.updatedAt = now;
            _attachToStation(s);

            return PaymentResult(
              payment: Payment(
                id: _uuid.v4(),
                method: method,
                amount: amount,
                status: 'CONFIRMED',
                confirmedAt: now,
                reference: reference,
                actor: const ActorRef(id: 'usr-op', name: 'Operator'),
              ),
              session: _project(s),
            );
          }));

  @override
  Future<ExtendResult> extendSession({
    required String sessionId,
    required int durationMinutes,
    required String idempotencyKey,
  }) =>
      _idempotent(idempotencyKey, () => _call(() {
            final s = _require(sessionId);
            final current = _project(s);
            final now = ServerTime.instance.now;

            // DEC-007: kelipatan 30, minimum 30.
            if (durationMinutes < 30 || durationMinutes % 30 != 0) {
              throw const ApiError(
                code: ApiErrorCode.extendDurationInvalid,
                message: 'Durasi harus kelipatan 30 menit.',
                details: {
                  'duration_minutes': ['Durasi harus kelipatan 30 menit.']
                },
                httpStatus: 422,
              );
            }

            if (!const {
              SessionStatus.active,
              SessionStatus.warning,
              SessionStatus.expired,
            }.contains(current.status)) {
              throw const ApiError(
                code: ApiErrorCode.sessionStatusInvalid,
                message: 'Sesi pada status ini tidak bisa di-extend.',
                httpStatus: 409,
              );
            }

            final previousEnd = s.endAt!;
            final deadline = previousEnd.add(graceWindow);

            // DEC-007: grace 10 menit.
            if (now.isAfter(deadline)) {
              throw ApiError(
                code: ApiErrorCode.extendGraceExpired,
                message: 'Sesi sudah lewat 10 menit dari waktu habis. '
                    'Lakukan checkout lalu buat sesi baru.',
                details: {
                  'end_at': previousEnd.toIso8601String(),
                  'grace_until': deadline.toIso8601String(),
                },
                httpStatus: 409,
              );
            }

            // DEC-007: dihitung dari end_at LAMA, bukan dari waktu approve.
            // Waktu grace yang sudah lewat tetap terhitung.
            final newEnd = previousEnd.add(Duration(minutes: durationMinutes));
            final price = extendPrice(
              hourlyRate: s.package.hourlyRate,
              minutes: durationMinutes,
            );

            s.endAt = newEnd;
            s.status = SessionStatus.active;
            s.updatedAt = now;
            s.items.add(_SessionItemState(
              id: _uuid.v4(),
              type: SessionItemType.extend,
              name: 'Extend $durationMinutes menit',
              qty: 1,
              unitPrice: price,
              isPaid: false,
              meta: {'duration_minutes': durationMinutes},
              createdAt: now,
            ));

            _attachToStation(s);

            return ExtendResult(
              session: _project(s),
              durationMinutes: durationMinutes,
              price: price,
              previousEndAt: previousEnd,
              newEndAt: newEnd,
            );
          }));

  @override
  Future<Session> swapStation({
    required String sessionId,
    required String targetStationId,
    required String idempotencyKey,
    String? reason,
  }) =>
      _idempotent(idempotencyKey, () => _call(() {
            final s = _require(sessionId);

            if (s.station.id == targetStationId) {
              throw const ApiError(
                code: ApiErrorCode.targetStationSame,
                message: 'Station tujuan sama dengan station saat ini.',
                httpStatus: 409,
              );
            }

            final target = _stations.firstWhere(
              (st) => st.id == targetStationId,
              orElse: () => throw const ApiError(
                code: ApiErrorCode.notFound,
                message: 'Station tujuan tidak ditemukan.',
                httpStatus: 404,
              ),
            );

            if (target.status != StationMasterStatus.active ||
                target.session != null) {
              throw const ApiError(
                code: ApiErrorCode.stationNotAvailable,
                message: 'Station tujuan tidak tersedia.',
                httpStatus: 409,
              );
            }

            // Atomic: hanya referensi station yang berubah.
            // session_id, end_at, items, payments TETAP (PRD §15, R07).
            final from = s.station.id;
            s.station =
                StationRef(id: target.id, code: target.code, name: target.name);
            s.updatedAt = ServerTime.instance.now;

            _detachFromStation(from);
            _attachToStation(s);
            return _project(s);
          }));

  @override
  Future<CheckoutResult> checkout({
    required String sessionId,
    required List<({PaymentMethod method, int amount, String? reference})>
        payments,
    required String idempotencyKey,
  }) =>
      _idempotent(idempotencyKey, () => _call(() {
            final s = _require(sessionId);
            final now = ServerTime.instance.now;

            if (s.status == SessionStatus.completed) {
              throw const ApiError(
                code: ApiErrorCode.sessionStatusInvalid,
                message: 'Sesi sudah selesai.',
                httpStatus: 409,
              );
            }

            final actualMinutes = s.startedAt == null
                ? 0
                : now.difference(s.startedAt!).inMinutes;

            // DEC-009: rounding HANYA untuk Postpaid.
            // Prepaid pakai harga paket apa adanya.
            var billable = s.package.durationMinutes;
            if (s.mode == SessionMode.postpaid) {
              billable = billableMinutes(actualMinutes);
              final rental = s.items
                  .firstWhere((i) => i.type == SessionItemType.rental);
              rental.unitPrice =
                  ((s.package.hourlyRate * billable) + 59) ~/ 60;
              rental.name = 'Rental $billable menit';
              rental.meta = {'duration_minutes': billable};
            }

            final projected = _project(s);
            final totalPaying =
                payments.fold<int>(0, (a, p) => a + p.amount);

            if (totalPaying < projected.totals.balanceDue) {
              throw ApiError(
                code: ApiErrorCode.checkoutInsufficientPayment,
                message: 'Pembayaran belum menutupi sisa tagihan '
                    '(${projected.totals.balanceDue}).',
                details: {'balance_due': projected.totals.balanceDue},
                httpStatus: 422,
              );
            }

            for (final p in payments) {
              if (p.method.requiresReference &&
                  (p.reference == null || p.reference!.trim().isEmpty)) {
                throw const ApiError(
                  code: ApiErrorCode.paymentReferenceRequired,
                  message:
                      'Pembayaran QRIS wajib mencantumkan nomor referensi.',
                  httpStatus: 422,
                );
              }
            }

            s.paid += totalPaying;
            for (final p in payments) {
              _recordPayment(p.method, p.amount, now);
            }
            for (final i in s.items) {
              i.isPaid = true;
            }
            s.status = SessionStatus.completed;
            s.endedAt = now;
            s.updatedAt = now;
            _detachFromStation(s.station.id);

            _receiptSeq++;
            final finalSession = _project(s);

            return CheckoutResult(
              session: finalSession,
              receipt: Receipt(
                number:
                    'INV-${_dateStamp()}-${_receiptSeq.toString().padLeft(4, '0')}',
                issuedAt: now,
                // Dua-duanya dikirim supaya operator bisa menjelaskan
                // rounding DEC-009 ke customer.
                billableDurationMinutes: billable,
                actualDurationMinutes: actualMinutes,
                lines: finalSession.items
                    .map((i) => ReceiptLine(
                        name: i.name, qty: i.qty, subtotal: i.subtotal))
                    .toList(growable: false),
                totals: finalSession.totals,
                payments: payments
                    .map((p) => Payment(
                          id: _uuid.v4(),
                          method: p.method,
                          amount: p.amount,
                          status: 'CONFIRMED',
                          confirmedAt: now,
                          reference: p.reference,
                        ))
                    .toList(growable: false),
                operator: const ActorRef(id: 'usr-op', name: 'Operator'),
              ),
            );
          }));

  @override
  Future<Session> cancelSession({
    required String sessionId,
    required String idempotencyKey,
    String? reason,
  }) =>
      _idempotent(idempotencyKey, () => _call(() {
            final s = _require(sessionId);

            // Kontrak §7: v1 hanya dari PENDING_PAYMENT.
            if (s.status != SessionStatus.pendingPayment) {
              throw const ApiError(
                code: ApiErrorCode.sessionStatusInvalid,
                message:
                    'Hanya sesi yang belum dibayar yang bisa dibatalkan.',
                httpStatus: 409,
              );
            }

            s.status = SessionStatus.cancelled;
            s.endedAt = ServerTime.instance.now;
            s.updatedAt = s.endedAt!;
            _detachFromStation(s.station.id);
            return _project(s);
          }));

  // ── F&B ───────────────────────────────────────────────────────────

  @override
  Future<List<FnbProduct>> fetchFnbProducts() =>
      _call(() => List<FnbProduct>.unmodifiable(_products));

  @override
  Future<FnbOrderResult> createFnbOrder({
    required String sessionId,
    required List<({String productId, int qty})> items,
    required String idempotencyKey,
    String? note,
  }) =>
      _idempotent(idempotencyKey, () => _call(() {
            final s = _require(sessionId);
            final current = _project(s);

            // Ghost order (T05) — kontrak §8.
            if (!const {SessionStatus.active, SessionStatus.warning}
                .contains(current.status)) {
              throw const ApiError(
                code: ApiErrorCode.sessionNotOrderable,
                message:
                    'Sesi tidak aktif. Order F&B tidak bisa ditambahkan.',
                httpStatus: 409,
              );
            }
            if (items.isEmpty) {
              throw const ApiError(
                code: ApiErrorCode.validationFailed,
                message: 'Data yang dikirim tidak valid.',
                details: {'items': ['Pilih minimal satu item.']},
                httpStatus: 422,
              );
            }

            final now = ServerTime.instance.now;
            final orderItems = <FnbOrderItem>[];

            for (final it in items) {
              final p = _products.firstWhere(
                (e) => e.id == it.productId,
                orElse: () => throw const ApiError(
                  code: ApiErrorCode.notFound,
                  message: 'Produk tidak ditemukan.',
                  httpStatus: 404,
                ),
              );
              // Harga SELALU dari server. Client tidak pernah mengirim harga.
              orderItems.add(FnbOrderItem(
                productId: p.id,
                name: p.name,
                qty: it.qty,
                unitPrice: p.price,
                subtotal: p.price * it.qty,
              ));
              s.items.add(_SessionItemState(
                id: _uuid.v4(),
                type: SessionItemType.fnb,
                name: p.name,
                qty: it.qty,
                unitPrice: p.price,
                isPaid: false,
                meta: const {},
                createdAt: now,
              ));
            }

            _orderSeq++;
            final order = FnbOrder(
              id: _uuid.v4(),
              code: 'FB-${_orderSeq.toString().padLeft(4, '0')}',
              status: FnbOrderStatus.pending,
              sessionId: s.id,
              stationCode: s.station.code,
              items: orderItems,
              total: orderItems.fold(0, (a, i) => a + i.subtotal),
              source: 'OPERATOR',
              createdAt: now,
              note: note,
            );

            _orders[order.id] = order;
            s.updatedAt = now;
            _attachToStation(s);

            return FnbOrderResult(order: order, session: _project(s));
          }));

  @override
  Future<List<FnbOrder>> fetchFnbOrders({Set<FnbOrderStatus>? statuses}) =>
      _call(() {
        final all = _orders.values.toList()
          ..sort((a, b) => a.createdAt.compareTo(b.createdAt));
        if (statuses == null || statuses.isEmpty) return all;
        return all.where((o) => statuses.contains(o.status)).toList();
      });

  static String _seedConsoleFor(String stationCode) => switch (stationCode) {
        'ST01' => 'PS5 VIP',
        'ST02' => 'PS5 Reguler',
        'ST03' => 'PS5 Reguler',
        'ST04' => 'PS4 Pro',
        'ST05' => 'PS5 VIP',
        _ => 'PS4 Slim',
      };

  // ── Device ────────────────────────────────────────────────────────

  /// Daftar device dari **server** — kosong, dan itu jawaban yang benar.
  ///
  /// `GET /devices` adalah endpoint Laravel (kontrak §9) yang melaporkan
  /// heartbeat TV. Selama Laravel belum ada, tidak ada server yang menerima
  /// heartbeat, jadi daftarnya memang kosong.
  ///
  /// Sebelumnya di sini dikarang enam device beserta status online/offline.
  /// Itu membuat layar Status TV terlihat berfungsi padahal angkanya fiksi,
  /// dan bertabrakan dengan status sambungan TV yang sebenarnya.
  ///
  /// Status TV yang nyata ada di `TvSyncService`, bukan di sini.
  @override
  Future<DeviceList> fetchDevices() => _call(() => DeviceList.empty);

  // ── Shift ─────────────────────────────────────────────────────────

  @override
  Future<Shift?> fetchCurrentShift() => _call(
        () => _currentShift == null ? null : _projectShift(_currentShift!),
      );

  @override
  Future<Shift> openShift({
    required int openingCash,
    required String idempotencyKey,
  }) =>
      _idempotent(idempotencyKey, () => _call(() {
            if (_currentShift != null) {
              throw const ApiError(
                code: ApiErrorCode.sessionStatusInvalid,
                message: 'Masih ada shift yang berjalan. '
                    'Tutup shift itu dulu.',
                httpStatus: 409,
              );
            }
            if (openingCash < 0) {
              throw const ApiError(
                code: ApiErrorCode.validationFailed,
                message: 'Data yang dikirim tidak valid.',
                details: {'opening_cash': ['Kas awal tidak boleh negatif.']},
                httpStatus: 422,
              );
            }

            final shift = _ShiftState(
              id: _uuid.v4(),
              operator: const ActorRef(id: 'usr-op', name: 'Operator'),
              openedAt: ServerTime.instance.now,
              openingCash: openingCash,
            );
            _currentShift = shift;
            return _projectShift(shift);
          }));

  @override
  Future<Shift> closeShift({
    required String shiftId,
    required int closingCash,
    required String idempotencyKey,
    String? note,
  }) =>
      _idempotent(idempotencyKey, () => _call(() {
            final shift = _currentShift;
            if (shift == null || shift.id != shiftId) {
              throw const ApiError(
                code: ApiErrorCode.notFound,
                message: 'Shift tidak ditemukan atau sudah ditutup.',
                httpStatus: 404,
              );
            }
            if (closingCash < 0) {
              throw const ApiError(
                code: ApiErrorCode.validationFailed,
                message: 'Data yang dikirim tidak valid.',
                details: {'closing_cash': ['Kas akhir tidak boleh negatif.']},
                httpStatus: 422,
              );
            }

            // Shift tidak boleh ditutup kalau masih ada sesi berjalan —
            // tagihannya belum selesai dan pertanggungjawaban kas jadi
            // tidak bisa ditutup.
            final openSessions = _sessions.values
                .map(_project)
                .where((s) => s.status.occupiesStation)
                .length;
            if (openSessions > 0) {
              throw ApiError(
                code: ApiErrorCode.sessionStatusInvalid,
                message: 'Masih ada $openSessions sesi berjalan. '
                    'Selesaikan checkout semuanya sebelum menutup shift.',
                details: {'open_sessions': openSessions},
                httpStatus: 409,
              );
            }

            shift.closedAt = ServerTime.instance.now;
            shift.closingCash = closingCash;
            shift.note = note;

            final closed = _projectShift(shift);
            _shiftHistory.insert(0, shift);
            _currentShift = null;
            return closed;
          }));

  @override
  Future<List<Shift>> fetchShiftHistory({int limit = 20}) => _call(
        () => _shiftHistory
            .take(limit)
            .map(_projectShift)
            .toList(growable: false),
      );

  @override
  Future<FnbOrder> updateFnbOrderStatus({
    required String orderId,
    required FnbOrderStatus status,
  }) =>
      _call(() {
        final o = _orders[orderId];
        if (o == null) {
          throw const ApiError(
            code: ApiErrorCode.notFound,
            message: 'Order tidak ditemukan.',
            httpStatus: 404,
          );
        }
        // Kontrak §8: transisi sah PENDING -> PROCESSING -> READY ->
        // DELIVERED. Cancel HANYA dari PENDING atau PROCESSING — order yang
        // sudah siap atau sudah diantar tidak bisa dibatalkan begitu saja
        // karena barangnya sudah dibuat.
        final cancellable = status == FnbOrderStatus.cancelled &&
            const {FnbOrderStatus.pending, FnbOrderStatus.processing}
                .contains(o.status);

        if (o.status.next != status && !cancellable) {
          throw ApiError(
            code: ApiErrorCode.fnbStatusTransitionInvalid,
            message: status == FnbOrderStatus.cancelled
                ? 'Order yang sudah ${o.status.label.toLowerCase()} '
                    'tidak bisa dibatalkan.'
                : 'Perubahan status order tidak sah.',
            details: {'from': o.status.wire, 'to': status.wire},
            httpStatus: 409,
          );
        }
        final updated = FnbOrder(
          id: o.id,
          code: o.code,
          status: status,
          sessionId: o.sessionId,
          stationCode: o.stationCode,
          items: o.items,
          total: o.total,
          source: o.source,
          createdAt: o.createdAt,
          note: o.note,
        );
        _orders[o.id] = updated;
        return updated;
      });
}

// ─── State internal (mutable) ─────────────────────────────────────────

class _SessionState {
  _SessionState({
    required this.id,
    required this.code,
    required this.station,
    required this.package,
    required this.mode,
    required this.createdAt,
    this.customer,
    this.customerName,
  }) : updatedAt = createdAt;

  final String id;
  final String code;
  StationRef station;
  final Package package;
  final SessionMode mode;
  final CustomerRef? customer;
  final String? customerName;

  SessionStatus status = SessionStatus.pendingPayment;
  DateTime? startedAt;
  DateTime? endAt;
  DateTime? endedAt;
  int paid = 0;

  final List<_SessionItemState> items = [];
  final DateTime createdAt;
  DateTime updatedAt;
}

class _PaymentRecord {
  const _PaymentRecord({
    required this.method,
    required this.amount,
    required this.at,
  });

  final PaymentMethod method;
  final int amount;
  final DateTime at;
}

class _ShiftState {
  _ShiftState({
    required this.id,
    required this.operator,
    required this.openedAt,
    required this.openingCash,
  });

  final String id;
  final ActorRef operator;
  final DateTime openedAt;
  final int openingCash;

  DateTime? closedAt;
  int? closingCash;
  String? note;
}

class _SessionItemState {
  _SessionItemState({
    required this.id,
    required this.type,
    required this.name,
    required this.qty,
    required this.unitPrice,
    required this.isPaid,
    required this.meta,
    required this.createdAt,
  });

  final String id;
  final SessionItemType type;
  String name;
  final int qty;
  int unitPrice;
  bool isPaid;
  Map<String, dynamic> meta;
  final DateTime createdAt;
}
