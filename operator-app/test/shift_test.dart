import 'package:flutter_test/flutter_test.dart';
import 'package:operator_app/core/time/server_time.dart';
import 'package:operator_app/data/fake/fake_billing_repository.dart';
import 'package:operator_app/domain/errors/api_error.dart';
import 'package:operator_app/domain/models/enums.dart';
import 'package:operator_app/ui/shift/shift_controller.dart';

/// Test shift — PRD §18 "Start/close shift, handover" dan kontrak §10.
///
/// Inti yang diuji: **pertanggungjawaban kas**. Kas seharusnya, kas
/// dihitung, dan selisihnya — karena selisih itu yang diaudit (PRD §24).

/// Selesaikan semua sesi yang masih memakai station, supaya shift bisa
/// ditutup. Mencerminkan prosedur nyata: checkout dulu, baru tutup shift.
Future<void> _closeAllSessions(FakeBillingRepository repo) async {
  final sessions = await repo.fetchSessions();
  var n = 0;
  for (final s in sessions) {
    if (!s.status.occupiesStation) continue;
    n++;
    if (s.status == SessionStatus.pendingPayment) {
      await repo.cancelSession(
        sessionId: s.id,
        idempotencyKey: 'cleanup-cancel-$n',
      );
      continue;
    }
    final fresh = await repo.fetchSession(s.id);
    await repo.checkout(
      sessionId: s.id,
      payments: fresh.totals.balanceDue > 0
          ? [
              (
                method: PaymentMethod.cash,
                amount: fresh.totals.balanceDue,
                reference: null,
              ),
            ]
          : const [],
      idempotencyKey: 'cleanup-checkout-$n',
    );
  }
}

