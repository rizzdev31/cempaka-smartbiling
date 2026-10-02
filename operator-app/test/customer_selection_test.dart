import 'package:flutter_test/flutter_test.dart';
import 'package:operator_app/core/time/server_time.dart';
import 'package:operator_app/data/fake/fake_billing_repository.dart';
import 'package:operator_app/domain/errors/api_error.dart';
import 'package:operator_app/domain/models/enums.dart';
import 'package:operator_app/ui/customer/customer_picker.dart';

/// Test pemilihan customer di Start Session — PRD §18 dan DEC-008
/// (satu customer per sesi).

void main() {
  late FakeBillingRepository repo;

  setUp(() {
    ServerTime.instance.resetForTest();
    ServerTime.instance.sync(DateTime.now().toUtc());
    repo = FakeBillingRepository();
  });

  group('Pencarian customer', () {
    test('kueri kosong mengembalikan semua', () async {
      final all = await repo.searchCustomers('');
      expect(all.length, greaterThan(3));
    });

    test('cocok dengan nama, tidak peka huruf besar-kecil', () async {
      final byLower = await repo.searchCustomers('budi');
      final byUpper = await repo.searchCustomers('BUDI');

      expect(byLower, isNotEmpty);
      expect(byLower.map((c) => c.id), byUpper.map((c) => c.id));
      expect(byLower.first.name.toLowerCase(), contains('budi'));
    });

    test('cocok dengan nomor telepon', () async {
      final all = await repo.searchCustomers('');
      final target = all.firstWhere((c) => c.phone != null);
      final found = await repo.searchCustomers(target.phone!.substring(3));

      expect(found.map((c) => c.id), contains(target.id));
    });

    test('kueri tanpa hasil mengembalikan daftar kosong, bukan error',
        () async {
      final none = await repo.searchCustomers('zzzzz-tidak-ada');
      expect(none, isEmpty);
    });

    test('spasi di sekitar kueri diabaikan', () async {
      final a = await repo.searchCustomers('budi');
      final b = await repo.searchCustomers('   budi   ');
      expect(b.map((c) => c.id), a.map((c) => c.id));
    });
  });

  group('Membership', () {
    test('ada member aktif dan ada yang kedaluwarsa di seed', () async {
      final all = await repo.searchCustomers('');
      final withMembership =
          all.where((c) => c.membership != null).toList();

      expect(withMembership.any((c) => c.membership!.isActive), isTrue);
      expect(
        withMembership.any((c) => !c.membership!.isActive),
        isTrue,
        reason: 'membership kedaluwarsa harus tetap muncul di pencarian, '
            'supaya operator tahu orangnya pernah member tapi tidak '
            'berhak harga member sekarang',
      );
    });
  });

  group('CustomerChoice — pemetaan ke field kontrak', () {
    test('member mengirim customer_id, bukan customer_name', () async {
      final all = await repo.searchCustomers('');
      final member = all.first;
      final choice = CustomerChoice.member(member);

      expect(choice.isMember, isTrue);
      expect(choice.customerId, member.id);
      expect(choice.customerName, isNull,
          reason: 'kontrak §7: customer_id dan customer_name saling eksklusif');
      expect(choice.label, member.name);
    });

    test('walk-in bernama mengirim customer_name, bukan customer_id', () {
      const choice = CustomerChoice.walkIn('Pak Joko');

      expect(choice.isMember, isFalse);
      expect(choice.customerId, isNull);
      expect(choice.customerName, 'Pak Joko');
      expect(choice.label, 'Pak Joko');
    });

    test('walk-in tanpa nama tetap berlabel Walk-in', () {
      const choice = CustomerChoice.walkIn();
      expect(choice.customerId, isNull);
      expect(choice.customerName, isNull);
      expect(choice.label, 'Walk-in');
    });

    test('walk-in dengan nama hanya spasi diperlakukan tanpa nama', () {
      const choice = CustomerChoice.walkIn('   ');
      expect(choice.customerName, isEmpty);
      expect(choice.label, 'Walk-in');
    });
  });

  group('Sesi dibuat dengan customer terpilih', () {
    Future<String> freeStationId() async {
      final stations = await repo.fetchStations();
      return stations
          .firstWhere((s) =>
              s.session == null && s.status == StationMasterStatus.active)
          .id;
    }

    test('member tercatat di sesi', () async {
      final all = await repo.searchCustomers('');
      final member = all.first;
      final packages = await repo.fetchPackages();

      final session = await repo.createSession(
        stationId: await freeStationId(),
        packageId: packages.first.id,
        mode: SessionMode.postpaid,
        customerId: member.id,
        idempotencyKey: 'cs-member',
      );

      expect(session.customer, isNotNull);
      expect(session.customer!.id, member.id);
      expect(session.customerName, isNull);
      expect(session.customerLabel, member.name);
    });

    test('walk-in bernama tercatat tanpa customer_id', () async {
      final packages = await repo.fetchPackages();

      final session = await repo.createSession(
        stationId: await freeStationId(),
        packageId: packages.first.id,
        mode: SessionMode.postpaid,
        customerName: 'Pak Joko',
        idempotencyKey: 'cs-walkin',
      );

      expect(session.customer, isNull);
      expect(session.customerName, 'Pak Joko');
      expect(session.customerLabel, 'Pak Joko');
    });

    test('tanpa customer apa pun -> default Walk-in', () async {
      final packages = await repo.fetchPackages();

      final session = await repo.createSession(
        stationId: await freeStationId(),
        packageId: packages.first.id,
        mode: SessionMode.postpaid,
        idempotencyKey: 'cs-none',
      );

      expect(session.customer, isNull);
      expect(session.customerName, 'Walk-in');
    });

    test('customer_id yang tidak ada ditolak dengan ApiError, bukan crash',
        () async {
      final packages = await repo.fetchPackages();
      final stationId = await freeStationId();

      expect(
        () => repo.createSession(
          stationId: stationId,
          packageId: packages.first.id,
          mode: SessionMode.postpaid,
          customerId: 'tidak-ada',
          idempotencyKey: 'cs-invalid',
        ),
        throwsA(isA<ApiError>().having(
          (e) => e.code,
          'code',
          ApiErrorCode.notFound,
        )),
        reason: 'sebelumnya firstWhere tanpa pengaman melempar StateError '
            'mentah, sehingga UI menampilkan "kesalahan tidak terduga"',
      );
    });
  });
}
