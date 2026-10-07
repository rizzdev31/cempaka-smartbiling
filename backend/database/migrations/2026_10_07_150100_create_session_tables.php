<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Session billing + item Open Tab.
 *
 * Tabel ini bernama `sessions` sesuai PRD §22. Session web Laravel sudah
 * dipindah ke `web_sessions` supaya tidak bertabrakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('operator_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('opening_cash')->default(0);
            $table->unsignedInteger('closing_cash')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'closed_at']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();   // S-20261002-0001

            $table->foreignUuid('station_id')->constrained()->restrictOnDelete();

            /*
             * DEC-008 — satu session = satu customer. FK tunggal nullable,
             * TIDAK ada pivot `session_customers`. Walk-in non-member:
             * customer_id null + customer_name "Walk-in" (API.md §7).
             */
            $table->foreignUuid('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();

            $table->foreignUuid('package_id')->constrained()->restrictOnDelete();

            /*
             * Harga dibekukan saat session dibuat.
             *
             * Owner boleh mengubah tarif kapan saja (DEC-019/020). Tanpa
             * snapshot ini, mengubah harga paket akan mengubah tagihan session
             * yang sedang berjalan — termasuk harga extend, karena rumus
             * DEC-007 memakai `hourly_rate`. Customer sudah disebutkan harga
             * di depan; harga itu tidak boleh bergerak di tengah sesi.
             */
            $table->string('package_name', 64);
            $table->unsignedSmallInteger('package_duration_minutes');
            $table->unsignedInteger('package_price');
            $table->unsignedInteger('hourly_rate');

            $table->string('mode', 16);     // SessionMode
            $table->string('status', 24);   // SessionStatus

            // Semuanya nullable: PENDING_PAYMENT belum punya waktu mulai
            // maupun habis (API.md §7). Timer baru jalan setelah payment.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            // Diisi saat checkout. Dua-duanya disimpan supaya operator bisa
            // menjelaskan kenapa 63 menit ditagih 60 (API.md §7 checkout).
            $table->unsignedSmallInteger('actual_duration_minutes')->nullable();
            $table->unsignedSmallInteger('billable_duration_minutes')->nullable();

            $table->string('receipt_number', 32)->nullable()->unique();
            $table->timestamp('receipt_issued_at')->nullable();

            $table->foreignUuid('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('shift_id')->nullable()->constrained()->nullOnDelete();

            $table->string('cancel_reason')->nullable();

            $table->timestamps();

            // Dashboard memuat station + session aktif; reconcile memfilter status.
            $table->index(['status', 'end_at']);
            $table->index(['station_id', 'status']);
        });

        Schema::create('session_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained()->cascadeOnDelete();

            $table->string('type', 16);  // SessionItemType
            $table->string('name');
            $table->unsignedSmallInteger('qty')->default(1);

            /*
             * SIGNED, bukan unsigned: DISCOUNT bernilai negatif.
             * Integer rupiah tanpa desimal (DEC-005) — tidak ada float untuk uang.
             */
            $table->integer('unit_price');
            $table->integer('subtotal');

            $table->boolean('is_paid')->default(false);

            // EXTEND -> {"duration_minutes":30} · FNB -> {"fnb_order_id":"uuid"}
            $table->json('meta')->nullable();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['session_id', 'type']);
            $table->index(['session_id', 'is_paid']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained()->cascadeOnDelete();

            $table->string('method', 16);   // PaymentMethod
            $table->unsignedInteger('amount');

            // V1 manual: dikonfirmasi operator saat dibuat. Status tetap ada
            // karena gateway (Tahap 3D) akan punya PENDING -> CONFIRMED.
            $table->string('status', 16)->default('CONFIRMED');

            // QRIS statis wajib punya referensi (API.md §7).
            $table->string('reference', 64)->nullable();
            $table->text('note')->nullable();

            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            $table->index(['session_id', 'status']);
            $table->index(['shift_id', 'method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('session_items');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('shifts');
    }
};
