<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

/**
 * Gate per permission + rate limiter (API.md §13).
 *
 * Satu Gate didefinisikan untuk setiap nilai Permission, jadi route bisa
 * memakai `->middleware('can:pricing.manage')`. Menambah permission baru
 * di enum otomatis membuat Gate-nya — tidak ada daftar kedua yang bisa
 * ketinggalan.
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * Guard `device` — API.md §9. Dibuat lewat viaRequest dan bukan guard
         * penuh karena yang dibutuhkan cuma satu hal: menukar header
         * `X-Device-Token` jadi Device.
         *
         * Dengan jadi guard sungguhan, `auth:device` dan otorisasi channel
         * `private-station.*` memakai mesin Laravel yang sama seperti user —
         * tidak ada jalur autentikasi buatan sendiri yang harus diingat dan
         * mudah terlewat saat menambah endpoint.
         */
        Auth::viaRequest('device-token', fn (Request $request) => Device::fromToken(
            $request->header('X-Device-Token'),
        ));

        foreach (Permission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (User $user) => $user->is_active && $user->role->hasPermission($permission),
            );
        }

        // 5 / menit / IP (API.md §13). Membatasi per IP, bukan per username,
        // supaya penyerang tidak bisa memutar username untuk lolos.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        /*
         * Pendaftaran TV: 5 / menit / IP. Kode pendaftarannya cuma enam
         * huruf — tanpa batas ini, kode bisa ditebak dengan mencoba terus.
         */
        RateLimiter::for("device-register", fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        /*
         * Heartbeat: 2 / menit / device (API.md §13). TV mengirimnya tiap 30
         * detik; batas ini memberi ruang satu percobaan ulang tanpa membuka
         * jalan untuk membanjiri server.
         */
        RateLimiter::for(
            "device-heartbeat",
            fn (Request $request) => Limit::perMinute(2)->by($request->user()?->getAuthIdentifier() ?: $request->ip()),
        );

        // 120 / menit / user untuk endpoint ber-auth.
        RateLimiter::for(
            'api',
            fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()),
        );
    }
}
