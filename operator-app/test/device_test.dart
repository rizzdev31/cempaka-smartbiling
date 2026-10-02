import 'package:flutter_test/flutter_test.dart';
import 'package:operator_app/core/time/server_time.dart';
import 'package:operator_app/data/fake/fake_billing_repository.dart';
import 'package:operator_app/domain/models/enums.dart';
import 'package:operator_app/domain/models/models.dart';
import 'package:operator_app/domain/models/tv_agent.dart';
import 'package:operator_app/ui/dashboard/dashboard_controller.dart';

/// Test seputar device.
///
/// ## Kenapa test ini berubah banyak
///
/// Versi sebelumnya menguji **daftar device karangan** dari fake repository:
/// enam perangkat dengan status online/offline yang dibuat-buat. Hasilnya dua
/// sumber kebenaran untuk hal yang sama — kartu station memakai device palsu,
/// layar Status TV memakai sambungan nyata — dan angkanya bisa berbeda.
///
/// Sekarang hanya ada satu: sambungan nyata di `TvSyncService` (diuji di
/// `tv_sync_test.dart`). Yang tersisa di sini adalah memastikan sumber palsunya
/// benar-benar **tidak lagi mengarang**, plus logika murni pada model.

void main() {
  late FakeBillingRepository repo;

  setUp(() {
    ServerTime.instance.resetForTest();
    ServerTime.instance.sync(DateTime.now().toUtc());
    repo = FakeBillingRepository();
  });

  group('Sumber data ditandai sebagai contoh', () {
    test('fake repository mengaku data contoh', () {
      expect(repo.isSample, isTrue,
          reason: 'UI memakai ini untuk menandai DATA CONTOH di header — '
              'tanpa penanda, angka contoh mudah dibaca sebagai angka asli');
    });

    test('dashboard meneruskan penanda itu ke UI', () async {
      final ctrl = DashboardController(repo);
      await ctrl.load();
      expect(ctrl.isSampleData, isTrue);
    });
  });

  group('Station tidak lagi membawa device karangan', () {
    test('semua station punya device null', () async {
      final stations = await repo.fetchStations();

      expect(stations, isNotEmpty);
      for (final s in stations) {
        expect(
          s.device,
          isNull,
          reason: 'tidak ada server yang melacak heartbeat TV di mode kontrol '
              'langsung, jadi mengarang status device hanya membuat kartu '
              'station dan layar Status TV saling bertentangan',
        );
      }
    });

    test('device tetap null setelah sesi dibuat', () async {
      final stations = await repo.fetchStations();
      final free = stations.firstWhere(
        (s) => s.session == null && s.status == StationMasterStatus.active,
      );
      final packages = await repo.fetchPackages();

      await repo.createSession(
        stationId: free.id,
        packageId: packages.first.id,
        mode: SessionMode.postpaid,
        idempotencyKey: 'dev-null-1',
      );

      final after = await repo.fetchStations();
      expect(after.firstWhere((s) => s.id == free.id).device, isNull);
    });
  });

  group('GET /devices kosong, dan itu jawaban yang benar', () {
    test('daftar device kosong selama belum ada Laravel', () async {
      final data = await repo.fetchDevices();

      expect(data.devices, isEmpty);
      expect(data.onlineCount, 0);
      expect(data.offlineCount, 0);
      expect(data.unmappedCount, 0);
    });

    test('ambang offline tetap dikirim walau daftarnya kosong', () async {
      final data = await repo.fetchDevices();

      // Client memakainya untuk menjelaskan ALASAN sebuah device dianggap
      // offline, jadi nilainya harus tetap masuk akal.
      expect(data.offlineThreshold.inSeconds, greaterThan(0));
    });
  });

  group('Model DeviceList — logika murni', () {
    Device device({
      required bool online,
      bool mapped = true,
      String uid = 'tv-1',
    }) =>
        Device(
          id: uid,
          deviceUid: uid,
          station: mapped
              ? const StationRef(id: 'sta-1', code: 'ST01', name: 'Station 1')
              : null,
          status: online ? DeviceStatus.online : DeviceStatus.offline,
          lastSeenAt: DateTime.now().toUtc(),
          model: 'TV A2 43',
          osVersion: 'Android 11',
        );

    test('hitungan online dan offline hanya device yang dipetakan', () {
      final list = DeviceList(
        devices: [
          device(online: true, uid: 'a'),
          device(online: false, uid: 'b'),
          // Cadangan tanpa station: tidak boleh masuk hitungan offline, karena
          // badge dashboard menghitung TV per station. Dua angka berbeda untuk
          // hal yang sama membuat operator berhenti mempercayai keduanya.
          device(online: false, mapped: false, uid: 'c'),
        ],
        offlineThreshold: const Duration(minutes: 2),
      );

      expect(list.onlineCount, 1);
      expect(list.offlineCount, 1);
      expect(list.unmappedCount, 1);
    });

    test('label perangkat terbentuk dari model dan versi OS', () {
      expect(device(online: true).hardwareLabel, 'TV A2 43 · Android 11');
    });

    test('device tanpa model tetap punya label yang bisa dibaca', () {
      const d = Device(
        id: 'x',
        status: DeviceStatus.offline,
        lastSeenAt: null,
      );
      expect(d.hardwareLabel, 'Perangkat belum teridentifikasi');
      expect(d.isMapped, isFalse);
      expect(d.isOnline, isFalse);
    });
  });

  group('Model TvAgentInfo — pembacaan response agen', () {
    test('membaca identitas dan kemampuan dari /health', () {
      final info = TvAgentInfo.fromJson('http://192.168.0.77:8787', {
        'device_uid': 'tv-abc',
        'paired': true,
        'station_code': 'ST03',
        'requires_pairing': false,
        'pairing_locked': false,
        'device': {
          'manufacturer': 'Xiaomi',
          'model': 'TV A2 43',
          'android_release': '11',
          'is_television': true,
          'is_device_owner': false,
          'kiosk_tier': 'SOFT',
          'app_version': '0.1.0',
        },
        'time': {'synced': true, 'seconds_since_sync': 4},
      });

      expect(info.deviceUid, 'tv-abc');
      expect(info.paired, isTrue);
      expect(info.stationCode, 'ST03');
      expect(info.kioskTier, KioskTier.soft);
      expect(info.hardwareLabel, 'Xiaomi TV A2 43');
      expect(info.osLabel, 'Android 11');
      expect(info.address, '192.168.0.77:8787');
    });

    test('kiosk tier yang tidak dikenal tidak membuat crash', () {
      final info = TvAgentInfo.fromJson('http://1.2.3.4:8787', {
        'device_uid': 'x',
        'device': {'kiosk_tier': 'SESUATU_BARU'},
      });

      expect(info.kioskTier, KioskTier.unknown);
    });

    test('response minim tetap terbaca dengan nilai aman', () {
      final info = TvAgentInfo.fromJson('http://1.2.3.4:8787', {});

      expect(info.paired, isFalse);
      expect(info.requiresPairing, isTrue,
          reason: 'default yang aman adalah menganggap belum dipasangkan');
      expect(info.hardwareLabel, 'Perangkat belum teridentifikasi');
    });
  });
}
