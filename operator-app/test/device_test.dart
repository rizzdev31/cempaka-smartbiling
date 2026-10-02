import 'package:flutter_test/flutter_test.dart';
import 'package:operator_app/core/time/server_time.dart';
import 'package:operator_app/data/fake/fake_billing_repository.dart';
import 'package:operator_app/domain/models/enums.dart';
import 'package:operator_app/domain/models/models.dart';
import 'package:operator_app/ui/dashboard/dashboard_controller.dart';

/// Test status device — PRD §18 "Online/offline/last seen" dan kontrak §9.

void main() {
  late FakeBillingRepository repo;

  setUp(() {
    ServerTime.instance.resetForTest();
    ServerTime.instance.sync(DateTime.now().toUtc());
    repo = FakeBillingRepository();
  });

  group('Daftar device', () {
    test('setiap station punya device, plus satu cadangan tanpa station',
        () async {
      final data = await repo.fetchDevices();
      final stations = await repo.fetchStations();

      expect(
        data.devices.where((d) => d.isMapped).length,
        stations.where((s) => s.device != null).length,
      );
      expect(
        data.devices.where((d) => !d.isMapped),
        isNotEmpty,
        reason: 'device yang pemetaannya dicabut tetap harus terlihat '
            '(PRD §10)',
      );
    });

    test('offline diurutkan lebih dulu', () async {
      final data = await repo.fetchDevices();
      var seenOnline = false;
      for (final d in data.devices) {
        if (d.isOnline) {
          seenOnline = true;
        } else {
          expect(
            seenOnline,
            isFalse,
            reason: 'tidak boleh ada device offline setelah yang online — '
                'yang menuntut perhatian harus di atas',
          );
        }
      }
    });

    test('ambang offline dikirim bersama daftar', () async {
      final data = await repo.fetchDevices();
      expect(data.offlineThreshold, FakeBillingRepository.offlineThreshold);
      expect(data.offlineThreshold.inSeconds, greaterThan(0));
    });
  });

  group('Status ditentukan dari last_seen terhadap ambang', () {
    test('device yang baru mengirim kabar -> online', () async {
      final data = await repo.fetchDevices();
      final now = ServerTime.instance.now;

      for (final d in data.devices.where((d) => d.isOnline)) {
        expect(d.lastSeenAt, isNotNull);
        expect(
          now.difference(d.lastSeenAt!),
          lessThanOrEqualTo(data.offlineThreshold),
        );
      }
    });

    test('device yang lama tidak mengirim kabar -> offline', () async {
      final data = await repo.fetchDevices();
      final now = ServerTime.instance.now;

      final offline = data.devices.where((d) => !d.isOnline).toList();
      expect(offline, isNotEmpty);

      for (final d in offline) {
        expect(
          d.lastSeenAt == null ||
              now.difference(d.lastSeenAt!) > data.offlineThreshold,
          isTrue,
        );
      }
    });

    test('waktu berjalan membuat device jadi offline', () async {
      final before = await repo.fetchDevices();
      final onlineBefore = before.onlineCount;
      expect(onlineBefore, greaterThan(0));

      // Majukan jam server melewati ambang offline.
      repo.advanceClock(FakeBillingRepository.offlineThreshold +
          const Duration(minutes: 1));

      final after = await repo.fetchDevices();
      expect(
        after.onlineCount,
        0,
        reason: 'semua device jadi offline karena tidak ada heartbeat baru',
      );
      expect(after.offlineCount, greaterThan(before.offlineCount));
    });
  });

  group('Hitungan cocok dengan badge dashboard', () {
    test('offlineCount hanya menghitung device yang dipetakan', () async {
      final data = await repo.fetchDevices();

      final mappedOffline =
          data.devices.where((d) => d.isMapped && !d.isOnline).length;
      expect(data.offlineCount, mappedOffline);

      // Device cadangan tanpa station tidak boleh masuk offlineCount.
      expect(
        data.devices.where((d) => !d.isMapped && !d.isOnline),
        isNotEmpty,
        reason: 'seed punya cadangan offline tanpa station',
      );
      expect(data.unmappedCount, greaterThan(0));
    });

    test('badge dashboard = offlineCount di layar device', () async {
      final dashboard = DashboardController(repo);
      await dashboard.load();
      final data = await repo.fetchDevices();

      expect(
        dashboard.offlineDeviceCount,
        data.offlineCount,
        reason: 'kalau badge dan layar menunjukkan angka berbeda, '
            'operator berhenti mempercayai keduanya',
      );
    });

    test('onlineCount + offlineCount = jumlah station ber-device', () async {
      final data = await repo.fetchDevices();
      final stations = await repo.fetchStations();

      expect(
        data.onlineCount + data.offlineCount,
        stations.where((s) => s.device != null).length,
      );
    });
  });

  group('Informasi perangkat', () {
    test('label perangkat terbentuk dari model + versi OS', () async {
      final data = await repo.fetchDevices();
      final d = data.devices.firstWhere((d) => d.model != null);
      expect(d.hardwareLabel, contains(d.model!));
      expect(d.hardwareLabel, contains('Android'));
    });

    test('device tanpa model tetap punya label yang bisa dibaca', () {
      const d = Device(
        id: 'x',
        status: DeviceStatus.offline,
        lastSeenAt: null,
      );
      expect(d.hardwareLabel, isNotEmpty);
      expect(d.hardwareLabel, 'Perangkat belum teridentifikasi');
    });
  });
}
