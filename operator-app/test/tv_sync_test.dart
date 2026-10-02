import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:operator_app/data/tv/tv_agent_client.dart';
import 'package:operator_app/data/tv/tv_link_store.dart';
import 'package:operator_app/data/tv/tv_sync_service.dart';
import 'package:operator_app/domain/errors/api_error.dart';
import 'package:operator_app/domain/models/enums.dart';
import 'package:operator_app/domain/models/models.dart';
import 'package:operator_app/domain/models/tv_agent.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Test kontrol billing → TV.
///
/// Yang dikunci di sini adalah hal-hal yang kalau salah akan terlihat sebagai
/// "TV-nya aneh" di lokasi dan sulit dilacak:
/// - extend harus terkirim (ubah `end_at` tanpa ubah `session_id`)
/// - keadaan yang tidak berubah tidak boleh dikirim ulang
/// - kegagalan tidak boleh "mengunci" TV tertinggal
/// - tambah F&B tidak boleh memicu permintaan ke TV

/// Mencatat setiap permintaan supaya test bisa memeriksa apa yang dikirim,
/// bukan hanya berapa kali.
class RecordingAgent {
  RecordingAgent();

  final List<http.Request> requests = [];

  /// Status yang dikembalikan untuk permintaan berikutnya. Null = 200.
  int? nextStatus;
  Map<String, dynamic>? nextErrorBody;

  http.Client get client => MockClient.streaming((request, bodyStream) async {
        final body = await bodyStream.bytesToString();
        requests.add(
          http.Request(request.method, request.url)
            ..headers.addAll(request.headers)
            ..body = body,
        );

        final status = nextStatus;
        if (status != null && status >= 400) {
          // Default-nya error generik, bukan token-invalid: agen sebenarnya
          // membalas INTERNAL untuk kegagalan tak terduga, dan default yang
          // spesifik akan membuat test lain lulus/gagal karena alasan salah.
          final payload = nextErrorBody ??
              {
                'error': {
                  'code': 'INTERNAL',
                  'message': 'Kesalahan internal agen.',
                }
              };
          nextStatus = null;
          nextErrorBody = null;
          return http.StreamedResponse(
            Stream.value(utf8.encode(jsonEncode(payload))),
            status,
            headers: {'content-type': 'application/json'},
          );
        }

        nextStatus = null;
        return http.StreamedResponse(
          Stream.value(utf8.encode(jsonEncode(_responseFor(request.url.path)))),
          200,
          headers: {'content-type': 'application/json'},
        );
      });

  Map<String, dynamic> _responseFor(String path) => switch (path) {
        '/health' => {
            'agent': 'cempaka-tv-agent',
            'device_uid': 'tv-abc123',
            'paired': true,
            'station_code': 'ST01',
            'requires_pairing': false,
            'pairing_locked': false,
            'device': {
              'manufacturer': 'Xiaomi',
              'model': 'TV A2 43',
              'android_release': '11',
              'sdk_int': 30,
              'is_television': true,
              'is_device_owner': false,
              'kiosk_tier': 'SOFT',
              'app_version': '0.1.0',
            },
            'time': {'synced': true, 'offset_millis': 0},
          },
        '/pair' => {
            'device_token': 'tok-${'a' * 44}',
            'device_uid': 'tv-abc123',
            'station_code': 'ST01',
          },
        _ => {'mode': 'TIMER', 'station_code': 'ST01'},
      };

  List<http.Request> ofPath(String path) =>
      requests.where((r) => r.url.path == path).toList();

  void clear() => requests.clear();
}

Station station({
  String id = 'sta-1',
  String code = 'ST01',
  SessionStatus? status,
  DateTime? endAt,
  int balanceDue = 0,
  String sessionId = 'ses-1',
}) {
  final now = DateTime.now().toUtc();
  return Station(
    id: id,
    code: code,
    name: 'Station 1',
    consoleType: 'PS5 VIP',
    status: StationMasterStatus.active,
    session: status == null
        ? null
        : StationSessionSummary(
            id: sessionId,
            status: status,
            startedAt: now.subtract(const Duration(minutes: 10)),
            endAt: endAt ?? now.add(const Duration(minutes: 50)),
            customerLabel: 'Budi',
            balanceDue: balanceDue,
          ),
  );
}

