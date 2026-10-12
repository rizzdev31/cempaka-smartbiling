<?php

use App\Http\Controllers\Web\AdminActionController;
use App\Http\Controllers\Web\AdminController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\SessionActionController;
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
Route::middleware(['auth', 'admin.web'])
    ->prefix('admin')
    ->name('admin.')
    ->controller(AdminController::class)
    ->group(function () {
        Route::get('/', 'dashboard')->name('dashboard');
        Route::get('/sesi', 'sessions')->name('sessions');
        Route::get('/station', 'stations')->name('stations');
        Route::get('/fnb', 'fnb')->name('fnb');
        Route::get('/customer', 'customers')->name('customers');
        Route::get('/shift', 'shifts')->name('shifts');
        Route::get('/audit', 'audit')->name('audit');

        /*
         * Harga butuh permission tersendiri (DEC-020). Halamannya boleh
         * DILIHAT admin — mengetahui tarif adalah bagian dari pekerjaannya —
         * tapi tombol ubahnya hanya untuk owner, dan penegaknya ada di
         * endpoint perubahan, bukan di tampilan.
         */
        Route::get('/harga', 'pricing')->name('pricing');

        Route::get('/pembayaran', 'payments')->name('payments');

        /*
         * Detail sesi. Diletakkan SETELAH `/sesi` supaya `/sesi` tidak
         * tertangkap sebagai `{session}`.
         */
        Route::get('/sesi/{session}', 'session')->name('session');
    });

/*
 * Aksi ubah — DEC-044.
 *
 * Terpisah dari grup baca supaya terlihat: halaman admin hanya membaca,
 * SETIAP perubahan lewat sini, dan setiap method di sini memanggil Service
 * yang sama dengan API.
 *
 * Izinnya DISALIN PERSIS dari `routes/api.php`, bukan ditata ulang — dua
 * pintu ke service yang sama harus dijaga sama ketatnya.
 *
 * Perbedaan penting antara keduanya, dan mudah salah:
 *
 * - **Paket**: `can:pricing.manage` di ROUTE. Service tidak memeriksanya
 *   sama sekali, jadi route adalah satu-satunya gerbang. Versi pertama
 *   grup ini melewatkannya, dan admin bisa mengubah seluruh tarif rental —
 *   ditemukan test, bukan saat dibaca.
 * - **Produk F&B**: `can:fnb.manage` di route, lalu SERVICE menolak
 *   perubahan `price` dari non-owner. Gerbangnya per field, karena
 *   "Mie Goreng habis" kejadian harian yang harus bisa ditangani admin.
 */
Route::middleware(['auth', 'admin.web'])
    ->prefix('admin')
    ->name('admin.')
    ->controller(AdminActionController::class)
    ->group(function () {
        Route::post('/paket', 'storePackage')
            ->middleware('can:pricing.manage')->name('packages.store');

        Route::patch('/paket/{package}', 'updatePackage')
            ->middleware('can:pricing.manage')->name('packages.update');

        Route::patch('/fnb/produk/{product}', 'updateFnbProduct')
            ->middleware('can:fnb.manage')->name('fnb-products.update');

        // Menambah menu = menetapkan harga → owner (DEC-020), sama dengan
        // `POST /api/v1/fnb/products`.
        Route::post('/fnb/produk', 'storeFnbProduct')
            ->middleware('can:pricing.manage')->name('fnb-products.store');

        Route::post('/customer', 'storeCustomer')
            ->middleware('can:customer.create')->name('customers.store');

        Route::post('/customer/{customer}/member', 'storeMembership')
            ->middleware('can:customer.create')->name('customers.membership');

        Route::post('/fnb/pesanan/{order}/status', 'updateFnbStatus')
            ->middleware('can:fnb.manage')->name('fnb-orders.status');

        Route::post('/shift/{shift}/tutup', 'closeShift')
            ->middleware('can:shift.manage')->name('shifts.close');

        /*
         * Pengeluaran kasir — DEC-046. Izin sama dengan
         * `POST /api/v1/shifts/{shift}/expenses`: `shift.manage` di route,
         * lalu Service menolak kalau shiftnya sudah ditutup atau
         * pemanggilnya bukan pemegangnya.
         */
        Route::post('/shift/{shift}/pengeluaran', 'recordExpense')
            ->middleware('can:shift.manage')->name('shifts.expense');
    });

/*
 * Operasi sesi dari web — DEC-045.
 *
 * DEC-044 sempat menetapkan Laravel TIDAK melakukan operasi sesi. User
 * membalikkannya: Laravel adalah owner dari semua fitur dan harus punya
 * setiap akses, walaupun bukan dia yang berhadapan dengan customer.
 *
 * Izinnya DISALIN PERSIS dari `routes/api.php`. Sekarang ada dua pintu ke
 * operasi yang memegang uang, dan dua pintu dengan gerbang berbeda sama
 * saja dengan satu pintu tanpa gerbang.
 */
Route::middleware(['auth', 'admin.web'])
    ->prefix('admin')
    ->name('admin.')
    ->controller(SessionActionController::class)
    ->group(function () {
        Route::post('/sesi', 'store')
            ->middleware('can:session.create')->name('sessions.store');

        Route::post('/sesi/{session}/bayar', 'pay')
            ->middleware('can:payment.confirm')->name('sessions.pay');

        Route::post('/sesi/{session}/extend', 'extend')
            ->middleware('can:session.extend')->name('sessions.extend');

        Route::post('/sesi/{session}/pindah', 'swap')
            ->middleware('can:session.swap')->name('sessions.swap');

        Route::post('/sesi/{session}/checkout', 'checkout')
            ->middleware('can:session.checkout')->name('sessions.checkout');

        Route::post('/sesi/{session}/batal', 'cancel')
            ->middleware('can:session.cancel')->name('sessions.cancel');

        Route::post('/sesi/{session}/fnb', 'storeFnbOrder')
            ->middleware('can:fnb.manage')->name('sessions.fnb');

        Route::post('/shift/buka', 'openShift')
            ->middleware('can:shift.manage')->name('shifts.open');
    });
