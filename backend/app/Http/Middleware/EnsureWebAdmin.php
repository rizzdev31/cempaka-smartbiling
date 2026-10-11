<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga halaman Admin Web tetap milik Owner dan Admin — DEC-043.
 *
 * ## Kenapa ini ada padahal login sudah memeriksanya
 *
 * Login memeriksa role **saat masuk**. Session bertahan berhari-hari. Dalam
 * rentang itu akun bisa dinonaktifkan atau diturunkan jadi operator, dan
 * tanpa pemeriksaan di setiap request, perubahan itu tidak berpengaruh apa
 * pun sampai orangnya kebetulan logout sendiri.
 *
 * Ini bukan pengulangan yang sia-sia: keduanya menjawab pertanyaan berbeda —
 * "boleh masuk?" dan "masih boleh?".
 *
 * ## Kenapa bukan `EnsureUserIsActive`
 *
 * Middleware itu melempar `ApiException`, yang hanya dirender sebagai JSON
 * untuk `api/*`. Di browser hasilnya halaman error 500 yang tidak
 * menjelaskan apa pun. Di sini user dikeluarkan dan dikembalikan ke form
 * login dengan alasannya.
 */
class EnsureWebAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        $alasan = match (true) {
            ! $user->is_active => 'Akun ini dinonaktifkan. Hubungi admin.',
            ! $user->role->isAdminLevel() => 'Akses Anda ke Admin Web dicabut.',
            default => null,
        };

        if ($alasan === null) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['username' => $alasan]);
    }
}
