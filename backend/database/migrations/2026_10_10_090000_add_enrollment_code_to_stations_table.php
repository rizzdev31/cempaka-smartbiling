<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode pendaftaran TV — API.md §9 `POST /devices/register`.
 *
 * Kontrak menyebut "kode pendaftaran yang dibuat Admin" tapi tidak menetapkan
 * di mana ia tinggal. Ditaruh di `stations` karena kode itulah yang menentukan
 * TV ini milik station yang mana — response register mengembalikan
 * `station: { code }`, jadi pemetaannya memang berasal dari sini.
 *
 * Alternatif tabel `device_enrollments` tersendiri akan memungkinkan kode
 * sekali pakai dan kedaluwarsa. Tidak dipakai sekarang: selama Tahap 2 teknisi
 * akan mendaftarkan ulang TV berkali-kali saat uji coba, dan kode sekali pakai
 * tanpa UI Admin (Tahap 3B) akan membuatnya buntu setelah percobaan pertama.
 *
 * Konsekuensinya kode ini berumur panjang — lihat OD-027.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            /*
             * Nullable: station boleh ada tanpa kode, dan mencabut kode =
             * mengosongkannya. Unik supaya satu kode tidak pernah menunjuk
             * dua station.
             */
            $table->char('enrollment_code', 8)->nullable()->unique()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropUnique(['enrollment_code']);
            $table->dropColumn('enrollment_code');
        });
    }
};
