<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SessionExtendController;
use App\Http\Controllers\Api\V1\SessionPaymentController;
use Illuminate\Support\Facades\Route;

/*
 * Base path `/api/v1` (API.md §1) — prefix diatur di bootstrap/app.php.
 *
 * Cakupan per tahap ada di API.md §14. Endpoint yang belum ditulis
 * sengaja tidak didaftarkan, supaya 404 jujur dan bukan 500.
 */

// Tanpa auth: dipakai tablet & TV untuk tes jaringan sebelum punya token.
Route::get('/health', HealthController::class);

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');   // 5/menit/IP (API.md §13)

/*
 * `active.user` dipasang bersama `auth:sanctum`, bukan per route: akun yang
 * dinonaktifkan harus langsung kehilangan SEMUA akses, dan route yang lupa
 * memasangnya akan jadi celah.
 */
Route::middleware(['auth:sanctum', 'active.user', 'throttle:api'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    /*
     * Session — API.md §7.
     *
     * `idempotency` hanya pada POST yang MEMBUAT data (API.md §3). GET tidak
     * memakainya: memutar ulang response lama untuk pembacaan justru
     * menyembunyikan perubahan yang baru terjadi.
     */
    Route::get('/sessions', [SessionController::class, 'index'])
        ->middleware('can:session.read');

    Route::get('/sessions/{session}', [SessionController::class, 'show'])
        ->middleware('can:session.read');

    Route::post('/sessions', [SessionController::class, 'store'])
        ->middleware(['can:session.create', 'idempotency']);

    Route::post('/sessions/{session}/payments', [SessionPaymentController::class, 'store'])
        ->middleware(['can:payment.confirm', 'idempotency']);

    Route::post('/sessions/{session}/extend', [SessionExtendController::class, 'store'])
        ->middleware(['can:session.extend', 'idempotency']);
});
