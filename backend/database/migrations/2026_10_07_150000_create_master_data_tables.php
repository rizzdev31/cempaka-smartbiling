<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master data: tipe konsol, station, paket, customer, membership.
 *
 * Kenapa enum disimpan sebagai `string`, bukan tipe ENUM MySQL:
 * menambah satu nilai pada ENUM MySQL berarti ALTER TABLE pada tabel yang
 * sudah berisi data transaksi. Nilainya ditegakkan PHP enum di `app/Enums`
 * dan oleh validasi endpoint.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * DEC-019 — tarif berbeda per tipe konsol. Tabel ini yang membuat
         * "PS5 VIP" bisa punya harga sendiri, dan bisa diatur dari aplikasi
         * kasir oleh owner (DEC-020).
         */
        Schema::create('station_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 64)->unique();   // mis. "PS5 VIP", "PS4 Slim"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 16)->unique();   // ST01..ST06
            $table->string('name');

            // Nullable: station boleh ada sebelum tipe konsolnya ditentukan.
            // `GET /stations` mengirimnya sebagai `console_type` (string) agar
            // tetap non-breaking untuk Flutter (CHANGELOG DRAFT 4/5).
            $table->foreignUuid('station_type_id')->nullable()
                ->constrained('station_types')->nullOnDelete();

            $table->string('status', 16)->default('ACTIVE'); // StationStatus
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('status');
        });

        /*
         * Paket milik satu tipe konsol (DEC-019). `price` dan
         * `duration_minutes` adalah dasar `hourly_rate = price / (duration/60)`
         * yang dipakai rumus extend DEC-007.
         */
        Schema::create('packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('station_type_id')->constrained('station_types')->cascadeOnDelete();
            $table->string('name', 64);                        // "1 Jam"
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('price');                  // integer rupiah (DEC-005)
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // Satu tipe konsol tidak boleh punya dua paket bernama sama.
            $table->unique(['station_type_id', 'name']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('phone', 32)->nullable()->unique();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        /*
         * Satu customer maksimal satu membership. `tier` teks bebas karena
         * tiap rental punya penamaan sendiri — alasan yang sama dengan
         * `station_types.name` (CHANGELOG DRAFT 4).
         */
        Schema::create('memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('tier', 32);
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('packages');
        Schema::dropIfExists('stations');
        Schema::dropIfExists('station_types');
    }
};
