import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:operator_app/core/theme/app_theme.dart';
import 'package:operator_app/core/theme/tokens.dart';
import 'package:operator_app/core/time/server_time.dart';
import 'package:operator_app/core/time/ticker.dart';
import 'package:operator_app/domain/models/enums.dart';
import 'package:operator_app/domain/models/models.dart';
import 'package:operator_app/ui/widgets/station_card.dart';
import 'package:provider/provider.dart';

/// Mengunci layout kartu station.
///
/// Riwayat kenapa test ini ada:
/// 1. Versi pertama memakai dua `Spacer()` di dalam `Column`, yang overflow
///    28–48 px pada kartu pendek.
/// 2. Redesign menambah header (tipe konsol + status) dan baris aksi empat
///    tombol, sehingga kartu tumbuh 279 px — melebihi ruang yang tersedia
///    di tablet 1280x800 landscape (±265 px per baris).
///
/// Ukuran yang diuji adalah ukuran yang **benar-benar bisa dihasilkan grid**:
/// `DashboardScreen` menjamin lebar lewat breakpoint kolom dan tinggi lewat
/// [AppSize.stationCardMinHeight].

Station _station({
  required StationMasterStatus master,
  SessionStatus? sessionStatus,
  Duration? remaining,
  DeviceStatus device = DeviceStatus.online,
  int balanceDue = 125000,
  String? consoleType = 'PS5 Reguler',
}) {
  final now = DateTime.now().toUtc();
  return Station(
    id: 'sta-1',
    code: 'ST01',
    name: 'Station 1',
    consoleType: consoleType,
    status: master,
    device: DeviceSummary(
      id: 'dev-1',
      status: device,
      lastSeenAt: now,
      appVersion: '0.1.0',
    ),
    session: sessionStatus == null
        ? null
        : StationSessionSummary(
            id: 'ses-1',
            status: sessionStatus,
            startedAt: now.subtract(const Duration(minutes: 30)),
            endAt: now.add(remaining ?? const Duration(minutes: 30)),
            customerLabel: 'Nama Customer Yang Cukup Panjang',
            balanceDue: balanceDue,
          ),
  );
}

Future<void> _pumpCard(
  WidgetTester tester,
  Station station, {
  required Size size,
  double textScale = 1.0,
}) async {
  await tester.pumpWidget(
    MaterialApp(
      theme: AppTheme.dark(),
      home: ChangeNotifierProvider(
        create: (_) => AppTicker(),
        child: MediaQuery(
          data: MediaQueryData(textScaler: TextScaler.linear(textScale)),
          child: Scaffold(
            body: Center(
              child: SizedBox(
                width: size.width,
                height: size.height,
                child: StationCard(
                  station: station,
                  onTap: () {},
                  onExtend: (_) async {},
                  onAddFnb: () async {},
                  onPay: () async {},
                  onStart: () async {},
                  hourlyRateHint: 20000,
                ),
              ),
            ),
          ),
        ),
      ),
    ),
  );
  await tester.pump(const Duration(milliseconds: 300));
}

