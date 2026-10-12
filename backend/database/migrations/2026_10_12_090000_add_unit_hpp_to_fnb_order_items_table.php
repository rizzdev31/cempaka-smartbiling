<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bekukan HPP saat order dibuat — DEC-050.
 *
 * ## Kenapa ini perlu
 *
 * `name` dan `unit_price` sudah dibekukan ke baris order sejak awal: owner
 * yang menaikkan harga menu tidak boleh mengubah tagihan order yang sudah
 * jalan. **HPP tidak ikut dibekukan**, dan akibatnya baru terasa saat
 * laporan laba dibuat:
 *
 * Es teh dijual 5.000 dengan HPP 1.500 di bulan Januari. Februari harga
 * gula naik dan HPP-nya diubah jadi 2.500. Laporan laba bulan Januari —
 * yang sudah ditutup, sudah dicetak, sudah dibahas — ikut berubah
 * sendirinya, karena ia menghitung ulang dari angka hari ini.
 *
 * Tidak ada error. Angkanya tetap masuk akal. Hanya tidak sama dengan yang
 * dibaca bulan lalu, dan tidak ada yang bisa menjelaskan kenapa.
 *
 * ## Nullable, dan itu disengaja
 *
 * Baris yang sudah ada tidak bisa diisi mundur: HPP saat order itu dibuat
 * memang tidak pernah disimpan. Mengisinya dengan HPP hari ini akan
 * terlihat seperti data yang benar padahal tebakan.
 *
 * NULL berarti **"tidak diketahui"**, dan halaman Laporan menyebutkan
 * berapa banyak baris yang begitu alih-alih diam-diam menganggapnya nol.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fnb_order_items', function (Blueprint $table) {
            $table->unsignedInteger('unit_hpp')->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('fnb_order_items', function (Blueprint $table) {
            $table->dropColumn('unit_hpp');
        });
    }
};
