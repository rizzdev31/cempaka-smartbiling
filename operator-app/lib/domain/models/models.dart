import 'enums.dart';

/// Model ditranskrip dari `docs/contracts/API.md` §6–§8.
///
/// ATURAN (DEC-012 syarat 1): field yang tidak ada di kontrak TIDAK BOLEH
/// ditambahkan di sini. Butuh field baru -> ubah kontrak dulu, catat di
/// `contracts/CHANGELOG.md`, baru ubah model.

DateTime? _dt(Object? v) => v == null ? null : DateTime.parse(v as String).toUtc();
int _int(Object? v) => (v as num?)?.toInt() ?? 0;

// ─── Referensi ringkas ──────────────────────────────────────────────

class StationRef {
  const StationRef({required this.id, required this.code, required this.name});

  final String id;
  final String code;
  final String name;

  factory StationRef.fromJson(Map<String, dynamic> j) => StationRef(
        id: j['id'] as String,
        code: j['code'] as String,
        name: j['name'] as String? ?? j['code'] as String,
      );
}

class CustomerRef {
  const CustomerRef({required this.id, required this.name});

  final String id;
  final String name;

  factory CustomerRef.fromJson(Map<String, dynamic> j) =>
      CustomerRef(id: j['id'] as String, name: j['name'] as String);
}

class ActorRef {
  const ActorRef({required this.id, required this.name});

  final String id;
  final String name;

  factory ActorRef.fromJson(Map<String, dynamic> j) =>
      ActorRef(id: j['id'] as String, name: j['name'] as String);
}

// ─── Package — kontrak §6 ───────────────────────────────────────────

class Package {
  const Package({
    required this.id,
    required this.name,
    required this.durationMinutes,
    required this.price,
    required this.hourlyRate,
    required this.isActive,
  });

  final String id;
  final String name;
  final int durationMinutes;
  final int price;

  /// Dihitung SERVER. Dipakai client hanya untuk estimasi harga extend —
  /// harga final tetap dari server (DEC-007).
  final int hourlyRate;
  final bool isActive;

  factory Package.fromJson(Map<String, dynamic> j) => Package(
        id: j['id'] as String,
        name: j['name'] as String,
        durationMinutes: _int(j['duration_minutes']),
        price: _int(j['price']),
        hourlyRate: _int(j['hourly_rate']),
        isActive: j['is_active'] as bool? ?? true,
      );
}

// ─── Customer — kontrak §6 ──────────────────────────────────────────

class Membership {
  const Membership({required this.tier, required this.isActive});

  final String tier;
  final bool isActive;

  factory Membership.fromJson(Map<String, dynamic> j) => Membership(
        tier: j['tier'] as String,
        isActive: j['is_active'] as bool? ?? false,
      );
}

class Customer {
  const Customer({
    required this.id,
    required this.name,
    this.phone,
    this.membership,
  });

  final String id;
  final String name;
  final String? phone;
  final Membership? membership;

  factory Customer.fromJson(Map<String, dynamic> j) => Customer(
        id: j['id'] as String,
        name: j['name'] as String,
        phone: j['phone'] as String?,
        membership: j['membership'] == null
            ? null
            : Membership.fromJson(j['membership'] as Map<String, dynamic>),
      );
}

// ─── Station — kontrak §6 ───────────────────────────────────────────

class StationSessionSummary {
  const StationSessionSummary({
    required this.id,
    required this.status,
    required this.endAt,
    required this.customerLabel,
    required this.balanceDue,
    this.startedAt,
  });

  final String id;
  final SessionStatus status;

  /// `null` saat `PENDING_PAYMENT`. Bersama [endAt] dipakai untuk menggambar
  /// proporsi waktu terpakai (kontrak §6, CHANGELOG DRAFT 2).
  final DateTime? startedAt;
  final DateTime? endAt;
  final String? customerLabel;
  final int balanceDue;

