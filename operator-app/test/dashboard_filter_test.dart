import 'package:flutter_test/flutter_test.dart';
import 'package:operator_app/core/time/server_time.dart';
import 'package:operator_app/data/fake/fake_billing_repository.dart';
import 'package:operator_app/domain/models/enums.dart';
import 'package:operator_app/ui/dashboard/dashboard_controller.dart';
import 'package:operator_app/ui/widgets/station_card.dart';

/// Test filter & pencarian station di dashboard.
///
/// Filter memakai status yang **diturunkan dari `end_at`**, sama seperti
/// kartu. Kalau memakai snapshot server, filter "Hampir Habis" akan luput
/// pada sesi yang baru melewati ambang 10 menit tapi scheduler server
/// belum menandainya.

void main() {
  late FakeBillingRepository repo;
  late DashboardController ctrl;

  setUp(() async {
    ServerTime.instance.resetForTest();
    ServerTime.instance.sync(DateTime.now().toUtc());
    repo = FakeBillingRepository();
    ctrl = DashboardController(repo);
    await ctrl.load();
  });

  group('Filter', () {
    test('default menampilkan semua station', () {
      expect(ctrl.filter, StationFilter.all);
      expect(ctrl.visibleStations.length, ctrl.stations.length);
      expect(ctrl.countFor(StationFilter.all), ctrl.stations.length);
    });

    test('jumlah tiap filter menjumlah tidak lebih dari total', () {
      final sum = StationFilter.values
          .where((f) => f != StationFilter.all)
          .fold(0, (a, f) => a + ctrl.countFor(f));
      expect(sum, lessThanOrEqualTo(ctrl.stations.length));
    });

    test('filter Tersedia hanya station tanpa sesi', () {
      ctrl.setFilter(StationFilter.available);
      expect(ctrl.visibleStations, isNotEmpty);
      for (final s in ctrl.visibleStations) {
        expect(s.session, isNull);
        expect(s.status, StationMasterStatus.active);
      }
    });

    test('filter Bermain hanya sesi yang masih jauh dari habis', () {
      ctrl.setFilter(StationFilter.playing);
      for (final s in ctrl.visibleStations) {
        expect(deriveStationViewStatus(s), StationViewStatus.active);
      }
    });

    test('filter Menunggu Bayar menemukan sesi PENDING_PAYMENT', () {
      ctrl.setFilter(StationFilter.pending);
      expect(ctrl.visibleStations, isNotEmpty,
          reason: 'seed punya satu sesi menunggu bayar di ST05');
      for (final s in ctrl.visibleStations) {
        expect(
          s.session?.status,
          anyOf(SessionStatus.pendingPayment, SessionStatus.checkout),
        );
      }
    });

    test('station maintenance tidak masuk filter Tersedia', () {
      ctrl.setFilter(StationFilter.available);
      final hasMaintenance = ctrl.visibleStations
          .any((s) => s.status == StationMasterStatus.maintenance);
      expect(hasMaintenance, isFalse,
          reason: 'station maintenance tidak bisa dipakai, '
              'jadi tidak boleh tampil sebagai tersedia');
    });

    test('filter memakai status turunan dari end_at, bukan snapshot server',
        () async {
      // Majukan jam server sampai semua sesi aktif masuk ambang warning.
      ctrl.setFilter(StationFilter.warning);
      final before = ctrl.visibleStations.length;

      repo.advanceClock(const Duration(minutes: 55));
      await ctrl.refresh();

      final after = ctrl.visibleStations.length;
      expect(after, greaterThan(before),
          reason: 'sesi yang mendekati/melewati habis harus masuk filter '
              'walau scheduler server belum menandainya');
    });
  });

  group('Pencarian', () {
    test('cocok dengan kode station', () {
      ctrl.setQuery('ST03');
      expect(ctrl.visibleStations.length, 1);
      expect(ctrl.visibleStations.first.code, 'ST03');
    });

    test('tidak peka huruf besar-kecil', () {
      ctrl.setQuery('st01');
      expect(ctrl.visibleStations.first.code, 'ST01');
    });

    test('cocok dengan tipe konsol', () {
      ctrl.setQuery('PS4');
      expect(ctrl.visibleStations, isNotEmpty);
      for (final s in ctrl.visibleStations) {
        expect(s.consoleType!.toUpperCase(), contains('PS4'));
      }
    });

    test('cocok dengan nama customer pada sesi berjalan', () {
      final withCustomer = ctrl.stations.firstWhere(
        (s) => (s.session?.customerLabel ?? '').isNotEmpty &&
            s.session!.customerLabel != 'Walk-in',
      );
      final name = withCustomer.session!.customerLabel!;

      ctrl.setQuery(name.split(' ').first);
      expect(
        ctrl.visibleStations.map((s) => s.code),
        contains(withCustomer.code),
      );
    });

    test('kueri tanpa hasil mengembalikan daftar kosong', () {
      ctrl.setQuery('zzzz-tidak-ada');
      expect(ctrl.visibleStations, isEmpty);
    });

    test('spasi di sekitar kueri diabaikan', () {
      ctrl.setQuery('  ST02  ');
      expect(ctrl.visibleStations.length, 1);
      expect(ctrl.visibleStations.first.code, 'ST02');
    });

    test('pencarian dan filter bekerja bersamaan', () {
      ctrl.setFilter(StationFilter.available);
      ctrl.setQuery('ST02');

      for (final s in ctrl.visibleStations) {
        expect(s.code, 'ST02');
        expect(s.session, isNull);
      }
    });
  });

  group('Tarif acuan', () {
    test('memakai tarif per jam termurah dari paket aktif', () {
      final cheapest = ctrl.packages
          .map((p) => p.hourlyRate)
          .reduce((a, b) => a < b ? a : b);
      expect(ctrl.cheapestHourlyRate, cheapest);
    });
  });

  group('Tipe konsol', () {
    test('setiap station punya label konsol di seed', () {
      for (final s in ctrl.stations) {
        expect(s.consoleType, isNotNull);
        expect(s.consoleType, isNotEmpty);
      }
    });

    test('label konsol tetap ada setelah sesi dibuat', () async {
      final free = ctrl.stations.firstWhere(
        (s) => s.session == null && s.status == StationMasterStatus.active,
      );
      final before = free.consoleType;

      await ctrl.startSession(
        stationId: free.id,
        packageId: ctrl.packages.first.id,
        mode: SessionMode.postpaid,
      );

      final after = ctrl.stations.firstWhere((s) => s.id == free.id);
      expect(after.consoleType, before,
          reason: 'master data tidak boleh hilang saat sesi menempel');
    });
  });
}
