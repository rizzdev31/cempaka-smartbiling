// Menolak overflow layout di shell dan section-section-nya.
//
// Kenapa test ini ada: 186 test sebelumnya lolos sementara aplikasi
// sungguhan mencetak **empat** overflow berbeda begitu dijalankan. Test
// station card menguji kartu secara terpisah pada ukuran yang dijamin grid,
// jadi tidak ada yang pernah memompa shell utuh — dan justru di situ
// semuanya: BrandMark, blok operator di sidebar, header, dan strip shift.
//
// Catatan soal angka: widget test memakai font uji yang setiap glifnya
// kotak seukuran font-size, jadi teks di sini **lebih lebar** daripada di
// perangkat. Itu menjadikan test ini lebih ketat dari kenyataan — bagus
// sebagai penjaga, tapi jangan bandingkan jumlah pikselnya dengan perangkat.
//
// Hanya overflow **pertama** per skenario yang terlihat; binding test
// menyimpan satu exception. Itu cukup untuk menggagalkan test, dan pesannya
// menyebut berkas penyebabnya.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:operator_app/app.dart';
import 'package:operator_app/data/api/api_client.dart';
import 'package:operator_app/data/fake/fake_billing_repository.dart';
import 'package:operator_app/data/tv/tv_agent_client.dart';
import 'package:operator_app/data/tv/tv_link_store.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Memompa aplikasi pada [size], mengunjungi tiap section, dan mengembalikan
/// pesan overflow pertama — atau null kalau bersih.
Future<String?> _firstOverflow(
  WidgetTester tester,
  Size size, {
  double textScale = 1.0,
}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1.0;
  tester.platformDispatcher.textScaleFactorTestValue = textScale;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);

  // Token palsu + `/auth/me` tiruan: shell hanya muncul setelah gate auth
  // lolos, dan yang diuji di sini layout-nya, bukan alur masuknya.
  SharedPreferences.setMockInitialValues({'auth.token': 'token-uji'});
  final store = await TvLinkStore.open();

  final api = ApiClient(
    httpClient: MockClient((request) async {
      if (request.url.path.endsWith('/auth/me')) {
        return http.Response(
          // Bentuknya disalin dari respons server SUNGGUHAN, bukan dikarang:
          // `id` adalah UUID string, bukan int. Mock yang menebak bentuk
          // respons hanya memindahkan bug ke tempat yang tidak terlihat.
          '{"data":{"user":{"id":"9505a47b-495a-4bcd-a28e-6f9599ad2d48",'
          '"name":"Operator Kasir","username":"operator1","role":"OPERATOR",'
          '"permissions":["station.read","session.read"]},'
          '"active_shift":null}}',
          200,
          headers: {'content-type': 'application/json'},
        );
      }
      return http.Response('{"error":{"code":"INTERNAL"}}', 500);
    }),
  );
  await api.loadToken();
  addTearDown(api.close);

  // Klien palsu: tanpa ini widget test membuat http.Client sungguhan, dan
  // klien yang hidup menahan isolate sampai suite butuh menit untuk keluar.
  final client = TvAgentClient(
    httpClient: MockClient((_) async => http.Response('{}', 503)),
  );
  addTearDown(client.close);

  String? overflow;
  void capture() {
    final e = tester.takeException();
    if (e != null && e.toString().contains('overflowed')) {
      overflow ??= e.toString();
    } else if (e != null) {
      // Bukan overflow — jangan ditelan, itu kegagalan tersendiri.
      throw e;
    }
  }

  await tester.pumpWidget(
    OperatorApp(
      tvLinkStore: store,
      apiClient: api,
      tvClient: client,
      billingRepository: FakeBillingRepository(simulateLatency: false),
      // Tanpa ini test memindai subnet sungguhan.
      autoConnect: false,
    ),
  );
  await tester.pump(const Duration(milliseconds: 600));
  capture();

  // Setiap section membawa layarnya sendiri; overflow di salah satunya
  // tidak akan terlihat kalau hanya dashboard yang dipompa.
  for (final label in ['F&B', 'Shift', 'Perangkat', 'Pengaturan']) {
    final finder = find.text(label);
    if (finder.evaluate().isNotEmpty) {
      await tester.tap(finder.first, warnIfMissed: false);
      await tester.pump(const Duration(milliseconds: 400));
      capture();
    }
  }

  return overflow;
}

void main() {
  // Rentang yang diuji, dan alasannya masing-masing.
  const sizes = <String, Size>{
    // Tablet operator — ukuran sasaran sebenarnya.
    'tablet 1280x800': Size(1280, 800),
    // Tepat di dua sisi breakpoint sidebar (1040): penuh vs rail.
    'tepat di breakpoint sidebar 1040x768': Size(1040, 768),
    'tepat di bawahnya 1039x768': Size(1039, 768),
    // Tepat di dua sisi breakpoint header compact (620).
    'tepat di breakpoint header 620x800': Size(620, 800),
    'tepat di bawahnya 619x800': Size(619, 800),
    // Bukan perangkat sasaran, tapi tidak boleh rusak.
    'tablet kecil 800x480': Size(800, 480),
    'ponsel potret 411x731': Size(411, 731),
  };

  group('Shell tidak overflow', () {
    sizes.forEach((name, size) {
      testWidgets(name, (tester) async {
        expect(await _firstOverflow(tester, size), isNull);
      });
    });
  });

  // UI-UX-SPEC §10 mewajibkan tahan sampai 1,3x. Sebelum ini tidak ada
  // yang mengujinya sama sekali.
  testWidgets('tablet 1280x800 dengan teks 1,3x', (tester) async {
    expect(
      await _firstOverflow(tester, const Size(1280, 800), textScale: 1.3),
      isNull,
    );
  });
}