  /// Proporsi waktu yang sudah terpakai, 0..1.
  /// `null` kalau tidak bisa dihitung (sesi belum mulai atau data tidak ada).
  double? progressAt(DateTime now) {
    if (startedAt == null || endAt == null) return null;
    final total = endAt!.difference(startedAt!).inSeconds;
    if (total <= 0) return null;
    final used = now.difference(startedAt!).inSeconds;
    return (used / total).clamp(0.0, 1.0);
  }

  factory StationSessionSummary.fromJson(Map<String, dynamic> j) =>
      StationSessionSummary(
        id: j['id'] as String,
        status: SessionStatus.parse(j['status'] as String?),
        startedAt: _dt(j['started_at']),
        endAt: _dt(j['end_at']),
        customerLabel: j['customer_label'] as String?,
        balanceDue: _int(j['balance_due']),
      );
}

class DeviceSummary {
  const DeviceSummary({
    required this.id,
    required this.status,
    required this.lastSeenAt,
    required this.appVersion,
  });

  final String id;
  final DeviceStatus status;
  final DateTime? lastSeenAt;
  final String? appVersion;

  factory DeviceSummary.fromJson(Map<String, dynamic> j) => DeviceSummary(
        id: j['id'] as String,
        status: DeviceStatus.parse(j['status'] as String?),
        lastSeenAt: _dt(j['last_seen_at']),
        appVersion: j['app_version'] as String?,
      );
}

class Station {
  const Station({
    required this.id,
    required this.code,
    required this.name,
    required this.status,
    this.session,
    this.device,
  });

  final String id;
  final String code;
  final String name;
  final StationMasterStatus status;

  /// `null` = station kosong -> tampil AVAILABLE di dashboard.
  final StationSessionSummary? session;

  /// `null` = belum ada TV Agent terdaftar. Normal sampai Tahap 2.
  final DeviceSummary? device;

  StationViewStatus get viewStatus =>
      StationViewStatus.from(master: status, session: session?.status);

  factory Station.fromJson(Map<String, dynamic> j) => Station(
        id: j['id'] as String,
        code: j['code'] as String,
        name: j['name'] as String? ?? j['code'] as String,
        status: StationMasterStatus.parse(j['status'] as String?),
        session: j['session'] == null
            ? null
            : StationSessionSummary.fromJson(
                j['session'] as Map<String, dynamic>),
        device: j['device'] == null
            ? null
            : DeviceSummary.fromJson(j['device'] as Map<String, dynamic>),
      );
}

// ─── Session — kontrak §7 ───────────────────────────────────────────

class SessionItem {
  const SessionItem({
    required this.id,
    required this.type,
    required this.name,
    required this.qty,
    required this.unitPrice,
    required this.subtotal,
    required this.isPaid,
    required this.meta,
    required this.createdAt,
    this.createdBy,
  });

  final String id;
  final SessionItemType type;
  final String name;
  final int qty;
  final int unitPrice;
  final int subtotal;
  final bool isPaid;
  final Map<String, dynamic> meta;
  final DateTime createdAt;
  final ActorRef? createdBy;

  factory SessionItem.fromJson(Map<String, dynamic> j) => SessionItem(
        id: j['id'] as String,
        type: SessionItemType.parse(j['type'] as String?),
        name: j['name'] as String,
        qty: _int(j['qty']),
        unitPrice: _int(j['unit_price']),
        subtotal: _int(j['subtotal']),
        isPaid: j['is_paid'] as bool? ?? false,
        meta: (j['meta'] as Map<String, dynamic>?) ?? const {},
        createdAt: _dt(j['created_at']) ?? DateTime.now().toUtc(),
        createdBy: j['created_by'] == null
            ? null
            : ActorRef.fromJson(j['created_by'] as Map<String, dynamic>),
      );
}

class SessionTotals {
  const SessionTotals({
    required this.rental,
    required this.fnb,
    required this.extend,
    required this.discount,
    required this.adjustment,
    required this.grandTotal,
    required this.paid,
    required this.balanceDue,
  });

