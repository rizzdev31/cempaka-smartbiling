<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengeluaran kasir dari laci — OD-029, diputuskan jadi DEC-046.
 *
 * ## Lubang yang ditutup
 *
 * Rekonsiliasi laci seharusnya:
 *
 *     Kas seharusnya = Modal awal + Tunai masuk − PENGELUARAN
 *
 * Suku terakhirnya tidak pernah dibangun. Akibatnya kasir yang mengambil
 * Rp 20.000 dari laci untuk beli galon membuat sistem melaporkan kas
 * MINUS Rp 20.000 — dan kasir yang jujur terlihat seperti mencuri.
 *
 * Fitur anti-bocor yang justru jadi sumber tuduhan palsu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();

            /*
             * Menempel ke SHIFT, bukan ke tanggal.
             *
             * Yang dibandingkan dengan uang fisik adalah isi laci pada satu
             * shift. Pengeluaran yang hanya punya tanggal tidak bisa
             * dipertanggungjawabkan ke siapa pun saat shiftnya berganti di
             * tengah hari.
             */
            $table->foreignUuid('shift_id')->constrained()->cascadeOnDelete();

            // Integer rupiah, tanpa desimal (CLAUDE.md §4). Positif —
            // arahnya sudah ditentukan oleh tabel ini, bukan oleh tandanya.
            $table->unsignedInteger('amount');

            // Teks bebas, bukan enum. Daftar kategori belanja rental tidak
            // bisa ditebak dari sini, dan enum yang salah tebak memaksa
            // kasir memilih "Lainnya" untuk separuh pengeluarannya.
            $table->string('category', 32);

            // WAJIB. Pengeluaran tanpa keterangan persis yang tidak bisa
            // dijelaskan saat laporan diperiksa.
            $table->string('note', 255);

            $table->foreignUuid('actor_id')->constrained('users')->restrictOnDelete();

            $table->timestamps();

            // Laporan selalu "pengeluaran shift ini", tidak pernah
            // "pengeluaran satu orang sepanjang masa".
            $table->index(['shift_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_expenses');
    }
};
