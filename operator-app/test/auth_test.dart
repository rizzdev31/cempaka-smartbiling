// Alur masuk operator.
//
// Semua bentuk respons di sini disalin dari server SUNGGUHAN (`curl` ke
// Laravel yang jalan), bukan dikarang dari presenter. Itu bukan kerewelan:
// bug pertama di alur ini justru lahir dari menebak `user.id` sebagai int
// padahal backend mengirim UUID string, dan mock yang ikut menebak membuat
// test hijau sambil aplikasinya gagal.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:operator_app/core/config/api_config.dart';
import 'package:operator_app/core/theme/app_theme.dart';
import 'package:operator_app/data/api/api_client.dart';
import 'package:operator_app/data/api/auth_repository.dart';
import 'package:operator_app/ui/auth/auth_controller.dart';
import 'package:operator_app/ui/auth/login_screen.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

// ── Respons server sungguhan ─────────────────────────────────────────

const _userJson = '{"id":"9505a47b-495a-4bcd-a28e-6f9599ad2d48",'
    '"name":"Operator Kasir","username":"operator1","role":"OPERATOR",'
    '"permissions":["station.read","session.create"]}';

const _loginOk = '{"data":{"token":"1|abcdef1234567890","user":$_userJson,'
    '"active_shift":null}}';

const _meOk = '{"data":{"user":$_userJson,"active_shift":null}}';

const _invalidCredentials = '{"error":{"code":"INVALID_CREDENTIALS",'
    '"message":"Username atau password salah."}}';

const _unauthenticated = '{"error":{"code":"UNAUTHENTICATED",'
    '"message":"Sesi login tidak valid atau sudah kedaluwarsa."}}';

http.Response _json(String body, int status) => http.Response(
      body,
      status,
      headers: {'content-type': 'application/json'},
    );

