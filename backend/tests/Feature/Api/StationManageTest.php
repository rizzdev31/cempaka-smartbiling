<?php

namespace Tests\Feature\Api;

use App\Enums\StationStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Station;
use App\Models\StationType;
use App\Models\User;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Station & tipe konsol bisa ditambah dan diubah — DEC-048 (menutup OD-028).
 *
 * ## Yang dijaga di sini
 *
 * Hampir semua aturan di berkas ini ada karena akibatnya **tidak
 * menimbulkan error**. Kode station yang diubah membuat TV diam selamanya;
 * tipe konsol yang ditukar saat ada sesi berjalan membuat sesi itu menunjuk
 * tarif yang bukan dasar harganya. Keduanya hanya ketahuan berhari-hari
 * kemudian, dari keluhan — bukan dari log.
 */
class StationManageTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private function owner(): User
    {
        return $this->operator(['name' => 'Bos', 'role' => UserRole::OWNER]);
    }

    private function admin(): User
    {
        return $this->operator(['name' => 'Adi', 'role' => UserRole::ADMIN]);
    }

    private function buat(array $body = []): TestResponse
    {
        return $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/stations', $body + ['code' => 'ST09', 'name' => 'PS5 VIP']);
    }

    // ── Membuat ───────────────────────────────────────────────────────

    public function test_owner_menambah_station_dengan_nama_dan_tipe_bebas(): void
    {
        $tipe = $this->stationType('Simulator Balap');

        $this->actingAs($this->owner(), 'sanctum');

        $this->buat(['station_type_id' => $tipe->id, 'name' => 'Rig Balap Pojok'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ST09')
            ->assertJsonPath('data.name', 'Rig Balap Pojok')
            ->assertJsonPath('data.console_type', 'Simulator Balap')
            ->assertJsonPath('data.status', 'ACTIVE');
    }

    public function test_station_baru_langsung_punya_kode_pendaftaran_tv(): void
    {
        /*
         * Tanpa kode, station baru tidak punya cara dipasangkan ke TV sama
         * sekali — dan tidak ada endpoint lain yang membuatnya.
         */
        $this->actingAs($this->owner(), 'sanctum');

        $this->buat()->assertCreated();

        $station = Station::query()->where('code', 'ST09')->first();

        $this->assertNotNull($station->enrollment_code);
        $this->assertSame(6, strlen($station->enrollment_code));
    }

    public function test_admin_tidak_boleh_menambah_station(): void
    {
        // Station baru lahir dengan kode pendaftaran; siapa pun yang bisa
        // membuat station bisa membuat kode sebanyak yang dia mau.
        $this->actingAs($this->admin(), 'sanctum');

        $this->buat()->assertStatus(403);

        $this->assertDatabaseMissing('stations', ['code' => 'ST09']);
    }

    public function test_kode_station_tidak_boleh_mengandung_titik(): void
    {
        /*
         * Kode dipakai sebagai nama channel realtime
         * (`private-station.{code}`), dan titik adalah pemisah segmen di
         * sana. "ST.09" menghasilkan channel yang tidak pernah bisa
         * dilanggani — tanpa error, hanya diam.
         */
        $this->actingAs($this->owner(), 'sanctum');

        $this->buat(['code' => 'ST.09'])->assertStatus(422);
        $this->buat(['code' => 'ST 09'])->assertStatus(422);
    }

    public function test_kode_station_tidak_boleh_kembar(): void
    {
        $this->station($this->stationType(), ['code' => 'ST09']);

        $this->actingAs($this->owner(), 'sanctum');

        $this->buat()->assertStatus(422);
    }

    // ── Mengubah ──────────────────────────────────────────────────────

    public function test_admin_boleh_menandai_station_perbaikan(): void
    {
        /*
         * "PS3 nomor 4 rusak" kejadian harian. Menguncinya untuk owner
         * berarti station rusak tetap ditawarkan ke customer sampai owner
         * sempat membuka laptopnya.
         */
        $station = $this->station($this->stationType());

        $this->actingAs($this->admin(), 'sanctum');

        $this->patchJson("/api/v1/stations/{$station->id}", ['status' => 'MAINTENANCE'])
            ->assertOk()
            ->assertJsonPath('data.status', 'MAINTENANCE');
    }

    public function test_admin_tidak_boleh_mengubah_kode_atau_tipe(): void
    {
        $station = $this->station($this->stationType(), ['code' => 'ST01']);
        $lain = $this->stationType('PS5 Pro');

        $this->actingAs($this->admin(), 'sanctum');

        $this->patchJson("/api/v1/stations/{$station->id}", ['code' => 'ST99'])->assertStatus(403);
        $this->patchJson("/api/v1/stations/{$station->id}", ['station_type_id' => $lain->id])->assertStatus(403);

        $this->assertSame('ST01', $station->fresh()->code);
    }

    public function test_mengirim_kode_yang_sama_bukan_perubahan(): void
    {
        /*
         * Form web mengirim SELURUH field, termasuk yang tidak disentuh.
         * Kalau "kode dikirim" dianggap "kode diubah", admin tidak akan bisa
         * menyimpan perubahan nama sama sekali — dan pesannya akan
         * menyalahkan dia atas sesuatu yang tidak dia lakukan.
         */
        $station = $this->station($this->stationType(), ['code' => 'ST01']);

        $this->actingAs($this->admin(), 'sanctum');

        $this->patchJson("/api/v1/stations/{$station->id}", [
            'code' => 'ST01',
            'name' => 'Pojok kanan',
            'status' => 'ACTIVE',
        ])->assertOk();

        $this->assertSame('Pojok kanan', $station->fresh()->name);
    }

    public function test_kode_terkunci_selama_tv_masih_terdaftar(): void
    {
        /*
         * TV berlangganan `private-station.{code}` saat boot. Kode yang
         * berubah membuatnya diam selamanya — tidak ada error, tidak ada
         * yang tahu sampai ada yang menyadari layarnya tidak pernah berganti.
         */
        $station = $this->station($this->stationType(), ['code' => 'ST01']);

        Device::query()->create([
            'station_id' => $station->id,
            'device_uid' => 'tv-uid-1',
            'token_hash' => hash('sha256', 'dummy'),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($this->owner(), 'sanctum');

        $this->patchJson("/api/v1/stations/{$station->id}", ['code' => 'ST99'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'STATION_HAS_DEVICE');

        $this->assertSame('ST01', $station->fresh()->code);
    }

    public function test_nama_tetap_bisa_diubah_walau_tv_terdaftar(): void
    {
        // Yang berbahaya kodenya, bukan namanya. Mengunci keduanya akan
        // membuat station yang sudah dipasang TV tidak bisa dirapikan sama sekali.
        $station = $this->station($this->stationType());

        Device::query()->create([
            'station_id' => $station->id,
            'device_uid' => 'tv-uid-2',
            'token_hash' => hash('sha256', 'dummy'),
            'last_seen_at' => now(),
        ]);

        $this->actingAs($this->owner(), 'sanctum');

        $this->patchJson("/api/v1/stations/{$station->id}", ['name' => 'Meja depan'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Meja depan');
    }

    public function test_tipe_terkunci_selama_ada_sesi_berjalan(): void
    {
        /*
         * Tipe menentukan paket mana yang sah (DEC-019) dan ke mana sesi
         * boleh dipindahkan (DEC-021). Menukarnya saat ada yang bermain
         * membuat sesi berjalan menunjuk tipe yang bukan dasar harganya.
         */
        $tipe = $this->stationType();
        $station = $this->station($tipe);
        $package = $this->package($tipe);
        $lain = $this->stationType('PS5 Pro');

        $this->actingAs($this->owner(), 'sanctum');

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $station->id,
                'package_id' => $package->id,
                'mode' => 'POSTPAID',
            ])->assertCreated();

        $this->patchJson("/api/v1/stations/{$station->id}", ['station_type_id' => $lain->id])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'STATION_HAS_ACTIVE_SESSION');
    }

    public function test_perubahan_station_tercatat_di_audit_dengan_nilai_lama(): void
    {
        // Pertanyaan "kenapa station ini tiba-tiba nonaktif" hanya bisa
        // dijawab kalau nilai sebelumnya ikut tersimpan (PRD §24).
        $station = $this->station($this->stationType(), ['name' => 'Lama']);

        $this->actingAs($this->owner(), 'sanctum');

        $this->patchJson("/api/v1/stations/{$station->id}", [
            'name' => 'Baru', 'status' => 'DISABLED',
        ])->assertOk();

        $audit = AuditLog::query()->where('action', AuditAction::STATION_UPDATED)->first();

        $this->assertSame('Lama', $audit->before['name']);
        $this->assertSame('Baru', $audit->after['name']);
        $this->assertSame('ACTIVE', $audit->before['status']);
        $this->assertSame('DISABLED', $audit->after['status']);
    }

    public function test_station_nonaktif_tidak_bisa_menerima_sesi_baru(): void
    {
        /*
         * Ini alasan seluruh fitur status ada. Kalau station MAINTENANCE
         * tetap menerima sesi, menandainya rusak tidak mengubah apa pun.
         */
        $tipe = $this->stationType();
        $station = $this->station($tipe);
        $package = $this->package($tipe);

        $this->actingAs($this->owner(), 'sanctum');

        $this->patchJson("/api/v1/stations/{$station->id}", ['status' => 'MAINTENANCE'])->assertOk();

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $station->id,
                'package_id' => $package->id,
                'mode' => 'PREPAID',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'STATION_NOT_AVAILABLE');
    }

    // ── Tipe konsol ───────────────────────────────────────────────────

    public function test_owner_menambah_tipe_konsol_dengan_nama_bebas(): void
    {
        // DEC-019 sengaja tidak memakai enum: enum memaksa rilis aplikasi
        // baru setiap kali pemilik membeli jenis mesin yang berbeda.
        $this->actingAs($this->owner(), 'sanctum');

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/station-types', ['name' => 'Simulator Balap'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Simulator Balap')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_tipe_konsol_beda_huruf_besar_ditolak(): void
    {
        /*
         * "PS5" dan "ps5" sebagai dua tipe berbeda akan memecah daftar harga
         * jadi dua, dan tidak ada yang menyadarinya sampai ada paket yang
         * "hilang".
         *
         * Perlindungannya datang dari collation kolom (`utf8mb4_unicode_ci`),
         * bukan dari kode. Test ini yang akan gagal kalau suatu saat kolomnya
         * dipindah ke collation case-sensitive.
         */
        $this->stationType('PS5');

        $this->actingAs($this->owner(), 'sanctum');

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/station-types', ['name' => 'ps5'])
            ->assertStatus(422);
    }

    public function test_admin_tidak_boleh_menyentuh_tipe_konsol(): void
    {
        // Tipe adalah pembawa tarif (DEC-019); membuatnya berarti membuka
        // tempat untuk daftar harga baru.
        $tipe = $this->stationType();

        $this->actingAs($this->admin(), 'sanctum');

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/station-types', ['name' => 'Apa Saja'])
            ->assertStatus(403);

        $this->patchJson("/api/v1/station-types/{$tipe->id}", ['name' => 'Diganti'])
            ->assertStatus(403);
    }

    public function test_tipe_tidak_bisa_dinonaktifkan_selagi_dipakai_station_aktif(): void
    {
        /*
         * Kalau boleh, station-nya tetap terlihat kosong dan bisa diklik,
         * lalu tidak ada satu pun paket yang bisa dipilih. Kasir akan
         * mengira sistemnya rusak.
         */
        $tipe = $this->stationType();
        $this->station($tipe);

        $this->actingAs($this->owner(), 'sanctum');

        $this->patchJson("/api/v1/station-types/{$tipe->id}", ['is_active' => false])
            ->assertStatus(409);

        $this->assertTrue($tipe->fresh()->is_active);
    }

    public function test_tipe_bisa_dinonaktifkan_setelah_station_nya_nonaktif(): void
    {
        $tipe = $this->stationType();
        $station = $this->station($tipe);

        $this->actingAs($this->owner(), 'sanctum');

        $this->patchJson("/api/v1/stations/{$station->id}", ['status' => 'DISABLED'])->assertOk();

        $this->patchJson("/api/v1/station-types/{$tipe->id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_daftar_tipe_menyebut_berapa_station_memakainya(): void
    {
        // Dipakai layar pengaturan untuk memberi tahu akibat menonaktifkan
        // tipe SEBELUM tombolnya ditekan.
        $tipe = $this->stationType();
        $this->station($tipe, ['code' => 'ST01']);
        $this->station($tipe, ['code' => 'ST02']);

        $this->actingAs($this->owner(), 'sanctum');

        $this->getJson('/api/v1/station-types')
            ->assertOk()
            ->assertJsonPath('data.0.station_count', 2);
    }

    public function test_operator_tidak_punya_akses_sama_sekali(): void
    {
        $station = $this->station($this->stationType());

        $this->actingAs($this->operator(['role' => UserRole::OPERATOR]), 'sanctum');

        $this->buat()->assertStatus(403);
        $this->patchJson("/api/v1/stations/{$station->id}", ['status' => 'DISABLED'])->assertStatus(403);
    }

    public function test_tipe_yang_tidak_dikenal_ditolak_bukan_disimpan_menggantung(): void
    {
        // ID yang tidak ada akan tersimpan sebagai relasi menggantung tanpa
        // error apa pun — station yang tipenya tidak pernah bisa ditemukan.
        $this->actingAs($this->owner(), 'sanctum');

        $this->buat(['station_type_id' => '00000000-0000-4000-8000-000000000000'])
            ->assertStatus(422);
    }

    public function test_station_yang_statusnya_diubah_tidak_menghentikan_sesi_berjalan(): void
    {
        /*
         * MAINTENANCE menutup pintu untuk sesi BARU, bukan mengusir yang
         * sedang bermain. Menghentikan sesi berjalan diam-diam berarti
         * customer yang sudah membayar kehilangan waktunya karena seseorang
         * menekan tombol administratif.
         */
        $tipe = $this->stationType();
        $station = $this->station($tipe);
        $package = $this->package($tipe);

        $this->actingAs($this->owner(), 'sanctum');

        $sessionId = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $station->id,
                'package_id' => $package->id,
                'mode' => 'POSTPAID',
            ])->json('data.id');

        $this->patchJson("/api/v1/stations/{$station->id}", ['status' => 'MAINTENANCE'])->assertOk();

        $this->getJson("/api/v1/sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE');

        $this->assertSame(
            StationStatus::MAINTENANCE,
            Station::query()->whereKey($station->id)->first()->status,
        );
    }

    public function test_tipe_konsol_yang_dinonaktifkan_hilang_dari_pilihan_tapi_tidak_terhapus(): void
    {
        /*
         * Tidak ada hapus, hanya nonaktif. Tipe dipegang paket, station, dan
         * setiap sesi yang pernah berjalan di atasnya — menghapusnya berarti
         * membuang dasar harga dari riwayat yang sudah dibayar.
         */
        $tipe = $this->stationType('PS3 Lama');

        $this->actingAs($this->owner(), 'sanctum');

        $this->patchJson("/api/v1/station-types/{$tipe->id}", ['is_active' => false])->assertOk();

        $this->getJson('/api/v1/station-types')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/station-types?only_active=0')->assertOk()->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('station_types', ['id' => $tipe->id]);
    }

    public function test_tipe_konsol_baru_tercatat_di_audit(): void
    {
        $this->actingAs($this->owner(), 'sanctum');

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/station-types', ['name' => 'VIP Room'])
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::STATION_TYPE_CREATED]);
    }

    public function test_station_baru_muncul_di_daftar_station(): void
    {
        // Pemeriksaan paling sederhana, dan yang paling sering terlewat:
        // apakah yang dibuat benar-benar terlihat di tempat orang mencarinya.
        $this->actingAs($this->owner(), 'sanctum');

        $this->buat(['code' => 'ST77'])->assertCreated();

        $kode = collect($this->getJson('/api/v1/stations')->json('data'))->pluck('code');

        $this->assertContains('ST77', $kode->all());
    }

    public function test_tipe_konsol_tanpa_station_boleh_langsung_nonaktif(): void
    {
        $tipe = StationType::query()->create(['name' => 'Belum Dipakai', 'sort_order' => 0, 'is_active' => true]);

        $this->actingAs($this->owner(), 'sanctum');

        $this->patchJson("/api/v1/station-types/{$tipe->id}", ['is_active' => false])->assertOk();
    }
}
