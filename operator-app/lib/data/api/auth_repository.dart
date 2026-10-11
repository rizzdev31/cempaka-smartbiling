import '../../domain/models/auth.dart';
import 'api_client.dart';

/// Autentikasi operator — API.md §4.
///
/// Terpisah dari `ApiBillingRepository` karena hidupnya berbeda: login
/// dipanggil **sebelum** ada token, sementara seluruh repository billing
/// mensyaratkan token sudah ada. Menyatukannya membuat urutan itu kabur.
class AuthRepository {
  AuthRepository(this._api);

  final ApiClient _api;

  bool get hasToken => _api.isAuthenticated;

  /// Menukar kredensial dengan token, lalu **menyimpannya** di [ApiClient].
  ///
  /// Penyimpanan dilakukan di sini, bukan di pemanggil: kalau token sudah
  /// diterima tapi pemanggil lupa menyimpannya, aplikasi tampak berhasil
  /// masuk lalu menolak setiap permintaan berikutnya — kegagalan yang
  /// menyesatkan dan sulit dilacak.
  Future<AuthSession> login({
    required String username,
    required String password,
  }) async {
    final data = await _api.post(
      '/auth/login',
      body: {'username': username, 'password': password},
    ) as Map<String, dynamic>;

    final token = data['token'] as String;
    await _api.saveToken(token);

    return AuthSession(
      token: token,
      user: AuthUser.fromJson(data['user'] as Map<String, dynamic>),
    );
  }

  /// Memastikan token yang tersimpan masih berlaku.
  ///
  /// Dipanggil saat app dibuka ulang. Token Sanctum bisa dicabut dari sisi
  /// server — tanpa pemeriksaan ini, operator melihat dashboard kosong dengan
  /// error di setiap panel alih-alih diminta masuk kembali.
  Future<AuthUser> me() async {
    final data = await _api.get('/auth/me') as Map<String, dynamic>;
    return AuthUser.fromJson(data['user'] as Map<String, dynamic>);
  }

  /// Mencabut token yang sedang dipakai saja.
  ///
  /// Token lokal dihapus **apa pun hasil panggilan server**: kalau jaringan
  /// mati saat operator menekan keluar, yang berbahaya adalah tablet tetap
  /// terbuka — bukan token yatim di server, yang akan kedaluwarsa sendiri.
  Future<void> logout() async {
    try {
      await _api.post('/auth/logout');
    } catch (_) {
      // Sengaja ditelan. Lihat alasan di atas.
    } finally {
      await _api.clearToken();
    }
  }
}