  final int rental;
  final int fnb;
  final int extend;
  final int discount;
  final int adjustment;
  final int grandTotal;
  final int paid;

  /// Yang ditagih saat checkout.
  final int balanceDue;

  factory SessionTotals.fromJson(Map<String, dynamic> j) => SessionTotals(
        rental: _int(j['rental']),
        fnb: _int(j['fnb']),
        extend: _int(j['extend']),
        discount: _int(j['discount']),
        adjustment: _int(j['adjustment']),
        grandTotal: _int(j['grand_total']),
        paid: _int(j['paid']),
        balanceDue: _int(j['balance_due']),
      );

  static const zero = SessionTotals(
    rental: 0,
    fnb: 0,
    extend: 0,
    discount: 0,
    adjustment: 0,
    grandTotal: 0,
    paid: 0,
    balanceDue: 0,
  );
}

class Session {
  const Session({
    required this.id,
    required this.code,
    required this.status,
    required this.mode,
    required this.station,
    required this.package,
    required this.hourlyRate,
    required this.items,
    required this.totals,
    required this.extendable,
    required this.createdAt,
    required this.updatedAt,
    this.customer,
    this.customerName,
    this.startedAt,
    this.endAt,
    this.endedAt,
    this.extendDeadlineAt,
  });

  final String id;
  final String code;
  final SessionStatus status;
  final SessionMode mode;
  final StationRef station;

  /// DEC-008: SATU customer per session. Walk-in non-member -> [customer] null
  /// dan [customerName] terisi.
  final CustomerRef? customer;
  final String? customerName;

  final Package package;
  final int hourlyRate;

  final DateTime? startedAt;

  /// Sumber tunggal perhitungan sisa waktu.
  /// TIDAK ADA `remaining_seconds` di kontrak — client menghitung sendiri
  /// dari sini + server-time offset (PRD §16, DEC-003).
  final DateTime? endAt;
  final DateTime? endedAt;

  /// Server yang memutuskan. Client TIDAK menghitung sendiri.
  final bool extendable;

  /// `end_at + 10 menit` (DEC-007). Dikirim server supaya aturan grace
  /// tidak diduplikasi di client.
  final DateTime? extendDeadlineAt;

  final List<SessionItem> items;
  final SessionTotals totals;
  final DateTime createdAt;
  final DateTime updatedAt;

  String get customerLabel => customer?.name ?? customerName ?? 'Walk-in';

  List<SessionItem> itemsOfType(SessionItemType t) =>
      items.where((i) => i.type == t).toList(growable: false);

  factory Session.fromJson(Map<String, dynamic> j) => Session(
        id: j['id'] as String,
        code: j['code'] as String,
        status: SessionStatus.parse(j['status'] as String?),
        mode: SessionMode.parse(j['mode'] as String?),
        station: StationRef.fromJson(j['station'] as Map<String, dynamic>),
        customer: j['customer'] == null
            ? null
            : CustomerRef.fromJson(j['customer'] as Map<String, dynamic>),
        customerName: j['customer_name'] as String?,
        package: Package.fromJson({
          ...j['package'] as Map<String, dynamic>,
          'hourly_rate': j['hourly_rate'],
        }),
        hourlyRate: _int(j['hourly_rate']),
        startedAt: _dt(j['started_at']),
        endAt: _dt(j['end_at']),
        endedAt: _dt(j['ended_at']),
        extendable: j['extendable'] as bool? ?? false,
        extendDeadlineAt: _dt(j['extend_deadline_at']),
        items: ((j['items'] as List?) ?? const [])
            .map((e) => SessionItem.fromJson(e as Map<String, dynamic>))
            .toList(growable: false),
        totals: j['totals'] == null
            ? SessionTotals.zero
            : SessionTotals.fromJson(j['totals'] as Map<String, dynamic>),
        createdAt: _dt(j['created_at']) ?? DateTime.now().toUtc(),
        updatedAt: _dt(j['updated_at']) ?? DateTime.now().toUtc(),
      );
}

