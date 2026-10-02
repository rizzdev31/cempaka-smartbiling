import 'package:flutter_test/flutter_test.dart';
import 'package:operator_app/core/time/server_time.dart';
import 'package:operator_app/data/fake/fake_billing_repository.dart';
import 'package:operator_app/domain/errors/api_error.dart';
import 'package:operator_app/domain/models/enums.dart';
import 'package:operator_app/ui/fnb/fnb_queue_controller.dart';

/// Test antrian F&B — PRD §18 "Incoming order, process, delivered/status"
/// dan kontrak §8 (transisi status).

void main() {
  late FakeBillingRepository repo;
  late FnbQueueController ctrl;

  setUp(() async {
    ServerTime.instance.resetForTest();
    ServerTime.instance.sync(DateTime.now().toUtc());
    repo = FakeBillingRepository();
    ctrl = FnbQueueController(repo);
    await ctrl.load();
  });

  group('Pengelompokan antrian', () {
    test('seed menghasilkan order di beberapa status', () {
      expect(ctrl.hasData, isTrue);
      expect(ctrl.ofStatus(FnbOrderStatus.pending), isNotEmpty);
      expect(ctrl.ofStatus(FnbOrderStatus.processing), isNotEmpty);
      expect(ctrl.ofStatus(FnbOrderStatus.ready), isNotEmpty);
      expect(ctrl.ofStatus(FnbOrderStatus.delivered), isNotEmpty);
    });

    test('aktif berisi pending + processing + ready, tanpa delivered', () {
      final statuses = ctrl.active.map((o) => o.status).toSet();
      expect(statuses, isNot(contains(FnbOrderStatus.delivered)));
      expect(statuses, isNot(contains(FnbOrderStatus.cancelled)));
      expect(
        statuses.every(FnbQueueController.activeStatuses.contains),
        isTrue,
      );
    });

    test('riwayat hanya delivered + cancelled, terbaru di atas', () {
      final done = ctrl.done;
      expect(done, isNotEmpty);
      expect(
        done.every((o) => FnbQueueController.doneStatuses.contains(o.status)),
        isTrue,
      );
      for (var i = 1; i < done.length; i++) {
        expect(
          done[i - 1].createdAt.isAfter(done[i].createdAt) ||
              done[i - 1].createdAt == done[i].createdAt,
          isTrue,
          reason: 'riwayat harus urut dari yang terbaru',
        );
      }
    });

    test('badge hanya menghitung pending + processing, bukan ready', () {
      final expected = ctrl.ofStatus(FnbOrderStatus.pending).length +
          ctrl.ofStatus(FnbOrderStatus.processing).length;
      expect(ctrl.actionableCount, expected);
      expect(
        ctrl.actionableCount,
        lessThan(ctrl.active.length),
        reason: 'ada order READY di seed, jadi badge harus lebih kecil '
            'daripada total aktif — yang siap tinggal diantar, bukan dikerjakan',
      );
    });
  });

  group('Transisi status (kontrak §8)', () {
    test('pending -> processing -> ready -> delivered', () async {
      final order = ctrl.ofStatus(FnbOrderStatus.pending).first;

      expect(await ctrl.advance(order), FnbOrderStatus.processing);

      final p = ctrl.ofStatus(FnbOrderStatus.processing)
          .firstWhere((o) => o.id == order.id);
      expect(await ctrl.advance(p), FnbOrderStatus.ready);

      final r = ctrl.ofStatus(FnbOrderStatus.ready)
          .firstWhere((o) => o.id == order.id);
      expect(await ctrl.advance(r), FnbOrderStatus.delivered);
    });

    test('order delivered tidak bisa dimajukan lagi', () async {
      final delivered = ctrl.ofStatus(FnbOrderStatus.delivered).first;

      expect(
        () => ctrl.advance(delivered),
        throwsA(isA<ApiError>().having(
          (e) => e.code,
          'code',
          ApiErrorCode.fnbStatusTransitionInvalid,
        )),
      );
    });

    test('order keluar dari antrian aktif setelah diantar', () async {
      final order = ctrl.ofStatus(FnbOrderStatus.ready).first;
      final activeBefore = ctrl.active.length;

      await ctrl.advance(order);

      expect(ctrl.active.length, activeBefore - 1);
      expect(ctrl.done.any((o) => o.id == order.id), isTrue);
    });
  });

  group('Pembatalan', () {
    test('pending boleh dibatalkan', () async {
      final order = ctrl.ofStatus(FnbOrderStatus.pending).first;
      expect(order.status.canCancel, isTrue);
      expect(await ctrl.cancel(order), FnbOrderStatus.cancelled);
    });

    test('processing boleh dibatalkan', () async {
      final order = ctrl.ofStatus(FnbOrderStatus.processing).first;
      expect(order.status.canCancel, isTrue);
      expect(await ctrl.cancel(order), FnbOrderStatus.cancelled);
    });

    test('ready TIDAK boleh dibatalkan — barangnya sudah dibuat', () async {
      final order = ctrl.ofStatus(FnbOrderStatus.ready).first;
      expect(order.status.canCancel, isFalse);

      expect(
        () => ctrl.cancel(order),
        throwsA(isA<ApiError>().having(
          (e) => e.code,
          'code',
          ApiErrorCode.fnbStatusTransitionInvalid,
        )),
      );
    });

    test('delivered TIDAK boleh dibatalkan', () async {
      final order = ctrl.ofStatus(FnbOrderStatus.delivered).first;
      expect(order.status.canCancel, isFalse);

      expect(
        () => ctrl.cancel(order),
        throwsA(isA<ApiError>()),
      );
    });
  });

  group('Order baru masuk antrian', () {
    test('order dari session detail langsung terlihat di antrian', () async {
      final before = ctrl.ofStatus(FnbOrderStatus.pending).length;

      // Pakai sesi aktif yang ada di seed.
      final sessions = await repo.fetchSessions(
        statuses: {SessionStatus.active, SessionStatus.warning},
      );
      final session = sessions.first;
      final products = await repo.fetchFnbProducts();
      final available = products.firstWhere((p) => (p.stock ?? 1) > 0);

      await repo.createFnbOrder(
        sessionId: session.id,
        items: [(productId: available.id, qty: 1)],
        idempotencyKey: 'queue-new-order',
      );
      await ctrl.refresh();

      expect(ctrl.ofStatus(FnbOrderStatus.pending).length, before + 1);
      expect(
        ctrl.ofStatus(FnbOrderStatus.pending).last.status,
        FnbOrderStatus.pending,
        reason: 'order baru selalu masuk sebagai PENDING',
      );
    });
  });

  group('Label tombol', () {
    test('setiap status aktif punya langkah berikutnya', () {
      expect(FnbOrderStatus.pending.next, FnbOrderStatus.processing);
      expect(FnbOrderStatus.processing.next, FnbOrderStatus.ready);
      expect(FnbOrderStatus.ready.next, FnbOrderStatus.delivered);
      expect(FnbOrderStatus.delivered.next, isNull);
      expect(FnbOrderStatus.cancelled.next, isNull);
    });
  });
}
