<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo member — DEC-024, DEC-026.
 *
 * Disimpan sebagai **buku besar (ledger)**, bukan satu kolom `balance` di
 * `customers`. Alasannya: PRD §24 mewajibkan jejak audit untuk semua yang
 * menyangkut uang. Satu kolom saldo hanya menyimpan hasil akhir — kalau
 * angkanya dipertanyakan customer, tidak ada yang bisa dijelaskan. Dengan
 * ledger, setiap penambahan dan pemakaian punya barisnya sendiri beserta
 * sesi asalnya.
 *
 * Saldo berjalan = `SUM(amount)`. Kolomnya **signed**: EARNED positif,
 * USED negatif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_credits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->cascadeOnDelete();

            $table->string('type', 16);   // CustomerCreditType

            /*
             * Integer rupiah (DEC-005), SIGNED. Saldo disimpan dalam rupiah
             * dan bukan menit: DEC-019 membuat tarif berbeda per tipe konsol,
             * jadi "30 menit" tidak punya nilai tetap. DEC-025 mengubahnya
             * jadi menit saat dipakai, memakai tarif konsol yang baru.
             */
            $table->integer('amount');

            /*
             * Sesi asal (EARNED) atau sesi tempat saldo dipakai (USED).
             * nullOnDelete, bukan cascade: menghapus sesi tidak boleh ikut
             * menghapus saldo yang sudah menjadi hak customer.
             */
            $table->foreignUuid('session_id')->nullable()->constrained('sessions')->nullOnDelete();

            $table->string('note')->nullable();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Menghitung saldo = menjumlahkan baris milik satu customer.
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credits');
    }
};