// ─── Payment — kontrak §7 ───────────────────────────────────────────

class Payment {
  const Payment({
    required this.id,
    required this.method,
    required this.amount,
    required this.status,
    required this.confirmedAt,
    this.reference,
    this.actor,
  });

  final String id;
  final PaymentMethod method;
  final int amount;
  final String status;
  final DateTime? confirmedAt;
  final String? reference;
  final ActorRef? actor;

  factory Payment.fromJson(Map<String, dynamic> j) => Payment(
        id: j['id'] as String,
        method: PaymentMethod.parse(j['method'] as String?),
        amount: _int(j['amount']),
        status: j['status'] as String? ?? 'CONFIRMED',
        confirmedAt: _dt(j['confirmed_at']),
        reference: j['reference'] as String?,
        actor: j['actor'] == null
            ? null
            : ActorRef.fromJson(j['actor'] as Map<String, dynamic>),
      );
}

class PaymentResult {
  const PaymentResult({required this.payment, required this.session});

  final Payment payment;
  final Session session;
}

// ─── Extend — kontrak §7 ────────────────────────────────────────────

class ExtendResult {
  const ExtendResult({
    required this.session,
    required this.durationMinutes,
    required this.price,
    required this.previousEndAt,
    required this.newEndAt,
  });

  final Session session;
  final int durationMinutes;
  final int price;
  final DateTime previousEndAt;
  final DateTime newEndAt;
}

// ─── Checkout — kontrak §7 ──────────────────────────────────────────

class ReceiptLine {
  const ReceiptLine({
    required this.name,
    required this.qty,
    required this.subtotal,
  });

  final String name;
  final int qty;
  final int subtotal;
}

class Receipt {
  const Receipt({
    required this.number,
    required this.issuedAt,
    required this.billableDurationMinutes,
    required this.actualDurationMinutes,
    required this.lines,
    required this.totals,
    required this.payments,
    this.operator,
  });

  final String number;
  final DateTime issuedAt;

  /// Dua-duanya WAJIB ada — supaya operator bisa menjelaskan ke customer
  /// kenapa 63 menit ditagih 60 (DEC-009).
  final int billableDurationMinutes;
  final int actualDurationMinutes;

  final List<ReceiptLine> lines;
  final SessionTotals totals;
  final List<Payment> payments;
  final ActorRef? operator;
}

class CheckoutResult {
  const CheckoutResult({required this.session, required this.receipt});

  final Session session;
  final Receipt receipt;
}

// ─── Shift — kontrak §10 ────────────────────────────────────────────

class ShiftSummary {
  const ShiftSummary({
    required this.rental,
    required this.fnb,
    required this.cash,
    required this.qris,
    required this.total,
  });

  final int rental;
  final int fnb;
  final int cash;
  final int qris;
  final int total;

  factory ShiftSummary.fromJson(Map<String, dynamic> j) => ShiftSummary(
        rental: _int(j['rental']),
        fnb: _int(j['fnb']),
        cash: _int(j['cash']),
        qris: _int(j['qris']),
        total: _int(j['total']),
      );

  static const zero =
      ShiftSummary(rental: 0, fnb: 0, cash: 0, qris: 0, total: 0);
}

class Shift {
  const Shift({
    required this.id,
    required this.operator,
    required this.openedAt,
    required this.openingCash,
    required this.summary,
    this.closedAt,
    this.closingCash,
    this.note,
  });

  final String id;
  final ActorRef operator;
  final DateTime openedAt;
  final DateTime? closedAt;

  /// Kas awal yang dihitung operator saat membuka shift.
  final int openingCash;

  /// Kas akhir yang dihitung operator saat menutup. `null` kalau belum tutup.
  final int? closingCash;

  final ShiftSummary summary;
  final String? note;

  bool get isOpen => closedAt == null;

