<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\PackageController;
use App\Http\Controllers\Api\V1\FnbController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\SessionCancelController;
use App\Http\Controllers\Api\V1\SessionCheckoutController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\SessionExtendController;
use App\Http\Controllers\Api\V1\SessionPaymentController;
use App\Http\Controllers\Api\V1\SessionSwapController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\StationController;
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

    Route::post('/sessions/{session}/swap', [SessionSwapController::class, 'store'])
        ->middleware(['can:session.swap', 'idempotency']);

    Route::post('/sessions/{session}/checkout', [SessionCheckoutController::class, 'store'])
        ->middleware(['can:session.checkout', 'idempotency']);

    /*
     * F&B — API.md §8.
     *
     * Membaca menu dan antrian cukup `fnb.read`; membuat order dan memindahkan
     * statusnya butuh `fnb.manage`. Dipisah karena layar antrian dapur nanti
     * bisa dibuka perangkat yang tidak boleh mengubah apa pun.
     */
    Route::get('/fnb/products', [FnbController::class, 'products'])
        ->middleware('can:fnb.read');

    Route::get('/fnb/orders', [FnbController::class, 'orders'])
        ->middleware('can:fnb.read');

    Route::post('/sessions/{session}/fnb/orders', [FnbController::class, 'store'])
        ->middleware(['can:fnb.manage', 'idempotency']);

    Route::post('/fnb/orders/{order}/status', [FnbController::class, 'updateStatus'])
        ->middleware('can:fnb.manage');

    /*
     * Shift kasir — API.md §10.
     *
     * Tanpa shift terbuka, `shift_id` pada setiap payment tetap NULL dan uang
     * masuk tidak bisa dihubungkan ke siapa yang jaga.
     */
    Route::get('/shifts/current', [ShiftController::class, 'current'])
        ->middleware('can:shift.manage');

    // Riwayat shift tertutup \u2014 dipakai layar serah-terima.
    Route::get('/shifts', [ShiftController::class, 'index'])
        ->middleware('can:shift.manage');

    Route::post('/shifts/open', [ShiftController::class, 'open'])
        ->middleware(['can:shift.manage', 'idempotency']);

    Route::post('/shifts/{shift}/close', [ShiftController::class, 'close'])
        ->middleware(['can:shift.manage', 'idempotency']);

    /*
     * Customer & membership — API.md §6, DEC-027.
     *
     * `customer.create` sekarang dipegang operator juga: DEC-024 meminta
     * operator menawarkan membership saat checkout, dan sebelum DEC-027 dia
     * tidak punya tombolnya.
     */
    // Layar Status TV di Flutter. Read-only untuk operator.
    Route::get('/devices', [DeviceController::class, 'index'])
        ->middleware('can:device.read');

    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware('can:customer.read');

    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware(['can:customer.create', 'idempotency']);

    Route::post('/customers/{customer}/membership', [CustomerController::class, 'storeMembership'])
        ->middleware(['can:customer.create', 'idempotency']);

    /*
     * Master data read-only — API.md §6.
     *
     * `GET /stations` adalah sumber data dashboard: station, sesi aktifnya,
     * dan status TV dalam satu panggilan. `GET /packages` menerima filter
     * `station_id` supaya layar Start Session hanya menampilkan paket yang
     * sah untuk tipe konsol station itu (DEC-019).
     */
    Route::get('/stations', [StationController::class, 'index'])
        ->middleware('can:station.read');

    Route::get('/packages', [PackageController::class, 'index'])
        ->middleware('can:package.read');

    /*
     * Mengubah tarif — owner saja (DEC-020). Setiap perubahan memicu
     * `master.updated` supaya tablet lain memuat ulang daftarnya sendiri;
     * tanpa itu, owner yang menaikkan harga di satu tablet tidak punya cara
     * memberi tahu yang lain.
     *
     * Harga sesi yang SEDANG BERJALAN tidak ikut berubah — harganya dibekukan
     * ke baris sesi saat dibuat.
     */
    Route::post('/packages', [PackageController::class, 'store'])
        ->middleware(['can:pricing.manage', 'idempotency']);

    Route::patch('/packages/{package}', [PackageController::class, 'update'])
        ->middleware('can:pricing.manage');

    /*
     * Menu: `can:fnb.manage` di route, lalu service menolak perubahan HARGA
     * kalau pemanggilnya bukan owner. Dipisah begitu supaya operator bisa
     * menandai menu habis sendiri tanpa menunggu owner.
     */
    Route::patch('/fnb/products/{product}', [FnbController::class, 'updateProduct'])
        ->middleware('can:fnb.manage');

    Route::post('/sessions/{session}/cancel', [SessionCancelController::class, 'store'])
        ->middleware(['can:session.cancel', 'idempotency']);
});

/*
 * TV Agent — API.md §9.
 *
 * Pendaftaran TANPA auth: TV belum punya token saat memanggilnya. Yang
 * menjaganya kode pendaftaran milik station, plus rate limit — kalau tidak,
 * kode enam huruf bisa ditebak dengan mencoba terus-menerus.
 */
Route::post('/devices/register', [DeviceController::class, 'register'])
    ->middleware(['throttle:device-register', 'idempotency']);

/*
 * Guard `device` membaca header X-Device-Token. TV tidak punya akses apa pun
 * di luar dua endpoint ini (PRD §6) — tidak ada session, payment, maupun
 * master data.
 */
Route::middleware('auth:device')->group(function () {
    Route::post('/devices/heartbeat', [DeviceController::class, 'heartbeat'])
        ->middleware('throttle:device-heartbeat');

    Route::get('/devices/me/state', [DeviceController::class, 'meState']);
});