void main() {
  late RecordingAgent agent;
  late TvAgentClient client;
  late TvLinkStore store;
  late TvSyncService sync;

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    agent = RecordingAgent();
    client = TvAgentClient(httpClient: agent.client);
    store = await TvLinkStore.open();
    sync = TvSyncService(client: client, store: store);
  });

  Future<void> linkSt01() async {
    await store.save(
      TvLink(
        stationId: 'sta-1',
        stationCode: 'ST01',
        deviceUid: 'tv-abc123',
        deviceToken: 'tok-123',
        baseUrl: 'http://192.168.0.77:8787',
        pairedAt: DateTime.now().toUtc(),
      ),
    );
  }

  group('Normalisasi alamat', () {
    test('IP saja dilengkapi skema dan port', () {
      expect(
        TvAgentClient.normalizeBaseUrl('192.168.0.77'),
        'http://192.168.0.77:8787',
      );
    });

    test('port yang sudah ada dipertahankan', () {
      expect(
        TvAgentClient.normalizeBaseUrl('192.168.0.77:9000'),
        'http://192.168.0.77:9000',
      );
    });

    test('garis miring di akhir dibuang', () {
      expect(
        TvAgentClient.normalizeBaseUrl('http://192.168.0.77:8787/'),
        'http://192.168.0.77:8787',
      );
    });
  });

  group('Station tanpa TV terpasang', () {
    test('tidak ada permintaan apa pun', () async {
      await sync.syncAll([station(status: SessionStatus.active)]);

      expect(agent.requests, isEmpty);
      expect(sync.statusFor('sta-1').health, TvLinkHealth.unlinked);
      expect(sync.isLinked('sta-1'), isFalse);
    });
  });

  group('Mengirim keadaan sesi', () {
    test('sesi berjalan dikirim sebagai POST /session', () async {
      await linkSt01();
      await sync.syncAll([station(status: SessionStatus.active)]);

      final posts = agent.ofPath('/session');
      expect(posts.length, 1);
      expect(posts.first.method, 'POST');

      final body = jsonDecode(posts.first.body) as Map<String, dynamic>;
      expect(body['session_id'], 'ses-1');
      expect(body['station_code'], 'ST01');
      expect(body['pending_payment'], false);
      expect(body['end_at'], isA<int>());
      // sender_time wajib ada: itu yang dipakai TV mengoreksi jamnya (DEC-003).
      expect(body['sender_time'], isA<int>());
    });

    test('token device dikirim di header', () async {
      await linkSt01();
      await sync.syncAll([station(status: SessionStatus.active)]);

      expect(
        agent.ofPath('/session').first.headers['X-Agent-Token'],
        'tok-123',
      );
    });

    test('station kosong dikirim sebagai DELETE /session', () async {
      await linkSt01();
      await sync.syncAll([station()]);

      final reqs = agent.ofPath('/session');
      expect(reqs.length, 1);
      expect(reqs.first.method, 'DELETE');
    });

    test('menunggu bayar ditandai pending_payment', () async {
      await linkSt01();
      await sync.syncAll([
        station(status: SessionStatus.pendingPayment, endAt: null),
      ]);

      final body =
          jsonDecode(agent.ofPath('/session').first.body) as Map<String, dynamic>;
      expect(body['pending_payment'], true);
    });

    test('berhasil kirim menandai tersambung', () async {
      await linkSt01();
      await sync.syncAll([station(status: SessionStatus.active)]);

      final status = sync.statusFor('sta-1');
      expect(status.health, TvLinkHealth.online);
      expect(status.lastOkAt, isNotNull);
      expect(status.lastError, isNull);
    });
  });

  group('Hanya perubahan yang dikirim', () {
    test('keadaan yang sama tidak dikirim dua kali', () async {
      await linkSt01();
      final s = station(status: SessionStatus.active);

      await sync.syncAll([s]);
      agent.clear();
      await sync.syncAll([s]);

      expect(
        agent.requests,
        isEmpty,
        reason: 'dashboard menyegarkan tiap kali operator kembali ke monitor; '
            'mengirim ulang setiap kali berarti belasan permintaan per menit '
            'tanpa ada yang berubah di layar TV',
      );
    });

    test('extend terkirim — end_at berubah walau session_id sama', () async {
      await linkSt01();
      final now = DateTime.now().toUtc();

      await sync.syncAll([
        station(status: SessionStatus.active, endAt: now.add(const Duration(minutes: 10))),
      ]);
      agent.clear();

      await sync.syncAll([
        station(status: SessionStatus.active, endAt: now.add(const Duration(minutes: 40))),
      ]);

      expect(
        agent.ofPath('/session').length,
        1,
        reason: 'extend tidak mengubah session_id, hanya end_at — kalau end_at '
            'tidak masuk sidik, tambah durasi tidak akan pernah sampai ke TV',
      );
    });

    test('tambah F&B TIDAK memicu permintaan ke TV', () async {
      await linkSt01();
      final now = DateTime.now().toUtc();
      final endAt = now.add(const Duration(minutes: 30));

      await sync.syncAll([
        station(status: SessionStatus.active, endAt: endAt, balanceDue: 0),
      ]);
      agent.clear();

      // Hanya tagihan yang berubah; TV tidak menampilkan tagihan.
      await sync.syncAll([
        station(status: SessionStatus.active, endAt: endAt, balanceDue: 45000),
      ]);

      expect(
        agent.requests,
        isEmpty,
        reason: 'kalau tagihan masuk sidik, setiap teh manis memicu satu '
            'permintaan ke TV tanpa ada yang berubah di layarnya',
      );
    });

    test('sesi baru di station yang sama terkirim', () async {
      await linkSt01();
      await sync.syncAll([station(status: SessionStatus.active, sessionId: 'ses-1')]);
      agent.clear();

      await sync.syncAll([station(status: SessionStatus.active, sessionId: 'ses-2')]);

      expect(agent.ofPath('/session').length, 1);
    });

    test('checkout terkirim sebagai DELETE', () async {
      await linkSt01();
      await sync.syncAll([station(status: SessionStatus.active)]);
      agent.clear();

      await sync.syncAll([station()]);

      final reqs = agent.ofPath('/session');
      expect(reqs.length, 1);
      expect(reqs.first.method, 'DELETE');
    });

    test('forcePush mengirim walau tidak ada perubahan', () async {
      await linkSt01();
      final s = station(status: SessionStatus.active);
      await sync.syncAll([s]);
      agent.clear();

      await sync.forcePush(s);

      expect(agent.ofPath('/session').length, 1);
    });
  });

  group('Kegagalan', () {
    test('TV tidak merespons ditandai unreachable', () async {
      await linkSt01();
      agent.nextStatus = 500;

      await sync.syncAll([station(status: SessionStatus.active)]);

      final status = sync.statusFor('sta-1');
      expect(status.health, TvLinkHealth.unreachable);
      expect(status.lastError, isNotNull);
    });

    test('token ditolak dibedakan dari tidak terjangkau', () async {
      await linkSt01();
      agent.nextStatus = 401;
      agent.nextErrorBody = {
        'error': {
          'code': ApiErrorCode.deviceTokenInvalid,
          'message': 'Token device tidak sah atau sudah dicabut.',
        }
      };

      await sync.syncAll([station(status: SessionStatus.active)]);

      expect(
        sync.statusFor('sta-1').health,
        TvLinkHealth.rejected,
        reason: 'penanganannya berbeda: ini perlu pairing ulang, '
            'bukan menunggu jaringan membaik',
      );
    });

    test('kegagalan tidak mengunci TV tertinggal', () async {
      await linkSt01();
      final s = station(status: SessionStatus.active);

      agent.nextStatus = 500;
      await sync.syncAll([s]);
      expect(sync.statusFor('sta-1').health, TvLinkHealth.unreachable);
      agent.clear();

      // Percobaan berikutnya harus mengirim lagi. Kalau sidik disimpan saat
      // gagal, TV tertinggal sampai ada perubahan berikutnya — bisa berjam-jam.
      await sync.syncAll([s]);

      expect(agent.ofPath('/session').length, 1);
      expect(sync.statusFor('sta-1').health, TvLinkHealth.online);
    });
  });

  group('Pairing', () {
    test('pair menyimpan link lalu langsung mengirim keadaan', () async {
      final s = station(status: SessionStatus.active);

      await sync.pair(station: s, baseUrl: '192.168.0.77', code: '123456');

      expect(store.forStation('sta-1'), isNotNull);
      expect(store.forStation('sta-1')!.stationCode, 'ST01');
      expect(agent.ofPath('/pair').length, 1);

      // Pengiriman langsung penting: tanpanya TV menampilkan layar idle walau
      // station-nya sedang ada sesi, dan operator menyimpulkan pairing gagal.
      expect(agent.ofPath('/session').length, 1);
    });

    test('satu TV tidak boleh terpasang di dua station', () async {
      await sync.pair(
        station: station(id: 'sta-1', code: 'ST01'),
        baseUrl: '192.168.0.77',
        code: '111111',
      );
      await sync.pair(
        station: station(id: 'sta-2', code: 'ST02'),
        baseUrl: '192.168.0.77',
        code: '222222',
      );

      // Kalau keduanya bertahan, dua station mengirim perintah ke TV yang sama
      // dan timer-nya saling menimpa tanpa ada yang tahu kenapa.
      expect(store.forStation('sta-1'), isNull);
      expect(store.forStation('sta-2'), isNotNull);
      expect(store.all.length, 1);
    });

    test('unpair tetap melepas walau TV tidak merespons', () async {
      await linkSt01();
      agent.nextStatus = 500;

      await sync.unpair('sta-1');

      // TV yang mati atau sudah dibawa pergi tidak boleh membuat station
      // terjebak dengan perangkat yang tidak ada.
      expect(store.forStation('sta-1'), isNull);
      expect(sync.statusFor('sta-1').health, TvLinkHealth.unlinked);
    });

    test('rebind memperbarui alamat dan memaksa kirim ulang', () async {
      await linkSt01();
      await sync.syncAll([station(status: SessionStatus.active)]);
      agent.clear();

      await sync.rebind('sta-1', '192.168.0.99');

      expect(store.forStation('sta-1')!.baseUrl, 'http://192.168.0.99:8787');

      await sync.syncAll([station(status: SessionStatus.active)]);
      expect(agent.ofPath('/session').length, 1);
      expect(
        agent.ofPath('/session').first.url.host,
        '192.168.0.99',
      );
    });
  });

  group('Penyimpanan link', () {
    test('link bertahan setelah store dibuka ulang', () async {
      await linkSt01();

      final reopened = await TvLinkStore.open();

      expect(reopened.forStation('sta-1'), isNotNull);
      expect(reopened.forStation('sta-1')!.deviceToken, 'tok-123');
    });

    test('pencarian berdasarkan device_uid menemukan link', () async {
      await linkSt01();
      expect(store.forDeviceUid('tv-abc123')?.stationId, 'sta-1');
      expect(store.forDeviceUid('tidak-ada'), isNull);
    });

    test('data rusak tidak membuat app gagal start', () async {
      SharedPreferences.setMockInitialValues({'tv.links.v1': 'bukan json'});

      final opened = await TvLinkStore.open();

      expect(opened.all, isEmpty);
    });
  });

  group('Hitungan untuk ringkasan', () {
    test('menghitung tersambung dan bermasalah', () async {
      await linkSt01();
      await store.save(
        TvLink(
          stationId: 'sta-2',
          stationCode: 'ST02',
          deviceUid: 'tv-def456',
          deviceToken: 'tok-456',
          baseUrl: 'http://192.168.0.78:8787',
        ),
      );

      await sync.syncAll([station(id: 'sta-1', status: SessionStatus.active)]);
      agent.nextStatus = 500;
      await sync.syncAll([station(id: 'sta-2', code: 'ST02')]);

      expect(sync.linkedCount, 2);
      expect(sync.onlineCount, 1);
      expect(sync.problemCount, 1);
    });
  });
}
