<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyimpanan idempotency (API.md §3).
 *
 * Pertahanan utama terhadap R06 (duplicate payment) dan operator yang
 * menekan tombol dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();

            // Scope memisahkan key antar pemakai: user uuid, device id, atau 'anon'.
            // Tanpa ini, key milik satu operator bisa menabrak milik operator lain.
            $table->string('scope', 64);
            $table->uuid('key');

            // Hash dari method + path + body. Key sama tapi body beda = 409 (§3 aturan 4).
            $table->char('request_hash', 64);

            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');

            $table->timestamp('created_at');

            // Retensi minimal 24 jam (§3 aturan 2). Baris lewat waktu dibersihkan scheduler.
            $table->timestamp('expires_at')->index();

            $table->unique(['scope', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