  /// Kas yang **seharusnya** ada di kotak: kas awal + penerimaan tunai.
  /// QRIS tidak dihitung karena tidak masuk kotak kas.
  int get expectedCash => openingCash + summary.cash;

  /// Selisih antara kas yang dihitung dan kas yang seharusnya.
  /// Positif = lebih, negatif = kurang. `null` kalau shift belum ditutup.
  ///
  /// Ini angka yang diaudit (PRD §24) — bukan sekadar informasi.
  int? get variance =>
      closingCash == null ? null : closingCash! - expectedCash;

  factory Shift.fromJson(Map<String, dynamic> j) => Shift(
        id: j['id'] as String,
        operator: ActorRef.fromJson(j['operator'] as Map<String, dynamic>),
        openedAt: _dt(j['opened_at']) ?? DateTime.now().toUtc(),
        closedAt: _dt(j['closed_at']),
        openingCash: _int(j['opening_cash']),
        closingCash: (j['closing_cash'] as num?)?.toInt(),
        summary: j['summary'] == null
            ? ShiftSummary.zero
            : ShiftSummary.fromJson(j['summary'] as Map<String, dynamic>),
        note: j['note'] as String?,
      );
}

// ─── F&B — kontrak §8 ───────────────────────────────────────────────

class FnbProduct {
  const FnbProduct({
    required this.id,
    required this.category,
    required this.name,
    required this.price,
    required this.isAvailable,
    this.stock,
  });

  final String id;
  final String category;
  final String name;
  final int price;
  final bool isAvailable;

  /// `null` = produk tidak dilacak stoknya.
  final int? stock;

  factory FnbProduct.fromJson(Map<String, dynamic> j) => FnbProduct(
        id: j['id'] as String,
        category: j['category'] as String? ?? 'Lain-lain',
        name: j['name'] as String,
        price: _int(j['price']),
        isAvailable: j['is_available'] as bool? ?? true,
        stock: (j['stock'] as num?)?.toInt(),
      );
}

class FnbOrderItem {
  const FnbOrderItem({
    required this.productId,
    required this.name,
    required this.qty,
    required this.unitPrice,
    required this.subtotal,
  });

  final String productId;
  final String name;
  final int qty;
  final int unitPrice;
  final int subtotal;

  factory FnbOrderItem.fromJson(Map<String, dynamic> j) => FnbOrderItem(
        productId: j['product_id'] as String,
        name: j['name'] as String,
        qty: _int(j['qty']),
        unitPrice: _int(j['unit_price']),
        subtotal: _int(j['subtotal']),
      );
}

class FnbOrder {
  const FnbOrder({
    required this.id,
    required this.code,
    required this.status,
    required this.sessionId,
    required this.stationCode,
    required this.items,
    required this.total,
    required this.source,
    required this.createdAt,
    this.note,
  });

  final String id;
  final String code;
  final FnbOrderStatus status;
  final String sessionId;
  final String stationCode;
  final List<FnbOrderItem> items;
  final int total;

  /// `OPERATOR` | `CUSTOMER` — customer baru ada di Tahap 3C.
  final String source;
  final DateTime createdAt;
  final String? note;

  factory FnbOrder.fromJson(Map<String, dynamic> j) => FnbOrder(
        id: j['id'] as String,
        code: j['code'] as String,
        status: FnbOrderStatus.parse(j['status'] as String?),
        sessionId: j['session_id'] as String,
        stationCode: j['station_code'] as String,
        items: ((j['items'] as List?) ?? const [])
            .map((e) => FnbOrderItem.fromJson(e as Map<String, dynamic>))
            .toList(growable: false),
        total: _int(j['total']),
        source: j['source'] as String? ?? 'OPERATOR',
        createdAt: _dt(j['created_at']) ?? DateTime.now().toUtc(),
        note: j['note'] as String?,
      );
}

class FnbOrderResult {
  const FnbOrderResult({required this.order, required this.session});

  final FnbOrder order;
  final Session session;
}