/// Membangun klien + repo di atas satu handler tiruan.
({ApiClient api, AuthRepository repo}) _wire(
  Future<http.Response> Function(http.Request) handler,
) {
  final api = ApiClient(
    httpClient: MockClient(handler),
    config: ApiConfig.instance,
  );
  return (api: api, repo: AuthRepository(api));
}

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  group('AuthController.bootstrap', () {
    test('tanpa token tersimpan langsung ke layar masuk', () async {
      var called = false;
      final w = _wire((_) async {
        called = true;
        return _json(_meOk, 200);
      });

      final ctrl = AuthController(w.repo);
      await ctrl.bootstrap();

      expect(ctrl.status, AuthStatus.signedOut);
      expect(called, isFalse,
          reason: 'tidak perlu memanggil server hanya untuk dapat 401');
    });

    test('token tersimpan yang masih berlaku langsung masuk', () async {
      SharedPreferences.setMockInitialValues({'auth.token': 'token-lama'});
      final w = _wire((r) async {
        expect(r.headers['Authorization'], 'Bearer token-lama');
        return _json(_meOk, 200);
      });
      await w.api.loadToken();

      final ctrl = AuthController(w.repo);
      await ctrl.bootstrap();

      expect(ctrl.status, AuthStatus.signedIn);
      expect(ctrl.user?.username, 'operator1');
      expect(ctrl.user?.id, '9505a47b-495a-4bcd-a28e-6f9599ad2d48');
    });

    test('token dicabut server -> keluar DAN token dibuang', () async {
      SharedPreferences.setMockInitialValues({'auth.token': 'token-basi'});
      final w = _wire((_) async => _json(_unauthenticated, 401));
      await w.api.loadToken();

      final ctrl = AuthController(w.repo);
      await ctrl.bootstrap();

      expect(ctrl.status, AuthStatus.signedOut);
      expect(w.api.isAuthenticated, isFalse,
          reason: 'token yang sudah ditolak tidak boleh disimpan');
    });

    test('server mati TIDAK membuang token', () async {
      // Pembedaan yang penting di lokasi: jaringan putus sesaat bukan alasan
      // memaksa operator masuk ulang — layar masuknya pun tidak akan jalan.
      SharedPreferences.setMockInitialValues({'auth.token': 'token-sah'});
      final w = _wire((_) async => throw const SocketExceptionStub());
      await w.api.loadToken();

      final ctrl = AuthController(w.repo);
      await ctrl.bootstrap();

      expect(ctrl.status, AuthStatus.signedOut);
      expect(w.api.isAuthenticated, isTrue,
          reason: 'token masih sah, hanya servernya tak terjangkau');
    });
  });

  group('AuthController.signIn', () {
    test('berhasil menyimpan token dan menandai sudah masuk', () async {
      final w = _wire((r) async {
        expect(r.url.path, endsWith('/auth/login'));
        return _json(_loginOk, 200);
      });

      final ctrl = AuthController(w.repo);
      final ok = await ctrl.signIn('operator1', 'password');

      expect(ok, isTrue);
      expect(ctrl.status, AuthStatus.signedIn);
      expect(w.api.token, '1|abcdef1234567890');
      expect(
        (await SharedPreferences.getInstance()).getString('auth.token'),
        '1|abcdef1234567890',
        reason: 'token harus bertahan setelah app ditutup',
      );
    });

    test('kredensial salah menampilkan pesan server, bukan pesan karangan',
        () async {
      final w = _wire((_) async => _json(_invalidCredentials, 401));

      final ctrl = AuthController(w.repo);
      final ok = await ctrl.signIn('operator1', 'salah');

      expect(ok, isFalse);
      expect(ctrl.status, AuthStatus.signedOut);
      expect(ctrl.error, 'Username atau password salah.');
      expect(ctrl.busy, isFalse, reason: 'tombol harus bisa ditekan lagi');
    });

    test('username dirapikan dari spasi tidak sengaja', () async {
      String? sent;
      final w = _wire((r) async {
        sent = r.body;
        return _json(_loginOk, 200);
      });

      await AuthController(w.repo).signIn('  operator1  ', 'password');
      expect(sent, contains('"username":"operator1"'));
    });
  });

  group('AuthController.signOut', () {
    test('token dibuang walau server tidak bisa dihubungi', () async {
      // Yang berbahaya saat jaringan mati bukan token yatim di server —
      // itu kedaluwarsa sendiri — melainkan tablet yang tetap terbuka.
      SharedPreferences.setMockInitialValues({'auth.token': 'token-sah'});
      final w = _wire((_) async => throw const SocketExceptionStub());
      await w.api.loadToken();

      final ctrl = AuthController(w.repo);
      await ctrl.signOut();

      expect(ctrl.status, AuthStatus.signedOut);
      expect(w.api.isAuthenticated, isFalse);
    });
  });

  group('LoginScreen', () {
    Widget wrap(AuthController ctrl) => MultiProvider(
          providers: [
            ChangeNotifierProvider.value(value: ctrl),
            ChangeNotifierProvider.value(value: ApiConfig.instance),
          ],
          child: MaterialApp(
            theme: AppTheme.light(),
            home: const LoginScreen(),
          ),
        );

    testWidgets('alamat server tampil di layar masuk', (tester) async {
      // Kalau alamatnya salah, login gagal — dan layar Pengaturan ada di
      // DALAM shell yang baru terbuka setelah login. Tanpa ini operator
      // terkunci di layar yang tidak bisa diperbaiki dari mana pun.
      final w = _wire((_) async => _json(_meOk, 200));
      await tester.pumpWidget(wrap(AuthController(w.repo)));

      expect(find.text(ApiConfig.instance.baseUrl), findsOneWidget);
      expect(find.text('Cari otomatis'), findsOneWidget);
    });

    testWidgets('pesan gagal menetap, tidak hilang sendiri', (tester) async {
      // Snackbar lenyap setelah beberapa detik; "password salah" harus tetap
      // terbaca selama operator mencoba lagi.
      final w = _wire((_) async => _json(_invalidCredentials, 401));
      final ctrl = AuthController(w.repo);
      await tester.pumpWidget(wrap(ctrl));

      await ctrl.signIn('operator1', 'salah');
      await tester.pump();

      expect(find.text('Username atau password salah.'), findsOneWidget);
    });

    testWidgets('field kosong tidak mengirim apa pun ke server',
        (tester) async {
      var hit = false;
      final w = _wire((_) async {
        hit = true;
        return _json(_loginOk, 200);
      });
      await tester.pumpWidget(wrap(AuthController(w.repo)));

      await tester.tap(find.widgetWithText(FilledButton, 'Masuk'));
      await tester.pump();

      expect(hit, isFalse);
    });

    testWidgets('password tersembunyi, dan bisa ditampilkan', (tester) async {
      final w = _wire((_) async => _json(_loginOk, 200));
      await tester.pumpWidget(wrap(AuthController(w.repo)));

      TextField field() => tester.widget<TextField>(
            find.ancestor(
              of: find.text('Password'),
              matching: find.byType(TextField),
            ),
          );

      expect(field().obscureText, isTrue);

      await tester.tap(find.byIcon(Icons.visibility_outlined));
      await tester.pump();

      expect(field().obscureText, isFalse,
          reason: 'tablet dipakai berdiri dan terburu-buru; salah ketik '
              'password yang tidak terlihat adalah kegagalan tersering');
    });
  });
}

/// Pengganti `SocketException` tanpa mengimpor `dart:io`, supaya test ini
/// tetap bisa dijalankan di target web.
class SocketExceptionStub implements Exception {
  const SocketExceptionStub();
  @override
  String toString() => 'SocketException: jaringan tidak terjangkau';
}
