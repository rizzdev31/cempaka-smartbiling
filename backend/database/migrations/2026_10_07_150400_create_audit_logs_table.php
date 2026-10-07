<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit — PRD §24. Wajib untuk login, payment, extend, swap, discount,
 * adjustment, dan perubahan master data (termasuk perubahan tarif, DEC-019/020).
 *
 * Aktif sejak Tahap 0: audit yang dipasang belakangan tidak bisa menjelaskan
 * kejadian yang sudah lewat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // USER | DEVICE | SYSTEM — scheduler juga bisa jadi aktor.
            $table->string('actor_type', 16);
            $table->uuid('actor_id')->nullable();

            /*
             * Nama aktor dibekukan. Tanpa ini, mengganti nama user akan
             * mengubah isi audit lama — audit yang bisa berubah tidak ada
             * gunanya sebagai bukti.
             */
            $table->string('actor_name')->nullable();
            $table->string('actor_role', 16)->nullable();

            $table->string('action', 64);           // mis. "payment.confirm"
            $table->string('subject_type', 64)->nullable();
            $table->uuid('subject_id')->nullable();

            // PRD §19: audit log menampilkan before/after.
            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // Tidak ada updated_at: baris audit tidak boleh diubah.
            $table->timestamp('created_at');

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
