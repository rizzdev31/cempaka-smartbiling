<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
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
});
