<?php

namespace App\Http\Controllers\Web;

use App\Support\Audit\AuditAction;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\CredentialCheck;
use App\Support\Auth\CredentialOutcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login Admin Web — DEC-043.
 *
 * ## Kenapa ini bukan `auth:sanctum`
 *
 * Sanctum yang dipakai tablet adalah **token API**: setiap request membawa
 * `Authorization: Bearer`. Browser tidak melakukan itu — ia membawa cookie.
 * Jadi Admin Web memakai guard `web` (session), dan keduanya hidup
 * berdampingan tanpa saling mengganggu.
 *
 * Yang DIBAGI dengan API adalah pemeriksaan kredensialnya
 * ([CredentialCheck]): aturan timing-safe, pesan yang tidak membocorkan
 * username, pemblokiran akun nonaktif, dan audit. Yang berbeda hanya bentuk
 * jawabannya — JSON di sana, kembali ke form di sini.
 */
class LoginController
{
    /**
     * Sengaja berbeda dari `throttle:login` milik API (5/menit/IP).
     *
     * Middleware itu menjawab dengan halaman 429 telanjang. Di form login,
     * yang dibutuhkan adalah pesan di tempat yang sama dengan error lain,
     * menyebut sisa waktunya. Hitungannya juga per **username + IP**, bukan
     * IP saja: beberapa admin di satu jaringan rental tidak boleh saling
     * mengunci.
     */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(
        private readonly CredentialCheck $credentials,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * User yang SUDAH login tidak pernah sampai ke sini — middleware `guest`
     * sudah melemparnya ke dashboard (diarahkan di `AuthServiceProvider`).
     * Memeriksanya lagi di sini hanya akan jadi cabang yang tidak pernah
     * dijalankan, dan cabang semacam itu membusuk tanpa ketahuan.
     */
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ], [], [
            'username' => 'username',
            'password' => 'password',
        ]);

        $this->ensureNotRateLimited($request, $data['username']);

        $result = $this->credentials->attempt($data['username'], $data['password']);

        if ($result->outcome === CredentialOutcome::INVALID) {
            RateLimiter::hit($this->throttleKey($request, $data['username']), self::DECAY_SECONDS);

            // Satu pesan untuk username salah maupun password salah.
            throw ValidationException::withMessages([
                'username' => 'Username atau password salah.',
            ]);
        }

        if ($result->outcome === CredentialOutcome::INACTIVE) {
            throw ValidationException::withMessages([
                'username' => 'Akun ini dinonaktifkan. Hubungi admin.',
            ]);
        }

        $user = $result->user;

        /*
         * Operator TIDAK masuk Admin Web (DEC-043).
         *
         * Diperiksa SEBELUM `Auth::login`, bukan oleh middleware sesudahnya:
         * sesi yang dibuat lalu langsung ditolak tetap meninggalkan cookie
         * login yang sah, dan halaman lain yang lupa dipasangi middleware
         * akan menerimanya.
         */
        if (! $user->role->isAdminLevel()) {
            $this->audit->forUser($user, AuditAction::LOGIN_BLOCKED_ROLE, [
                'after' => ['surface' => 'web', 'role' => $user->role->value],
            ]);

            throw ValidationException::withMessages([
                'username' => 'Admin Web hanya untuk Owner dan Admin. '
                    .'Operator memakai aplikasi tablet.',
            ]);
        }

        Auth::login($user);

        /*
         * Wajib. Tanpa regenerate, ID session sebelum login tetap dipakai
         * sesudahnya — penyerang yang bisa menanam ID itu lebih dulu akan
         * ikut masuk bersama korban (session fixation).
         */
        $request->session()->regenerate();

        RateLimiter::clear($this->throttleKey($request, $data['username']));

        $this->audit->forUser($user, AuditAction::LOGIN_SUCCESS, [
            'after' => ['surface' => 'web'],
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();

        /*
         * Keduanya perlu: `invalidate` membuang isi session, `regenerateToken`
         * memberi token CSRF baru. Tanpa yang kedua, form login berikutnya
         * memakai token lama dan ditolak 419 — logout yang "berhasil" tapi
         * tidak bisa login lagi.
         */
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $this->audit->forUser($user, AuditAction::LOGOUT, [
            'after' => ['surface' => 'web'],
        ]);

        return redirect()->route('login');
    }

    private function ensureNotRateLimited(Request $request, string $username): void
    {
        $key = $this->throttleKey($request, $username);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'username' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    private function throttleKey(Request $request, string $username): string
    {
        return 'web-login:'.mb_strtolower($username).'|'.$request->ip();
    }
}
