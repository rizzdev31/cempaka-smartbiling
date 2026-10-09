<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Master data read-only — `GET /stations` dan `GET /packages`, API.md §6.
 *
 * Dua endpoint ini yang membuat tablet tidak perlu tahu apa pun dari database.
 * Sebelum ada keduanya, golden path masih mengambil `station_id` dan
 * `package_id` langsung dari MySQL — exit criteria ROADMAP melarang itu.
 */
class MasterDataTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    public function test_daftar_station_dengan_tipe_konsol(): void
    {
        $ps5 = $this->stationType('PS5 VIP');
        $this->station($ps5, ['code' => 'ST01', 'name' => 'Station 1', 'sort_order' => 1]);
        $this->station($ps5, ['code' => 'ST02', 'name' => 'Station 2', 'sort_order' => 2]);

        $this->actingAs($this->operator(), 'sanctum');

        $this->getJson('/api/v1/stations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'ST01')
            ->assertJsonPath('data.0.console_type', 'PS5 VIP')
            ->assertJsonPath('data.0.status', 'ACTIVE')
            // Kosong = tidak punya sesi aktif. Tidak ada status "AVAILABLE"
            // yang disimpan di mana pun.
            ->assertJsonPath('data.0.session', null)
            ->assertJsonPath('data.0.device', null);
    }

    public function test_ambang_offline_dikirim_di_meta(): void
    {
        // Supaya Flutter tidak menuliskan angkanya sendiri.
        $this->station($this->stationType());
        $this->actingAs($this->operator(), 'sanctum');

        $this->getJson('/api/v1/stations')
            ->assertOk()
            ->assertJsonPath('meta.offline_threshold_seconds', Device::OFFLINE_THRESHOLD_SECONDS);
    }

    public function test_station_terpakai_membawa_ringkasan_sesi(): void
    {
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);

        $this->actingAs($this->operator(), 'sanctum');
        $this->travelTo(Carbon::parse('2026-10-09T07:00:00Z'));

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $station->id,
                'package_id' => $package->id,
                'mode' => 'POSTPAID',
            ])->assertCreated();

        $this->getJson('/api/v1/stations')
            ->assertOk()
            ->assertJsonPath('data.0.session.status', 'ACTIVE')
            ->assertJsonPath('data.0.session.mode', 'POSTPAID')
            ->assertJsonPath('data.0.session.customer_label', 'Walk-in')
            // DEC-034 — Postpaid tidak punya batas waktu.
            ->assertJsonPath('data.0.session.end_at', null)
            // Tagihan berjalan: minimum satu blok 30 menit.
            ->assertJsonPath('data.0.session.balance_due', 10000);
    }

    public function test_status_tv_dihitung_dari_last_seen(): void
    {
        $station = $this->station($this->stationType());
        $this->actingAs($this->operator(), 'sanctum');

        $device = Device::query()->create([
            'device_uid' => 'TV-ST01',
            'station_id' => $station->id,
            'app_version' => '0.1.0',
            'last_seen_at' => Carbon::now(),
        ]);

        $this->getJson('/api/v1/stations')
            ->assertOk()
            ->assertJsonPath('data.0.device.status', 'ONLINE')
            ->assertJsonPath('data.0.device.app_version', '0.1.0');

        // Lewat ambang -> OFFLINE, tanpa ada kolom status yang perlu diubah.
        $device->update(['last_seen_at' => Carbon::now()->subSeconds(Device::OFFLINE_THRESHOLD_SECONDS + 10)]);

        $this->getJson('/api/v1/stations')
            ->assertOk()
            ->assertJsonPath('data.0.device.status', 'OFFLINE');
    }

    public function test_daftar_paket_dengan_tarif_per_jam(): void
    {
        $type = $this->stationType('PS4 Slim');
        $this->package($type, ['name' => '1 Jam', 'duration_minutes' => 60, 'price' => 15000]);
        $this->package($type, ['name' => '2 Jam', 'duration_minutes' => 120, 'price' => 28000]);

        $this->actingAs($this->operator(), 'sanctum');

        $this->getJson('/api/v1/packages')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', '1 Jam')
            ->assertJsonPath('data.0.hourly_rate', 15000)
            ->assertJsonPath('data.0.console_type', 'PS4 Slim')
            // 28.000 / 2 jam = 14.000
            ->assertJsonPath('data.1.hourly_rate', 14000);
    }

    public function test_paket_bisa_disaring_per_station(): void
    {
        // DEC-019 — layar Start Session hanya boleh menampilkan paket yang sah
        // untuk tipe konsol station yang dipilih.
        $ps4 = $this->stationType('PS4 Slim');
        $ps5 = $this->stationType('PS5 VIP');

        $stationPs5 = $this->station($ps5, ['code' => 'ST01', 'name' => 'Station 1']);
        $this->package($ps4, ['name' => 'PS4 1 Jam']);
        $this->package($ps5, ['name' => 'PS5 1 Jam']);

        $this->actingAs($this->operator(), 'sanctum');

        $this->getJson("/api/v1/packages?station_id={$stationPs5->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'PS5 1 Jam');
    }

    public function test_paket_nonaktif_tidak_muncul(): void
    {
        $type = $this->stationType();
        $this->package($type, ['name' => 'Aktif']);
        $this->package($type, ['name' => 'Sudah Tidak Dijual', 'is_active' => false]);

        $this->actingAs($this->operator(), 'sanctum');

        $this->getJson('/api/v1/packages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Aktif');
    }

    public function test_station_tanpa_tipe_konsol_tidak_mengembalikan_semua_paket(): void
    {
        // Daftar penuh di layar yang sudah memilih station akan menyesatkan —
        // operator bisa memilih paket yang pasti ditolak server.
        $type = $this->stationType();
        $this->package($type);
        $tanpaTipe = $this->station($type, ['code' => 'ST09', 'name' => 'Station 9', 'station_type_id' => null]);

        $this->actingAs($this->operator(), 'sanctum');

        $this->getJson("/api/v1/packages?station_id={$tanpaTipe->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_butuh_login(): void
    {
        $this->getJson('/api/v1/stations')->assertStatus(401);
        $this->getJson('/api/v1/packages')->assertStatus(401);
    }
}
