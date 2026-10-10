import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:operator_app/core/config/api_config.dart';
import 'package:operator_app/data/api/server_connector.dart';
import 'package:operator_app/data/api/server_discovery.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Penemuan server otomatis — DEC-041, menutup OD-011.
///
/// Yang diuji di sini sebagian besar adalah **penolakan**: apa saja yang
/// menjawab di port 8000 tetapi bukan server billing. Kalau bagian itu salah,
/// tablet akan menyambung ke printer atau router dan kesalahannya tidak
/// terlihat sampai operator menekan tombol pertama.

/// Jawaban `GET /api/v1/health` yang sah — bentuknya mengikuti
/// `HealthController` di backend.
String _healthBody({
  String app = ServerDiscovery.appId,
  String instance = 'Amor Gaming Space',
}) =>
    jsonEncode({
      'data': {
        'app': app,
        'instance': instance,
        'status': 'ok',
        'version': 'v1',
        'database': 'ok',
        'broadcast': 'reverb',
      },
      'meta': {'server_time': '2026-10-10T03:00:00Z'},
    });

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  /// Jaringan tiruan: hanya [serverAt] yang menjawab seperti server billing,
  /// sisanya mati. Mencatat alamat yang diketuk supaya bisa dibuktikan bahwa
  /// pemindaian tidak dilakukan saat tidak perlu.
  ({http.Client client, List<String> probed}) fakeNetwork({
    String? serverAt,
    List<String> imposters = const [],
    String imposterApp = 'octoprint',
  }) {
    final probed = <String>[];

    final client = MockClient((request) async {
      final origin = '${request.url.scheme}://${request.url.authority}';
      probed.add(origin);

      if (origin == serverAt) {
        return http.Response(_healthBody(), 200,
            headers: {'content-type': 'application/json'});
      }
      if (imposters.contains(origin)) {
        return http.Response(_healthBody(app: imposterApp), 200,
            headers: {'content-type': 'application/json'});
      }
      // Alamat kosong: tidak ada yang mendengarkan.
      throw http.ClientException('connection refused', request.url);
    });

    return (client: client, probed: probed);
  }

  ServerDiscovery discoveryOn(
    http.Client client, {
    List<String> addresses = const ['192.168.100.23'],
  }) =>
      ServerDiscovery(
        httpClient: client,
        localAddresses: () async => addresses,
      );

  group('probe', () {
    test('menerima server billing', () async {
      final net = fakeNetwork(serverAt: 'http://192.168.100.11:8000');

      final found =
          await discoveryOn(net.client).probe('http://192.168.100.11:8000');

      expect(found, isNotNull);
      expect(found!.baseUrl, 'http://192.168.100.11:8000');
      expect(found.host, '192.168.100.11');
      // Nama rental, bukan IP — itu yang dibaca teknisi saat memilih.
      expect(found.instance, 'Amor Gaming Space');
    });

    test('menolak layanan lain yang hidup di port yang sama', () async {
      /*
       * Inti pengamanannya. Printer, router, dan dashboard lain sering
       * memakai port 8000 dan menjawab 200 dengan JSON. Tanpa pemeriksaan
       * `app`, scanner akan menerimanya.
       */
      final net = fakeNetwork(imposters: ['http://192.168.100.11:8000']);

      expect(
        await discoveryOn(net.client).probe('http://192.168.100.11:8000'),
        isNull,
      );
    });

    test('menolak jawaban yang bukan JSON', () async {
      final client = MockClient((_) async => http.Response('<html>', 200));

      expect(
        await discoveryOn(client).probe('http://192.168.100.11:8000'),
        isNull,
      );
    });

    test('menolak status selain 200', () async {
      final client = MockClient((_) async => http.Response(_healthBody(), 503));

      expect(
        await discoveryOn(client).probe('http://192.168.100.11:8000'),
        isNull,
      );
    });

    test('alamat mati tidak melempar exception', () async {
      // Pemindaian menyentuh 254 alamat mati; satu exception yang lolos akan
      // menghentikan seluruh pencarian.
      final net = fakeNetwork();

      expect(
        await discoveryOn(net.client).probe('http://192.168.100.11:8000'),
        isNull,
      );
    });
  });

  group('pemindaian', () {
    test('menemukan server di subnet perangkat sendiri', () async {
      final net = fakeNetwork(serverAt: 'http://192.168.100.11:8000');

      final found = await discoveryOn(
        net.client,
        addresses: ['192.168.100.23'],
      ).discover();

      expect(found?.baseUrl, 'http://192.168.100.11:8000');
    });

    test('tidak memindai subnet lain', () async {
      /*
       * Tablet di 10.0.0.x tidak akan menemukan server di 192.168.100.x,
       * dan memang tidak boleh mencoba — itu jaringan orang lain.
       */
      final net = fakeNetwork(serverAt: 'http://192.168.100.11:8000');

      final found =
          await discoveryOn(net.client, addresses: ['10.0.0.5']).discover();

      expect(found, isNull);
      expect(net.probed.every((u) => u.startsWith('http://10.0.0.')), isTrue);
    });

    test('memindai setiap subnet kalau perangkat punya dua jaringan', () async {
      // Tablet yang tersambung WiFi sekaligus tethering punya dua IP.
      final net = fakeNetwork(serverAt: 'http://192.168.100.11:8000');

      final found = await discoveryOn(
        net.client,
        addresses: ['10.0.0.5', '192.168.100.23'],
      ).discover();

      expect(found?.baseUrl, 'http://192.168.100.11:8000');
    });

    test('jaringan kosong menghasilkan null, bukan error', () async {
      final net = fakeNetwork();

      expect(await discoveryOn(net.client).discover(), isNull);
    });

    test('mengembalikan semua server kalau ada lebih dari satu', () async {
      /*
       * Dua server hidup biasanya berarti laptop cadangan lupa dimatikan.
       * Memilih sendiri salah satunya berarti transaksi masuk ke database
       * yang keliru, jadi keduanya harus dilaporkan dan teknisi memilih.
       */
      final client = MockClient((request) async {
        final origin = '${request.url.scheme}://${request.url.authority}';
        if (origin == 'http://192.168.100.11:8000' ||
            origin == 'http://192.168.100.12:8000') {
          return http.Response(_healthBody(instance: origin), 200);
        }
        throw http.ClientException('connection refused', request.url);
      });

      final all = await discoveryOn(client).discoverAll();

      expect(all.map((s) => s.host),
          containsAll(['192.168.100.11', '192.168.100.12']));
    });

    test('hanya port 8000 yang diketuk', () async {
      /*
       * Menambah port lain menggandakan waktu pindai dan menambah kandidat
       * palsu — port 80 justru yang paling sering dijawab router dan printer.
       */
      final net = fakeNetwork();
      await discoveryOn(net.client).discover();

      expect(net.probed, hasLength(254));
      expect(net.probed.every((u) => u.endsWith(':8000')), isTrue);
    });

    test('tanpa IP lokal tidak memindai apa pun', () async {
      // WiFi mati: tidak ada subnet untuk ditebak.
      final net = fakeNetwork(serverAt: 'http://192.168.100.11:8000');

      expect(
        await discoveryOn(net.client, addresses: const []).discover(),
        isNull,
      );
      expect(net.probed, isEmpty);
    });
  });

  group('ServerConnector', () {
    setUp(() async {
      SharedPreferences.setMockInitialValues({});
      await ApiConfig.instance.resetToBuildDefaults();
    });

    test('alamat yang diingat masih benar — tidak memindai', () async {
      await ApiConfig.instance.update(baseUrl: 'http://192.168.100.11:8000');
      final net = fakeNetwork(serverAt: 'http://192.168.100.11:8000');

      final connector = ServerConnector(
        discovery: discoveryOn(net.client),
        config: ApiConfig.instance,
      );

      expect((await connector.connect()).outcome, ConnectOutcome.remembered);
      // Satu ketukan saja. Memindai 254 alamat setiap buka aplikasi akan
      // terasa lambat setiap hari untuk masalah yang jarang terjadi.
      expect(net.probed, ['http://192.168.100.11:8000']);
    });

    test('IP server berganti — ditemukan lagi dan disimpan', () async {
      await ApiConfig.instance.update(
        baseUrl: 'http://192.168.100.11:8000',
        wsHost: '192.168.100.11',
      );
      // Router memberi laptop alamat lain setelah menyambung ulang WiFi.
      final net = fakeNetwork(serverAt: 'http://192.168.100.37:8000');

      final result = await ServerConnector(
        discovery: discoveryOn(net.client),
        config: ApiConfig.instance,
      ).connect();

      expect(result.outcome, ConnectOutcome.rediscovered);
      expect(result.addressChanged, isTrue);
      expect(ApiConfig.instance.baseUrl, 'http://192.168.100.37:8000');
      // Tanpa ini realtime tetap menunjuk IP lama: data bisa dimuat tapi
      // tablet diam total.
      expect(ApiConfig.instance.wsHost, '192.168.100.37');
      expect(ApiConfig.instance.wsPort, 8080);
    });

    test('server mati — alamat lama TIDAK dihapus', () async {
      /*
       * Mematikan laptop sebentar tidak boleh membuat setiap tablet lupa
       * alamatnya; kalau dihapus, semuanya harus diisi ulang manual saat
       * laptop hidup lagi.
       */
      await ApiConfig.instance.update(baseUrl: 'http://192.168.100.11:8000');
      final net = fakeNetwork();

      final result = await ServerConnector(
        discovery: discoveryOn(net.client),
        config: ApiConfig.instance,
      ).connect();

      expect(result.outcome, ConnectOutcome.notFound);
      expect(result.connected, isFalse);
      expect(ApiConfig.instance.baseUrl, 'http://192.168.100.11:8000');
    });

    test('memilih manual dari hasil pemindaian ikut tersimpan', () async {
      final net = fakeNetwork(serverAt: 'http://192.168.100.11:8000');
      final connector = ServerConnector(
        discovery: discoveryOn(net.client),
        config: ApiConfig.instance,
      );

      final servers = await connector.scan();
      expect(servers, hasLength(1));

      await connector.remember(servers.first);

      expect(ApiConfig.instance.baseUrl, 'http://192.168.100.11:8000');
      expect(ApiConfig.instance.wsHost, '192.168.100.11');
    });
  });
}