void main() {
  setUp(() {
    ServerTime.instance.resetForTest();
    ServerTime.instance.sync(DateTime.now().toUtc());
  });

  // Ukuran yang bisa dihasilkan grid dashboard.
  const minW = AppSize.stationCardMinWidth;
  const minH = AppSize.stationCardMinHeight;

  const sizes = <String, Size>{
    'tablet landscape, 3 kolom': Size(340, 268),
    'tablet portrait, 2 kolom': Size(360, 280),
    'satu kolom, layar sempit': Size(640, 300),
    'batas minimum grid': Size(minW, minH),
  };

  group('StationCard tidak overflow', () {
    for (final entry in sizes.entries) {
      testWidgets('sesi aktif — ${entry.key}', (tester) async {
        await _pumpCard(
          tester,
          _station(
            master: StationMasterStatus.active,
            sessionStatus: SessionStatus.active,
          ),
          size: entry.value,
        );
        expect(tester.takeException(), isNull);
      });

      testWidgets('station kosong — ${entry.key}', (tester) async {
        await _pumpCard(
          tester,
          _station(master: StationMasterStatus.active),
          size: entry.value,
        );
        expect(tester.takeException(), isNull);
      });

      testWidgets('menunggu bayar — ${entry.key}', (tester) async {
        await _pumpCard(
          tester,
          _station(
            master: StationMasterStatus.active,
            sessionStatus: SessionStatus.pendingPayment,
          ),
          size: entry.value,
        );
        expect(tester.takeException(), isNull);
      });
    }

    testWidgets('maintenance tidak overflow', (tester) async {
      await _pumpCard(
        tester,
        _station(master: StationMasterStatus.maintenance),
        size: const Size(minW, minH),
      );
      expect(tester.takeException(), isNull);
    });

    testWidgets('TV offline + status terpanjang tidak overflow',
        (tester) async {
      // "Menunggu Bayar" adalah label status terpanjang, dan ikon offline
      // mengambil ruang tambahan di header yang sama.
      await _pumpCard(
        tester,
        _station(
          master: StationMasterStatus.active,
          sessionStatus: SessionStatus.pendingPayment,
          device: DeviceStatus.offline,
        ),
        size: const Size(minW, minH),
      );
      expect(tester.takeException(), isNull);
    });

    testWidgets('tipe konsol panjang tidak mendorong status keluar',
        (tester) async {
      await _pumpCard(
        tester,
        _station(
          master: StationMasterStatus.active,
          sessionStatus: SessionStatus.warning,
          remaining: const Duration(minutes: 4),
          consoleType: 'PlayStation 5 VIP Room Lantai 2',
        ),
        size: const Size(minW, minH),
      );
      expect(tester.takeException(), isNull);
    });

    testWidgets('tagihan besar tidak menabrak nama customer', (tester) async {
      await _pumpCard(
        tester,
        _station(
          master: StationMasterStatus.active,
          sessionStatus: SessionStatus.active,
          balanceDue: 9850000,
        ),
        size: const Size(minW, minH),
      );
      expect(tester.takeException(), isNull);
    });

    testWidgets('tanpa tipe konsol tetap rapi', (tester) async {
      await _pumpCard(
        tester,
        _station(
          master: StationMasterStatus.active,
          sessionStatus: SessionStatus.active,
          consoleType: null,
        ),
        size: const Size(minW, minH),
      );
      expect(tester.takeException(), isNull);
    });

    testWidgets('penskalaan teks 1.3x tidak overflow', (tester) async {
      // `app.dart` membatasi textScaler maksimum 1.3 — batas itu yang diuji.
      await _pumpCard(
        tester,
        _station(
          master: StationMasterStatus.active,
          sessionStatus: SessionStatus.active,
        ),
        size: const Size(360, 300),
        textScale: 1.3,
      );
      expect(tester.takeException(), isNull);
    });
  });

  group('Status diturunkan dari end_at, bukan snapshot server', () {
    test('sisa 5 menit -> WARNING walau server bilang ACTIVE', () {
      final station = _station(
        master: StationMasterStatus.active,
        sessionStatus: SessionStatus.active,
        remaining: const Duration(minutes: 5),
      );
      expect(deriveStationViewStatus(station), StationViewStatus.warning);
    });

    test('sudah lewat -> EXPIRED walau server bilang ACTIVE', () {
      final station = _station(
        master: StationMasterStatus.active,
        sessionStatus: SessionStatus.active,
        remaining: const Duration(minutes: -2),
      );
      expect(deriveStationViewStatus(station), StationViewStatus.expired);
    });

    test('sisa 30 menit tetap ACTIVE', () {
      final station = _station(
        master: StationMasterStatus.active,
        sessionStatus: SessionStatus.active,
        remaining: const Duration(minutes: 30),
      );
      expect(deriveStationViewStatus(station), StationViewStatus.active);
    });

    test('maintenance menang atas status sesi', () {
      final station = _station(
        master: StationMasterStatus.maintenance,
        sessionStatus: SessionStatus.active,
      );
      expect(deriveStationViewStatus(station), StationViewStatus.maintenance);
    });
  });

  group('Proporsi waktu', () {
    test('setengah jalan -> 0.5', () {
      final now = DateTime.now().toUtc();
      final s = StationSessionSummary(
        id: 'x',
        status: SessionStatus.active,
        startedAt: now.subtract(const Duration(minutes: 30)),
        endAt: now.add(const Duration(minutes: 30)),
        customerLabel: null,
        balanceDue: 0,
      );
      expect(s.progressAt(now), closeTo(0.5, 0.01));
    });

    test('sudah lewat -> dibatasi 1.0, tidak lebih', () {
      final now = DateTime.now().toUtc();
      final s = StationSessionSummary(
        id: 'x',
        status: SessionStatus.expired,
        startedAt: now.subtract(const Duration(minutes: 90)),
        endAt: now.subtract(const Duration(minutes: 30)),
        customerLabel: null,
        balanceDue: 0,
      );
      expect(s.progressAt(now), 1.0);
    });

    test('belum mulai (PENDING_PAYMENT) -> null, bar tidak digambar', () {
      final now = DateTime.now().toUtc();
      final s = StationSessionSummary(
        id: 'x',
        status: SessionStatus.pendingPayment,
        startedAt: null,
        endAt: null,
        customerLabel: null,
        balanceDue: 0,
      );
      expect(s.progressAt(now), isNull);
    });
  });
}
