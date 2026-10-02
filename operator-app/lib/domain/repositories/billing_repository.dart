import '../models/enums.dart';
import '../models/models.dart';

/// Kontrak data untuk aplikasi operator.
///
/// Dua implementasi:
/// - `FakeBillingRepository` — in-memory, untuk membangun UI sebelum Laravel ada
/// - `ApiBillingRepository`  — HTTP, menggantikan fake di vertical slice pertama
///
/// DEC-012 syarat 2: fake WAJIB diganti pada vertical slice pertama.
/// Fake tidak bisa membuktikan kontraknya lengkap — hanya Laravel asli yang
/// mengungkap field yang kurang.
///
/// Setiap method yang membuat/mengubah data menerima [idempotencyKey].
/// Key dibuat sekali per NIAT aksi dan dipakai ulang saat retry — bukan key
/// baru setiap percobaan (kontrak §3).
abstract class BillingRepository {
  // ── Master data ───────────────────────────────────────────────────

  Future<List<Station>> fetchStations();

  Future<List<Package>> fetchPackages();

  Future<List<Customer>> searchCustomers(String query);

  // ── Session ───────────────────────────────────────────────────────

  Future<Session> fetchSession(String sessionId);

  Future<List<Session>> fetchSessions({Set<SessionStatus>? statuses});

  Future<Session> createSession({
    required String stationId,
    required String packageId,
    required SessionMode mode,
    required String idempotencyKey,
    String? customerId,
    String? customerName,
  });

  Future<PaymentResult> addPayment({
    required String sessionId,
    required PaymentMethod method,
    required int amount,
    required String idempotencyKey,
    String? reference,
    String? note,
  });

  /// Kelipatan 30 menit saja (DEC-007). Server menolak nilai lain dengan
  /// `EXTEND_DURATION_INVALID`, dan menolak di luar grace 10 menit dengan
  /// `EXTEND_GRACE_EXPIRED`.
  Future<ExtendResult> extendSession({
    required String sessionId,
    required int durationMinutes,
    required String idempotencyKey,
  });

  /// Atomic. `session_id` tidak berubah, `end_at` dan items tetap (PRD §15).
  Future<Session> swapStation({
    required String sessionId,
    required String targetStationId,
    required String idempotencyKey,
    String? reason,
  });

  Future<CheckoutResult> checkout({
    required String sessionId,
    required List<({PaymentMethod method, int amount, String? reference})> payments,
    required String idempotencyKey,
  });

  Future<Session> cancelSession({
    required String sessionId,
    required String idempotencyKey,
    String? reason,
  });

  // ── F&B ───────────────────────────────────────────────────────────

  Future<List<FnbProduct>> fetchFnbProducts();

  Future<FnbOrderResult> createFnbOrder({
    required String sessionId,
    required List<({String productId, int qty})> items,
    required String idempotencyKey,
    String? note,
  });

  Future<List<FnbOrder>> fetchFnbOrders({Set<FnbOrderStatus>? statuses});

  Future<FnbOrder> updateFnbOrderStatus({
    required String orderId,
    required FnbOrderStatus status,
  });

  // ── Device — kontrak §9 ───────────────────────────────────────────

  /// Daftar TV Agent terdaftar. **Read-only** untuk operator —
  /// pendaftaran, pemetaan ulang, dan pencabutan token adalah wewenang
  /// Admin (PRD §19, Tahap 3B).
  Future<DeviceList> fetchDevices();

  // ── Shift — kontrak §10 ───────────────────────────────────────────

  /// Shift yang sedang berjalan, atau `null` kalau belum ada yang dibuka.
  Future<Shift?> fetchCurrentShift();

  Future<Shift> openShift({
    required int openingCash,
    required String idempotencyKey,
  });

  /// [closingCash] adalah kas yang **dihitung operator**, bukan yang
  /// dihitung sistem. Selisihnya yang diaudit.
  Future<Shift> closeShift({
    required String shiftId,
    required int closingCash,
    required String idempotencyKey,
    String? note,
  });

  /// Riwayat shift yang sudah ditutup, terbaru di atas.
  Future<List<Shift>> fetchShiftHistory({int limit = 20});
}
