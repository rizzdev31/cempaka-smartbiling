<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Users + session web.
 *
 * Migration bawaan Laravel diubah di tempat (belum pernah ada data):
 *
 * 1. `email` -> `username`. API.md §4 login memakai `username`; rental tidak
 *    punya email per operator. `password_reset_tokens` dihapus karena reset
 *    password lewat email tidak mungkin tanpa email.
 * 2. `role` tiga nilai (DEC-020): hanya OWNER yang boleh mengubah tarif.
 * 3. Tabel session web dinamai `web_sessions`, BUKAN `sessions` — nama
 *    `sessions` dipakai session billing (PRD §22). Dua-duanya bernama
 *    `sessions` akan bertabrakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('username', 64)->unique();
            $table->string('password');

            // OWNER | ADMIN | OPERATOR — lihat App\Enums\UserRole.
            $table->string('role', 16);

            // PRD §6: akun dinonaktifkan -> 403 USER_INACTIVE, bukan dihapus,
            // supaya histori transaksi tetap punya pemilik.
            $table->boolean('is_active')->default(true);

            $table->rememberToken();
            $table->timestamps();

            $table->index('role');
        });

        Schema::create('web_sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('web_sessions');
    }
};
