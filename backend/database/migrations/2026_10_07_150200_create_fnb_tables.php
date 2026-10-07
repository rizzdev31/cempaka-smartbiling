<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** F&B — PRD §13, API.md §8. Order masuk Open Tab session yang sama. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fnb_products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('category', 64);
            $table->string('name');
            $table->unsignedInteger('price');

            // HPP untuk laporan profit (Tahap 3B). Nullable karena formula
            // profit/margin belum diputuskan (OD-009).
            $table->unsignedInteger('hpp')->nullable();

            // null = stok tidak dilacak (API.md §8).
            $table->integer('stock')->nullable();

            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_available']);
        });

        Schema::create('fnb_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 32)->unique();   // FB-0012
            $table->foreignUuid('session_id')->constrained()->cascadeOnDelete();

            $table->string('status', 16)->default('PENDING');  // FnbOrderStatus
            $table->string('source', 16)->default('OPERATOR'); // CUSTOMER baru di Tahap 3C

            $table->text('note')->nullable();
            $table->unsignedInteger('total');

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Antrian F&B operator memfilter status.
            $table->index('status');
            $table->index(['session_id', 'status']);
        });

        Schema::create('fnb_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fnb_order_id')->constrained()->cascadeOnDelete();

            // restrictOnDelete: produk yang sudah pernah dipesan tidak boleh
            // dihapus, supaya histori order tidak kehilangan acuan.
            $table->foreignUuid('fnb_product_id')->constrained()->restrictOnDelete();

            // Nama & harga dibekukan saat order dibuat. Owner mengubah harga
            // menu tidak boleh mengubah tagihan order yang sudah jalan.
            $table->string('name');
            $table->unsignedSmallInteger('qty');
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('subtotal');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fnb_order_items');
        Schema::dropIfExists('fnb_orders');
        Schema::dropIfExists('fnb_products');
    }
};
