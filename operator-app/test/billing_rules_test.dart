import 'package:flutter_test/flutter_test.dart';
import 'package:operator_app/core/time/server_time.dart';
import 'package:operator_app/data/fake/fake_billing_repository.dart';
import 'package:operator_app/domain/errors/api_error.dart';
import 'package:operator_app/domain/models/enums.dart';
import 'package:operator_app/domain/repositories/billing_repository.dart';

/// Test aturan billing yang sudah final: DEC-007 (extend) dan DEC-009
/// (rounding durasi).
///
/// Kenapa diuji di sisi client padahal server yang berwenang:
/// `FakeBillingRepository` adalah cerminan aturan server. Kalau cerminannya
/// salah, UI akan dibangun di atas perilaku yang salah dan baru ketahuan di
/// lokasi. Saat `ApiBillingRepository` masuk, test yang sama dijalankan
/// terhadap Laravel untuk membuktikan keduanya sepakat.
///
/// Memetakan ke acceptance test: T15, T16, T17.

void main() {
  setUp(() {
    ServerTime.instance.resetForTest();
    ServerTime.instance.sync(DateTime.now().toUtc());
  });

  group('DEC-009 — rounding durasi (Postpaid)', () {
    // sisa = m mod 30; sisa <= 5 -> ke bawah; sisa > 5 -> ke atas; min 30.
    const cases = <int, int>{
      1: 30, // minimum 30 menit
      29: 30,
      30: 30,
      31: 30, // sisa 1 -> ke bawah (toleransi 5 menit)
      35: 30, // sisa 5 -> ke bawah
      36: 60, // sisa 6 -> ke atas
      60: 60, // pas
      63: 60, // sisa 3 -> ke bawah  (contoh T17)
      65: 60, // sisa 5 -> ke bawah
      66: 90, // sisa 6 -> ke atas
      70: 90, // sisa 10 -> ke atas
      90: 90,
      95: 90, // sisa 5 -> ke bawah
      96: 120, // sisa 6 -> ke atas
    };

    for (final entry in cases.entries) {
      test('${entry.key} menit aktual -> ditagih ${entry.value} menit', () {
        expect(
          FakeBillingRepository.billableMinutes(entry.key),
          entry.value,
          reason: 'DEC-009: sisa <= 5 dibulatkan ke bawah, > 5 ke atas',
        );
      });
    }

    test('31 menit dibulatkan ke bawah jadi 30, bukan ke atas', () {
      // 31 mod 30 = 1, toleransi 5 menit -> 30.
      expect(FakeBillingRepository.billableMinutes(31), 30);
    });

    test('durasi nol atau negatif tetap kena minimum 30 menit', () {
      expect(FakeBillingRepository.billableMinutes(0), 30);
      expect(FakeBillingRepository.billableMinutes(-10), 30);
    });
  });

  group('DEC-007 — harga extend', () {
    test('tarif 20.000/jam: 30 menit = 10.000', () {
      expect(
        FakeBillingRepository.extendPrice(hourlyRate: 20000, minutes: 30),
        10000,
      );
    });

    test('tarif 20.000/jam: 90 menit = 30.000', () {
      expect(
        FakeBillingRepository.extendPrice(hourlyRate: 20000, minutes: 90),
        30000,
      );
    });

    test('tarif ganjil dibulatkan ke atas, tidak pernah ke bawah', () {
      // 19.000/jam, 30 menit = 9.500 tepat.
      expect(
        FakeBillingRepository.extendPrice(hourlyRate: 19000, minutes: 30),
        9500,
      );
      // 19.001/jam, 30 menit = 9500,5 -> 9501 (rental tidak dirugikan).
      expect(
        FakeBillingRepository.extendPrice(hourlyRate: 19001, minutes: 30),
        9501,
      );
    });
  });

  group('Extend — perilaku end-to-end (T15, T16)', () {
    late FakeBillingRepository repo;

    setUp(() => repo = FakeBillingRepository());

    Future<String> startPrepaidSession(BillingRepository r) async {
      final stations = await r.fetchStations();
      final free = stations.firstWhere(
        (s) => s.session == null && s.status == StationMasterStatus.active,
      );
      final packages = await r.fetchPackages();
      final oneHour = packages.firstWhere((p) => p.durationMinutes == 60);

      // PREPAID, bukan Postpaid.
      //
      // Postpaid tidak punya `end_at` (DEC-034), jadi tidak ada yang bisa
      // diperpanjang — memakainya di sini dulu membuat test lulus sambil
      // menguji perilaku yang tidak pernah terjadi.
      final session = await r.createSession(
        stationId: free.id,
        packageId: oneHour.id,
        mode: SessionMode.prepaid,
        idempotencyKey: 'test-create-1',
      );

      // Prepaid menunggu pembayaran sebelum timernya jalan (kontrak §7).
      await r.addPayment(
        sessionId: session.id,
        method: PaymentMethod.cash,
        amount: oneHour.price,
        idempotencyKey: 'test-pay-1',
      );
      return session.id;
    }

    test('extend menambah tepat 30 menit dari end_at lama', () async {
      final id = await startPrepaidSession(repo);
      final before = await repo.fetchSession(id);

      final result = await repo.extendSession(
        sessionId: id,
        durationMinutes: 30,
        idempotencyKey: 'test-extend-1',
      );

      expect(result.previousEndAt, before.endAt);
      expect(
        result.newEndAt,
        before.endAt!.add(const Duration(minutes: 30)),
        reason: 'DEC-007: end_at baru = end_at LAMA + durasi, '
            'bukan dihitung dari waktu approve',
      );
      expect(result.session.totals.extend, result.price);
    });

    test('durasi bukan kelipatan 30 ditolak', () async {
      final id = await startPrepaidSession(repo);

      expect(
        () => repo.extendSession(
          sessionId: id,
          durationMinutes: 45,
          idempotencyKey: 'test-extend-bad',
        ),
        throwsA(
          isA<ApiError>().having(
            (e) => e.code,
            'code',
            ApiErrorCode.extendDurationInvalid,
          ),
        ),
      );
    });

    test('T16 — extend ditolak setelah waktu habis (DEC-033)', () async {
      final id = await startPrepaidSession(repo);
      final session = await repo.fetchSession(id);

      // Satu menit setelah `end_at`. Dulu ini masih di dalam grace 10
      // menit; sejak DEC-033 grace dicabut — habis ya habis.
      repo.advanceClock(
        session.endAt!
            .add(const Duration(minutes: 1))
            .difference(ServerTime.instance.now),
      );

      expect(
        () => repo.extendSession(
          sessionId: id,
          durationMinutes: 30,
          idempotencyKey: 'test-extend-late',
        ),
        throwsA(
          isA<ApiError>().having(
            (e) => e.code,
            'code',
            ApiErrorCode.extendGraceExpired,
          ),
        ),
      );
    });

    test('T15 — extend dihitung dari end_at lama, bukan dari waktu approve',
        () async {
      final id = await startPrepaidSession(repo);
      final session = await repo.fetchSession(id);
      final originalEnd = session.endAt!;

      // 10 menit SEBELUM habis. Dulu skenario ini dijalankan 6 menit
      // SETELAH habis, yang sejak DEC-033 sudah ditolak — tapi yang
      // sebenarnya dilindungi test ini bukan grace-nya, melainkan bahwa
      // waktu yang sudah berlalu tidak digratiskan. Prinsip itu tetap.
      repo.advanceClock(
        originalEnd
            .subtract(const Duration(minutes: 10))
            .difference(ServerTime.instance.now),
      );

      final result = await repo.extendSession(
        sessionId: id,
        durationMinutes: 30,
        idempotencyKey: 'test-extend-grace',
      );

      expect(result.newEndAt, originalEnd.add(const Duration(minutes: 30)));

      // Sisa ke depan 40 menit: 10 sisa + 30 tambahan. Kalau `end_at` baru
      // dihitung dari waktu approve, angkanya jadi 30 — dan 10 menit yang
      // sudah dibayar hilang.
      final forward = result.newEndAt.difference(ServerTime.instance.now);
      expect(forward.inMinutes, closeTo(40, 1));
    });

    test('extend_deadline_at SAMA DENGAN end_at (DEC-033)', () async {
      // Field-nya sengaja tidak dihapus dari kontrak supaya client lama
      // tidak patah; yang berubah hanya nilainya.
      final id = await startPrepaidSession(repo);
      final session = await repo.fetchSession(id);

      expect(session.extendDeadlineAt, session.endAt);
      expect(session.extendable, isTrue);
    });

    test('Postpaid tidak bisa di-extend — tidak ada yang diperpanjang',
        () async {
      final stations = await repo.fetchStations();
      final free = stations.firstWhere(
        (s) => s.session == null && s.status == StationMasterStatus.active,
      );
      final packages = await repo.fetchPackages();

      final session = await repo.createSession(
        stationId: free.id,
        packageId: packages.first.id,
        mode: SessionMode.postpaid,
        idempotencyKey: 'test-create-postpaid',
      );

      expect(session.endAt, isNull, reason: 'DEC-034: tanpa batas waktu');
      expect(
        () => repo.extendSession(
          sessionId: session.id,
          durationMinutes: 30,
          idempotencyKey: 'test-extend-postpaid',
        ),
        throwsA(isA<ApiError>().having(
            (e) => e.code, 'code', ApiErrorCode.sessionStatusInvalid)),
      );
    });
  });

  group('Idempotency (T14)', () {
    test('key sama tidak membuat sesi ganda', () async {
      final repo = FakeBillingRepository();
      final stations = await repo.fetchStations();
      final free = stations.firstWhere(
        (s) => s.session == null && s.status == StationMasterStatus.active,
      );
      final packages = await repo.fetchPackages();

      const key = 'same-intent-key';
      final first = await repo.createSession(
        stationId: free.id,
        packageId: packages.first.id,
        mode: SessionMode.postpaid,
        idempotencyKey: key,
      );
      final second = await repo.createSession(
        stationId: free.id,
        packageId: packages.first.id,
        mode: SessionMode.postpaid,
        idempotencyKey: key,
      );

      expect(second.id, first.id, reason: 'replay harus kembalikan sesi sama');

      final all = await repo.fetchSessions();
      expect(
        all.where((s) => s.id == first.id).length,
        1,
        reason: 'tidak boleh ada sesi ganda',
      );
    });

    test('pembayaran dengan key sama hanya dihitung sekali', () async {
      final repo = FakeBillingRepository();
      final stations = await repo.fetchStations();
      final free = stations.firstWhere(
        (s) => s.session == null && s.status == StationMasterStatus.active,
      );
      final packages = await repo.fetchPackages();
      final pkg = packages.firstWhere((p) => p.durationMinutes == 60);

      final session = await repo.createSession(
        stationId: free.id,
        packageId: pkg.id,
        mode: SessionMode.prepaid,
        idempotencyKey: 'pay-create',
      );

      const key = 'pay-intent';
      await repo.addPayment(
        sessionId: session.id,
        method: PaymentMethod.cash,
        amount: pkg.price,
        idempotencyKey: key,
      );
      final second = await repo.addPayment(
        sessionId: session.id,
        method: PaymentMethod.cash,
        amount: pkg.price,
        idempotencyKey: key,
      );

      expect(second.session.totals.paid, pkg.price,
          reason: 'double-tap tidak boleh menggandakan pembayaran');
      expect(second.session.totals.balanceDue, 0);
    });
  });

  group('Station Swap (T08)', () {
    test('session_id dan end_at tidak berubah setelah swap', () async {
      final repo = FakeBillingRepository();
      final stations = await repo.fetchStations();
      final free = stations
          .where((s) =>
              s.session == null && s.status == StationMasterStatus.active)
          .toList();
      final packages = await repo.fetchPackages();

      final session = await repo.createSession(
        stationId: free[0].id,
        packageId: packages.first.id,
        mode: SessionMode.postpaid,
        idempotencyKey: 'swap-create',
      );

      final swapped = await repo.swapStation(
        sessionId: session.id,
        targetStationId: free[1].id,
        idempotencyKey: 'swap-intent',
      );

      expect(swapped.id, session.id, reason: 'session_id harus tetap sama');
      expect(swapped.endAt, session.endAt, reason: 'end_at tidak boleh hilang');
      expect(swapped.station.id, free[1].id);

      final after = await repo.fetchStations();
      expect(after.firstWhere((s) => s.id == free[0].id).session, isNull,
          reason: 'station lama harus kembali tersedia');
    });

    test('swap ke station yang sama ditolak', () async {
      final repo = FakeBillingRepository();
      final stations = await repo.fetchStations();
      final free = stations.firstWhere(
        (s) => s.session == null && s.status == StationMasterStatus.active,
      );
      final packages = await repo.fetchPackages();

      final session = await repo.createSession(
        stationId: free.id,
        packageId: packages.first.id,
        mode: SessionMode.postpaid,
        idempotencyKey: 'swap-same-create',
      );

      expect(
        () => repo.swapStation(
          sessionId: session.id,
          targetStationId: free.id,
          idempotencyKey: 'swap-same',
        ),
        throwsA(isA<ApiError>().having(
            (e) => e.code, 'code', ApiErrorCode.targetStationSame)),
      );
    });
  });

  group('Checkout (T13, T17)', () {
    test('Prepaid: rental tidak di-rounding, hanya item unpaid ditagih',
        () async {
      final repo = FakeBillingRepository();
      final stations = await repo.fetchStations();
      final free = stations.firstWhere(
        (s) => s.session == null && s.status == StationMasterStatus.active,
      );
      final packages = await repo.fetchPackages();
      final pkg = packages.firstWhere((p) => p.durationMinutes == 60);

      final session = await repo.createSession(
        stationId: free.id,
        packageId: pkg.id,
        mode: SessionMode.prepaid,
        idempotencyKey: 'co-create',
      );
      await repo.addPayment(
        sessionId: session.id,
        method: PaymentMethod.cash,
        amount: pkg.price,
        idempotencyKey: 'co-pay',
      );

      final products = await repo.fetchFnbProducts();
      final teh = products.firstWhere((p) => p.name == 'Teh Manis');
      final afterFnb = await repo.createFnbOrder(
        sessionId: session.id,
        items: [(productId: teh.id, qty: 2)],
        idempotencyKey: 'co-fnb',
      );

      // Rental sudah dibayar -> yang ditagih hanya F&B.
      expect(afterFnb.session.totals.balanceDue, teh.price * 2);

      final result = await repo.checkout(
        sessionId: session.id,
        payments: [
          (
            method: PaymentMethod.cash,
            amount: teh.price * 2,
            reference: null,
          ),
        ],
        idempotencyKey: 'co-checkout',
      );

      expect(result.session.status, SessionStatus.completed);
      expect(result.session.totals.balanceDue, 0);
      expect(result.receipt.billableDurationMinutes, 60,
          reason: 'Prepaid tidak di-rounding — pakai durasi paket');
      expect(result.receipt.totals.grandTotal, pkg.price + teh.price * 2);
    });

    test('pembayaran kurang dari tagihan ditolak', () async {
      final repo = FakeBillingRepository();
      final stations = await repo.fetchStations();
      final free = stations.firstWhere(
        (s) => s.session == null && s.status == StationMasterStatus.active,
      );
      final packages = await repo.fetchPackages();

      final session = await repo.createSession(
        stationId: free.id,
        packageId: packages.first.id,
        mode: SessionMode.postpaid,
        idempotencyKey: 'short-create',
      );

      expect(
        () => repo.checkout(
          sessionId: session.id,
          payments: [
            (method: PaymentMethod.cash, amount: 1000, reference: null),
          ],
          idempotencyKey: 'short-checkout',
        ),
        throwsA(isA<ApiError>().having(
            (e) => e.code, 'code', ApiErrorCode.checkoutInsufficientPayment)),
      );
    });
  });

  group('F&B ghost order (T05)', () {
    test('order ke sesi PENDING_PAYMENT ditolak', () async {
      final repo = FakeBillingRepository();
      final stations = await repo.fetchStations();
      final free = stations.firstWhere(
        (s) => s.session == null && s.status == StationMasterStatus.active,
      );
      final packages = await repo.fetchPackages();

      // Prepaid yang belum dibayar -> PENDING_PAYMENT.
      final session = await repo.createSession(
        stationId: free.id,
        packageId: packages.first.id,
        mode: SessionMode.prepaid,
        idempotencyKey: 'ghost-create',
      );
      expect(session.status, SessionStatus.pendingPayment);

      final products = await repo.fetchFnbProducts();

      expect(
        () => repo.createFnbOrder(
          sessionId: session.id,
          items: [(productId: products.first.id, qty: 1)],
          idempotencyKey: 'ghost-order',
        ),
        throwsA(isA<ApiError>().having(
            (e) => e.code, 'code', ApiErrorCode.sessionNotOrderable)),
      );
    });
  });

  group('QRIS wajib nomor referensi (T16 audit)', () {
    test('pembayaran QRIS tanpa referensi ditolak', () async {
      final repo = FakeBillingRepository();
      final stations = await repo.fetchStations();
      final free = stations.firstWhere(
        (s) => s.session == null && s.status == StationMasterStatus.active,
      );
      final packages = await repo.fetchPackages();

      final session = await repo.createSession(
        stationId: free.id,
        packageId: packages.first.id,
        mode: SessionMode.prepaid,
        idempotencyKey: 'qris-create',
      );

      expect(
        () => repo.addPayment(
          sessionId: session.id,
          method: PaymentMethod.qrisStatic,
          amount: packages.first.price,
          idempotencyKey: 'qris-pay',
        ),
        throwsA(isA<ApiError>().having(
            (e) => e.code, 'code', ApiErrorCode.paymentReferenceRequired)),
      );
    });
  });
}
