import 'package:flutter/foundation.dart';

import '../../data/api/auth_repository.dart';
import '../../domain/errors/api_error.dart';
import '../../domain/models/auth.dart';

enum AuthStatus {
  /// Token tersimpan sedang diperiksa ke server. Layar awal menahan di sini.
  checking,
  signedOut,
  signedIn,
}

/// Siapa yang sedang masuk, dan apakah app boleh menampilkan isinya.
class AuthController extends ChangeNotifier {
  AuthController(this._repo, {Future<void> Function()? preflight})
      : _preflight = preflight;

  final AuthRepository _repo;

  /// Dijalankan **sebelum** token diperiksa — DEC-041.
  ///
  /// Urutannya mengikat: `/auth/me` dipanggil ke `ApiConfig.baseUrl`, jadi
  /// kalau server pindah alamat sejak terakhir dipakai, pemeriksaan token
  /// gagal karena jaringan — bukan karena tokennya tidak berlaku. Operator
  /// akan melihat layar masuk padahal yang salah alamatnya.
  final Future<void> Function()? _preflight;

  AuthStatus _status = AuthStatus.checking;
  AuthUser? _user;
  String? _error;
  bool _busy = false;

  AuthStatus get status => _status;
  AuthUser? get user => _user;
  String? get error => _error;
  bool get busy => _busy;

  /// Dijalankan sekali saat app dibuka.
  ///
  /// Tanpa token tersimpan, langsung ke layar masuk — tidak perlu memanggil
  /// server hanya untuk mendapat 401.
  Future<void> bootstrap() async {
    // Gagal mencari server BUKAN alasan menggagalkan startup: alamat lama
    // tetap dipakai, dan layar masuk punya tombol "Cari otomatis" sebagai
    // jalan keluarnya.
    try {
      await _preflight?.call();
    } catch (_) {
      // Sengaja ditelan — lihat di atas.
    }

    if (!_repo.hasToken) {
      _set(AuthStatus.signedOut);
      return;
    }

    try {
      _user = await _repo.me();
      _set(AuthStatus.signedIn);
    } on ApiError catch (e) {
      if (e.requiresReauth) {
        // Token dicabut atau akun dinonaktifkan.
        await _repo.logout();
        _set(AuthStatus.signedOut);
        return;
      }

      // Server mati atau alamatnya salah — itu BUKAN alasan membuang token.
      // Operator di lokasi tanpa jaringan sesaat tidak boleh dipaksa masuk
      // ulang; dia akan dihadang layar masuk yang juga tidak bisa jalan.
      _error = e.message;
      _set(AuthStatus.signedOut);
    }
  }

  Future<bool> signIn(String username, String password) async {
    if (_busy) return false;
    _busy = true;
    _error = null;
    notifyListeners();

    try {
      final session = await _repo.login(
        username: username.trim(),
        password: password,
      );
      _user = session.user;
      _busy = false;
      _set(AuthStatus.signedIn);
      return true;
    } on ApiError catch (e) {
      _error = e.message;
      _busy = false;
      // Status ikut ditegaskan, bukan hanya `notifyListeners()`.
      //
      // Tanpa ini, controller yang belum sempat `bootstrap()` tertinggal di
      // `checking` setelah login gagal — dan gate di `app.dart` menahan
      // spinner selamanya, bukan menampilkan layar masuk beserta pesannya.
      _set(AuthStatus.signedOut);
      return false;
    }
  }

  Future<void> signOut() async {
    await _repo.logout();
    _user = null;
    _error = null;
    _set(AuthStatus.signedOut);
  }

  /// Dipanggil saat ada permintaan yang ditolak karena token tidak berlaku.
  ///
  /// Satu pintu, supaya tidak ada layar yang menangani 401 dengan caranya
  /// sendiri dan menyisakan app setengah masuk.
  Future<void> handleAuthFailure() async {
    if (_status == AuthStatus.signedOut) return;
    await signOut();
  }

  void clearError() {
    if (_error == null) return;
    _error = null;
    notifyListeners();
  }

  void _set(AuthStatus next) {
    _status = next;
    notifyListeners();
  }
}
