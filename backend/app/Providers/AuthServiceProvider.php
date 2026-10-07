<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\User;
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
        foreach (Permission::cases() as $permission) {
            Gate::define(
                $permission->value,
                fn (User $user) => $user->is_active && $user->role->hasPermission($permission),
            );
        }

        // 5 / menit / IP (API.md §13). Membatasi per IP, bukan per username,
        // supaya penyerang tidak bisa memutar username untuk lolos.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // 120 / menit / user untuk endpoint ber-auth.
        RateLimiter::for(
            'api',
            fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()),
        );
    }
}
