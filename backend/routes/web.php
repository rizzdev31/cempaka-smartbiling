<?php

use App\Support\Web\DashboardSampleData;
use Illuminate\Support\Facades\Route;

/*
 * Admin Web — DEC-042.
 *
 * ⚠️ BELUM ADA AUTH. Halaman ini terbuka untuk siapa pun yang bisa membuka
 * alamatnya, dan isinya `DashboardSampleData` — bukan data nyata.
 *
 * Itu aman hanya selama keduanya benar: tidak ada data asli yang bocor
 * karena tidak ada data asli yang dibaca. Begitu query nyatanya masuk,
 * login berbasis session WAJIB lebih dulu — Sanctum yang dipakai tablet
 * adalah token API, bukan session web, jadi ini pekerjaan tersendiri.
 */
Route::view('/', 'welcome');

Route::get('/admin', fn () => view('admin.dashboard', DashboardSampleData::all()))
    ->name('admin.dashboard');