void main() {
  late FakeBillingRepository repo;
  late ShiftController ctrl;

  setUp(() async {
    ServerTime.instance.resetForTest();
    ServerTime.instance.sync(DateTime.now().toUtc());
    repo = FakeBillingRepository();
    ctrl = ShiftController(repo);
    await ctrl.load();
  });

  group('Shift berjalan', () {
    test('seed membuka satu shift dengan kas awal', () {
      expect(ctrl.hasOpenShift, isTrue);
      expect(ctrl.current!.openingCash, 200000);
      expect(ctrl.current!.isOpen, isTrue);
      expect(ctrl.current!.closedAt, isNull);
      expect(ctrl.current!.variance, isNull,
          reason: 'selisih baru ada setelah shift ditutup');
    });

    test('kas seharusnya = kas awal + penerimaan tunai, tanpa QRIS', () {
      final s = ctrl.current!;
      expect(s.expectedCash, s.openingCash + s.summary.cash);
      expect(
        s.summary.total,
        s.summary.cash + s.summary.qris,
        reason: 'total uang masuk = tunai + QRIS',
      );
    });

    test('ringkasan terisi dari pembayaran seed, bukan nol', () {
      final s = ctrl.current!.summary;
      expect(s.cash, greaterThan(0),
          reason: 'seed punya rental prepaid dan F&B yang sudah dibayar');
      expect(s.rental, greaterThan(0));
      expect(s.fnb, greaterThan(0));
    });

    test('tidak bisa membuka shift kedua', () {
      expect(
        () => ctrl.open(openingCash: 100000),
        throwsA(isA<ApiError>()),
      );
    });
  });

  group('Menutup shift', () {
    test('ditolak kalau masih ada sesi berjalan', () {
      // Seed punya tiga sesi yang masih memakai station.
      expect(
        () => ctrl.close(closingCash: 500000),
        throwsA(isA<ApiError>().having(
          (e) => e.code,
          'code',
          ApiErrorCode.sessionStatusInvalid,
        )),
      );
    });

    test('kas pas -> selisih nol', () async {
      await _closeAllSessions(repo);
      await ctrl.refresh();

      final expected = ctrl.current!.expectedCash;
      final closed = await ctrl.close(closingCash: expected);

      expect(closed.isOpen, isFalse);
      expect(closed.closedAt, isNotNull);
      expect(closed.variance, 0);
      expect(ctrl.hasOpenShift, isFalse);
      expect(ctrl.history.first.id, closed.id);
    });

    test('kas kurang -> selisih negatif', () async {
      await _closeAllSessions(repo);
      await ctrl.refresh();

      final expected = ctrl.current!.expectedCash;
      final closed = await ctrl.close(closingCash: expected - 50000);

      expect(closed.variance, -50000);
    });

    test('kas lebih -> selisih positif', () async {
      await _closeAllSessions(repo);
      await ctrl.refresh();

      final expected = ctrl.current!.expectedCash;
      final closed = await ctrl.close(closingCash: expected + 25000);

      expect(closed.variance, 25000);
    });

    test('catatan ikut tersimpan', () async {
      await _closeAllSessions(repo);
      await ctrl.refresh();

      final closed = await ctrl.close(
        closingCash: ctrl.current!.expectedCash,
        note: 'Selisih karena uang kecil kurang',
      );

      expect(closed.note, 'Selisih karena uang kecil kurang');
    });

    test('kas akhir negatif ditolak', () async {
      await _closeAllSessions(repo);
      await ctrl.refresh();

      expect(
        () => ctrl.close(closingCash: -1),
        throwsA(isA<ApiError>().having(
          (e) => e.code,
          'code',
          ApiErrorCode.validationFailed,
        )),
      );
    });
  });

  group('Serah terima', () {
    test('kas akhir jadi kas awal shift berikutnya', () async {
      await _closeAllSessions(repo);
      await ctrl.refresh();

      final closed = await ctrl.close(closingCash: 777000);
      expect(ctrl.hasOpenShift, isFalse);

      // Serah terima: operator berikutnya membuka dengan kas yang sama.
      final next = await ctrl.open(openingCash: closed.closingCash!);

      expect(next.openingCash, 777000);
      expect(ctrl.hasOpenShift, isTrue);
    });

    test('shift baru mulai dari ringkasan nol', () async {
      await _closeAllSessions(repo);
      await ctrl.refresh();
      await ctrl.close(closingCash: 100000);

      final next = await ctrl.open(openingCash: 100000);

      expect(next.summary.cash, 0,
          reason: 'pembayaran shift sebelumnya tidak boleh terhitung lagi');
      expect(next.summary.total, 0);
      expect(next.expectedCash, 100000);
    });
  });

  group('Idempotency', () {
    test('buka shift dengan key sama tidak menghasilkan dua shift', () async {
      await _closeAllSessions(repo);
      await ctrl.refresh();
      await ctrl.close(closingCash: 0);

      const key = 'open-intent';
      final first = await ctrl.open(openingCash: 50000, idempotencyKey: key);
      final second = await ctrl.open(openingCash: 50000, idempotencyKey: key);

      expect(second.id, first.id);
    });

    test('tutup shift dengan key sama hanya tercatat sekali', () async {
      await _closeAllSessions(repo);
      await ctrl.refresh();

      const key = 'close-intent';
      final shift = ctrl.current!;
      final first = await repo.closeShift(
        shiftId: shift.id,
        closingCash: 123000,
        idempotencyKey: key,
      );
      final second = await repo.closeShift(
        shiftId: shift.id,
        closingCash: 123000,
        idempotencyKey: key,
      );

      expect(second.id, first.id);
      final history = await repo.fetchShiftHistory();
      expect(history.where((s) => s.id == first.id).length, 1);
    });
  });

  group('Pembayaran baru masuk ke ringkasan shift', () {
    test('pembayaran saat shift berjalan menambah kas seharusnya', () async {
      final before = ctrl.current!.expectedCash;

      // Bayar sisa tagihan satu sesi aktif.
      final sessions = await repo.fetchSessions(
        statuses: {SessionStatus.active, SessionStatus.warning},
      );
      final target = sessions.firstWhere((s) => s.totals.balanceDue > 0);
      final amount = target.totals.balanceDue;

      await repo.addPayment(
        sessionId: target.id,
        method: PaymentMethod.cash,
        amount: amount,
        idempotencyKey: 'shift-pay',
      );
      await ctrl.refresh();

      expect(ctrl.current!.expectedCash, before + amount);
    });

    test('pembayaran QRIS tidak menambah kas seharusnya', () async {
      final before = ctrl.current!.expectedCash;

      final sessions = await repo.fetchSessions(
        statuses: {SessionStatus.active, SessionStatus.warning},
      );
      final target = sessions.firstWhere((s) => s.totals.balanceDue > 0);
      final amount = target.totals.balanceDue;

      await repo.addPayment(
        sessionId: target.id,
        method: PaymentMethod.qrisStatic,
        amount: amount,
        reference: 'QR-123',
        idempotencyKey: 'shift-pay-qris',
      );
      await ctrl.refresh();

      expect(
        ctrl.current!.expectedCash,
        before,
        reason: 'QRIS tidak masuk kotak kas, jadi tidak boleh '
            'mengubah kas seharusnya',
      );
      expect(ctrl.current!.summary.qris, greaterThanOrEqualTo(amount));
    });
  });
}
