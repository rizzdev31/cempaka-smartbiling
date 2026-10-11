<?php

use App\Http\Controllers\Web\LoginController;
use App\Support\Web\DashboardData;
use Illuminate\Support\Facades\Route;

/*
 * Admin Web — DEC-042 (tampilan) & DEC-043 (login).
 *
 * Guard `web` (session), BUKAN `auth:sanctum`. Sanctum adalah token API
 * untuk tablet; browser membawa cookie, bukan header Authorization.
 */

Route::redirect('/', '/admin');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
 * `admin.web` memeriksa ulang role dan status aktif di SETIAP request.
 * Login sudah memeriksanya sekali, tapi session bertahan berhari-hari —
 * akun yang diturunkan atau dinonaktifkan di tengah jalan harus langsung
 * kehilangan akses, bukan menunggu dia logout sendiri.
 */
Route::middleware(['auth', 'admin.web'])->group(function () {
    Route::get('/admin', fn () => view('admin.dashboard', DashboardData::all()))
        ->name('admin.dashboard');
});
