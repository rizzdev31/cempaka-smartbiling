<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token Sanctum.
 *
 * `uuidMorphs`, bukan `morphs` bawaan: `morphs` membuat `tokenable_id`
 * bertipe integer, sedangkan primary key `users` adalah UUID (API.md §1).
 * MySQL menolak insert-nya dengan "Incorrect integer value", jadi login
 * gagal 500 di baris pembuatan token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
