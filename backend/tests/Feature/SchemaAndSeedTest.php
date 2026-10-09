<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Package;
use App\Models\Station;
use App\Models\StationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Schema & seeder Tahap 0 — PRD §22, DEC-019, DEC-020.
 */
class SchemaAndSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabel_sessions_milik_billing_bukan_session_web(): void
    {
        // Laravel bawaan membuat tabel `sessions` untuk session web, yang
        // bertabrakan dengan `sessions` billing di PRD §22. Session web
        // dipindah ke `web_sessions`.
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasColumn('sessions', 'end_at'));
        $this->assertTrue(Schema::hasTable('web_sessions'));
        $this->assertSame('web_sessions', config('session.table'));
    }

    public function test_semua_entity_tahap_0_ada(): void
    {
        foreach ([
            'users', 'customers', 'memberships', 'station_types', 'stations',
            'devices', 'packages', 'sessions', 'session_items', 'payments',
            'fnb_products', 'fnb_orders', 'fnb_order_items', 'shifts',
            'audit_logs', 'idempotency_keys',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} belum ada.");
        }
    }

    public function test_satu_session_satu_customer_tanpa_tabel_pivot(): void
    {
        // DEC-008 menolak pivot `session_customers`.
        $this->assertTrue(Schema::hasColumn('sessions', 'customer_id'));
        $this->assertFalse(Schema::hasTable('session_customers'));
    }

    public function test_primary_key_berbentuk_uuid(): void
    {
        $this->seed();

        $station = Station::query()->first();

        $this->assertTrue(Str::isUuid($station->id));
        $this->assertFalse($station->getIncrementing());
        $this->assertSame('string', $station->getKeyType());

        // UUID v4 (API.md §1) — karakter ke-15 menandai versinya.
        $this->assertSame('4', $station->id[14]);
    }

    public function test_seeder_membuat_tiga_role(): void
    {
        $this->seed();

        $this->assertSame(UserRole::OWNER, User::query()->where('username', 'owner')->sole()->role);
        $this->assertSame(UserRole::ADMIN, User::query()->where('username', 'admin')->sole()->role);
        $this->assertSame(UserRole::OPERATOR, User::query()->where('username', 'operator1')->sole()->role);
    }

    public function test_seeder_membuat_enam_station_dengan_tipe_konsol(): void
    {
        $this->seed();

        $this->assertSame(6, Station::query()->count());

        $codes = Station::query()->orderBy('sort_order')->pluck('code')->all();
        $this->assertSame(['ST01', 'ST02', 'ST03', 'ST04', 'ST05', 'ST06'], $codes);

        // DEC-019: setiap station punya tipe konsol, karena tarifnya menempel di situ.
        $this->assertSame(0, Station::query()->whereNull('station_type_id')->count());
    }

    public function test_tarif_berbeda_antar_tipe_konsol(): void
    {
        $this->seed();

        $ps4 = StationType::query()->where('name', 'PS4')->sole();
        $ps3 = StationType::query()->where('name', 'PS3')->sole();

        $ps4Hourly = Package::query()->where('station_type_id', $ps4->id)
            ->where('name', '1 Jam')->sole();
        $ps3Hourly = Package::query()->where('station_type_id', $ps3->id)
            ->where('name', '1 Jam')->sole();

        // Inti DEC-019: paket bernama sama boleh berbeda harga per tipe konsol.
        $this->assertNotSame($ps4Hourly->price, $ps3Hourly->price);
        $this->assertGreaterThan($ps3Hourly->price, $ps4Hourly->price);

        // Tarif asli dari catatan user (DEC-036).
        $this->assertSame(10000, (int) $ps4Hourly->price);
        $this->assertSame(8000, (int) $ps3Hourly->price);
    }

    public function test_paket_dengan_nama_sama_boleh_ada_di_tipe_konsol_berbeda(): void
    {
        $this->seed();

        // Unique constraint-nya (station_type_id, name) — bukan name saja.
        $this->assertSame(2, Package::query()->where('name', '1 Jam')->count());
    }

    public function test_paket_bonus_waktu_menurunkan_tarif_per_jamnya(): void
    {
        $this->seed();

        /*
         * "3 jam gratis 1 jam" disimpan sebagai paket 240 menit (DEC-036).
         * Konsekuensinya tarif per jam paket ini di BAWAH tarif normal, dan
         * harga extend memakai angka itu — extend di paket promo jadi lebih
         * murah daripada extend biasa.
         *
         * Diuji supaya kalau suatu hari angkanya terasa salah di laporan,
         * jelas bahwa ini akibat yang sudah diketahui, bukan bug.
         */
        $ps3 = StationType::query()->where('name', 'PS3')->sole();

        $normal = Package::query()->where('station_type_id', $ps3->id)
            ->where('name', '1 Jam')->sole();
        $promo = Package::query()->where('station_type_id', $ps3->id)
            ->where('name', '3 Jam + 1 Jam Gratis')->sole();

        $this->assertSame(240, (int) $promo->duration_minutes);
        $this->assertSame(8000, $normal->hourlyRate());
        $this->assertSame(7500, $promo->hourlyRate());
    }

    public function test_station_kosong_tidak_punya_session_berjalan(): void
    {
        $this->seed();

        $this->assertNull(Station::query()->where('code', 'ST01')->sole()->currentSession());
    }

    public function test_seeder_boleh_dijalankan_dua_kali_tanpa_duplikat(): void
    {
        // Pindah ke VPS nanti dilakukan dengan `migrate --seed` (DEC-002),
        // jadi seeder harus aman dijalankan ulang.
        $this->seed();
        $this->seed();

        $this->assertSame(6, Station::query()->count());
        $this->assertSame(3, User::query()->count());
    }
}
