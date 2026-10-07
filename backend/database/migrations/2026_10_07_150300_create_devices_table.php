<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TV Agent — PRD §10, API.md §9.
 *
 * Tabelnya dibuat di Tahap 0 karena kontraknya sudah fix; endpoint-nya
 * baru dipakai Tahap 2. Alur `enrollment_code` belum dibuat — itu wewenang
 * Admin (Tahap 3B) dan belum ada di PRD §22.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // android_id dari TV. Satu baris per perangkat fisik.
            $table->string('device_uid', 64)->unique();

            /*
             * PRD §10: satu station <-> satu device aktif, dan pemetaan boleh
             * berubah. Nullable supaya device bisa terdaftar sebelum/sesudah
             * dipetakan — `GET /devices` mengirim `station: null` untuk itu.
             */
            $table->foreignUuid('station_id')->nullable()->unique()
                ->constrained()->nullOnDelete();

            /*
             * HASH token, bukan tokennya. Kalau DB terbaca, token asli tidak
             * ikut terbaca. PRD §10/§24: token unik dan dapat dicabut —
             * pencabutan = isi `revoked_at`, bukan hapus baris, supaya
             * histori heartbeat tetap punya pemilik.
             */
            $table->char('token_hash', 64)->nullable()->unique();
            $table->timestamp('revoked_at')->nullable();

            $table->string('model')->nullable();
            $table->string('os_version', 64)->nullable();
            $table->string('app_version', 32)->nullable();

            // `status` ONLINE/OFFLINE TIDAK disimpan — dihitung server dari
            // last_seen_at terhadap offline_threshold_seconds (API.md §9).
            $table->timestamp('last_seen_at')->nullable();
            $table->unsignedInteger('uptime_seconds')->nullable();

            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
