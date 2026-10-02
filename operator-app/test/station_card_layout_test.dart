import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:operator_app/core/theme/app_theme.dart';
import 'package:operator_app/core/time/server_time.dart';
import 'package:operator_app/core/time/ticker.dart';
import 'package:operator_app/domain/models/enums.dart';
import 'package:operator_app/domain/models/models.dart';
import 'package:operator_app/ui/widgets/station_card.dart';
import 'package:provider/provider.dart';

/// Mengunci perbaikan layout kartu station.
///
/// Versi pertama memakai dua `Spacer()` di dalam `Column`. Pada kartu yang
/// pendek — jendela kecil, portrait sempit, atau penskalaan teks sistem —
/// itu langsung menyebabkan RenderFlex overflow. Diganti `spaceBetween`.
///
/// Test ini membangun kartu pada beberapa ukuran dan memastikan tidak ada
/// exception. Overflow di Flutter memunculkan FlutterError saat debug,
/// jadi `takeException()` akan menangkapnya.

Station _station({
  required StationMasterStatus master,
  SessionStatus? sessionStatus,
  Duration? remaining,
  DeviceStatus device = DeviceStatus.online,
  int balanceDue = 125000,
}) {
  final now = DateTime.now().toUtc();
  return Station(
    id: 'sta-1',
    code: 'ST01',
    name: 'Station 1',
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
                child: StationCard(station: station, onTap: () {}),
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

  // Ukuran yang benar-benar bisa dihasilkan grid.
  //
  // `DashboardScreen` menjamin tinggi kartu tidak pernah di bawah
  // [minStationCardHeight]; kalau jendela terlalu pendek, grid-nya
  // di-scroll. Jadi batas bawah yang perlu diuji adalah nilai itu,
  // bukan ukuran sembarang yang lebih kecil.
  const sizes = <String, Size>{
    'tablet landscape (grid 3x2)': Size(300, 280),
    'tablet portrait (grid 2x3)': Size(340, minStationCardHeight),
    'jendela kecil, lebar minimum': Size(210, minStationCardHeight),
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
    }

    testWidgets('maintenance tidak overflow', (tester) async {
      await _pumpCard(
        tester,
        _station(master: StationMasterStatus.maintenance),
        size: const Size(300, 280),
      );
      expect(tester.takeException(), isNull);
    });

    testWidgets('TV offline + nama panjang tidak overflow', (tester) async {
      await _pumpCard(
        tester,
        _station(
          master: StationMasterStatus.active,
          sessionStatus: SessionStatus.active,
          device: DeviceStatus.offline,
        ),
        size: const Size(210, minStationCardHeight),
      );
      expect(tester.takeException(), isNull);
    });

    testWidgets('tagihan besar + nama panjang tidak menabrak', (tester) async {
      await _pumpCard(
        tester,
        _station(
          master: StationMasterStatus.active,
          sessionStatus: SessionStatus.active,
          balanceDue: 9850000,
        ),
        size: const Size(210, minStationCardHeight),
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
        size: const Size(300, 280),
        textScale: 1.3,
      );
      expect(tester.takeException(), isNull);
    });
  });

  group('Status diturunkan dari end_at, bukan snapshot server', () {
    testWidgets('sisa 5 menit -> WARNING walau server bilang ACTIVE',
        (tester) async {
      final station = _station(
        master: StationMasterStatus.active,
        sessionStatus: SessionStatus.active,
        remaining: const Duration(minutes: 5),
      );
      expect(deriveStationViewStatus(station), StationViewStatus.warning);
    });

    testWidgets('sudah lewat -> EXPIRED walau server bilang ACTIVE',
        (tester) async {
      final station = _station(
        master: StationMasterStatus.active,
        sessionStatus: SessionStatus.active,
        remaining: const Duration(minutes: -2),
      );
      expect(deriveStationViewStatus(station), StationViewStatus.expired);
    });

    testWidgets('sisa 30 menit tetap ACTIVE', (tester) async {
      final station = _station(
        master: StationMasterStatus.active,
        sessionStatus: SessionStatus.active,
        remaining: const Duration(minutes: 30),
      );
      expect(deriveStationViewStatus(station), StationViewStatus.active);
    });

    testWidgets('maintenance menang atas status sesi', (tester) async {
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
