<?php

namespace Tests\Feature\Api;

use App\Enums\SessionStatus;
use App\Events\DeviceHeartbeat;
use App\Models\BillingSession;
use App\Models\Device;
use App\Models\Package;
use App\Models\Station;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * TV Agent — API.md §9.
 *
 * Yang paling penting diuji di sini bukan "TV bisa mendaftar", tapi batas
 * aksesnya: TV tidak boleh menyentuh apa pun di luar tiga endpoint miliknya
 * (PRD §6), dan hanya boleh mendengarkan station yang dipetakan ke dirinya.
 */
class DeviceTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private Station $st01;

    private Package $packageModel;

    protected function setUp(): void
    {
        parent::setUp();

        $type = $this->stationType();
        $this->st01 = $this->station($type, ['code' => 'ST01', 'name' => 'Station 1']);
        $this->packageModel = $this->package($type);
    }

    private function register(array $body = [], ?string $code = null): TestResponse
    {
        return $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/devices/register', $body + [
                'enrollment_code' => $code ?? $this->st01->enrollment_code,
                'device_uid' => 'android-id-aaa',
                'model' => 'Xiaomi TV A2',
                'os_version' => 'Android 11',
                'app_version' => '0.1.0',
            ]);
    }

    private function asDevice(string $token): static
    {
        return $this->withHeader('X-Device-Token', $token);
    }

    // ── Pendaftaran ──────────────────────────────────────────────────────

    public function test_tv_mendaftar_dengan_kode_station(): void
    {
        $response = $this->register()
            ->assertCreated()
            ->assertJsonPath('data.station.code', 'ST01');

        $this->assertNotEmpty($response->json('data.device_token'));
        $this->assertSame('Xiaomi TV A2', Device::query()->sole()->model);
    }

    public function test_token_asli_tidak_pernah_tersimpan(): void
    {
        // Kalau database terbaca, isinya tidak boleh bisa dipakai menyamar
        // jadi TV mana pun.
        $token = $this->register()->json('data.device_token');

        $device = Device::query()->sole();

        $this->assertNotSame($token, $device->token_hash);
        $this->assertSame(hash('sha256', $token), $device->token_hash);
    }

    public function test_kode_salah_ditolak(): void
    {
        $this->register(code: 'ZZZZZZ')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'ENROLLMENT_CODE_INVALID');

        $this->assertSame(0, Device::query()->count());
    }

    public function test_tv_yang_sama_boleh_mendaftar_ulang(): void
    {
        // Kejadian normal saat APK dipasang ulang: token baru, baris tetap satu.
        $pertama = $this->register()->json('data.device_token');
        $kedua = $this->register()->json('data.device_token');

        $this->assertNotSame($pertama, $kedua);
        $this->assertSame(1, Device::query()->count());
    }

    public function test_token_lama_mati_setelah_daftar_ulang(): void
    {
        $lama = $this->register()->json('data.device_token');
        $this->register();

        $this->asDevice($lama)->getJson('/api/v1/devices/me/state')->assertStatus(401);
    }

    public function test_station_yang_sudah_dipegang_tv_lain_ditolak(): void
    {
        /*
         * Teknisi yang salah membacakan kode akan membuat TV yang sedang jalan
         * kehilangan tokennya tanpa ada yang sadar — dan station itu berhenti
         * bekerja di tengah jam operasional.
         */
        $this->register()->assertCreated();

        $this->register(['device_uid' => 'android-id-bbb'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'STATION_HAS_DEVICE');
    }

    public function test_pendaftaran_tercatat_di_audit(): void
    {
        $this->register()->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DEVICE_REGISTERED,
            'subject_type' => 'device',
        ]);
    }

    // ── Batas akses ──────────────────────────────────────────────────────

    public function test_tanpa_token_ditolak(): void
    {
        $this->getJson('/api/v1/devices/me/state')->assertStatus(401);
        $this->postJson('/api/v1/devices/heartbeat', [])->assertStatus(401);
    }

    public function test_token_dicabut_langsung_kehilangan_akses(): void
    {
        $token = $this->register()->json('data.device_token');

        // Pencabutan = mengisi revoked_at, bukan menghapus baris, supaya
        // histori heartbeat tetap punya pemilik.
        Device::query()->sole()->update(['revoked_at' => Carbon::now()]);

        $this->asDevice($token)->getJson('/api/v1/devices/me/state')->assertStatus(401);
    }

    public function test_tv_tidak_bisa_menyentuh_endpoint_operator(): void
    {
        // PRD §6 — TV tidak punya akses apa pun di luar endpoint miliknya.
        $token = $this->register()->json('data.device_token');

        $this->asDevice($token)->getJson('/api/v1/sessions')->assertStatus(401);
        $this->asDevice($token)->getJson('/api/v1/stations')->assertStatus(401);
        $this->asDevice($token)->getJson('/api/v1/shifts/current')->assertStatus(401);
    }

    // ── State & heartbeat ────────────────────────────────────────────────

    public function test_station_kosong_memberi_mode_idle(): void
    {
        $token = $this->register()->json('data.device_token');

        $this->asDevice($token)->getJson('/api/v1/devices/me/state')
            ->assertOk()
            ->assertJsonPath('data.station.code', 'ST01')
            ->assertJsonPath('data.session', null)
            ->assertJsonPath('data.display.mode', 'IDLE');
    }

    public function test_sesi_berjalan_memberi_mode_timer(): void
    {
        $token = $this->register()->json('data.device_token');
        $this->mulaiSesi();

        $this->asDevice($token)->getJson('/api/v1/devices/me/state')
            ->assertOk()
            ->assertJsonPath('data.session.status', 'ACTIVE')
            ->assertJsonPath('data.display.mode', 'TIMER')
            ->assertJsonPath('data.session.customer_label', 'Walk-in');
    }

    public function test_waktu_habis_memberi_mode_locked(): void
    {
        /*
         * `LOCKED` akhirnya terpakai. Kontrak menandainya menunggu OD-001 &
         * OD-004 — keduanya sudah diputuskan: DEC-033 (waktu habis berarti
         * berhenti, TV mati/standby) dan DEC-030 (warning overlay).
         */
        $token = $this->register()->json('data.device_token');
        $id = $this->mulaiSesi();

        BillingSession::find($id)->update(['status' => SessionStatus::EXPIRED]);

        $this->asDevice($token)->getJson('/api/v1/devices/me/state')
            ->assertOk()
            ->assertJsonPath('data.display.mode', 'LOCKED');
    }

    public function test_tidak_ada_sisa_detik_dikirim_server(): void
    {
        // PRD §16, DEC-003 — TV menghitung sendiri dari end_at + offset.
        $token = $this->register()->json('data.device_token');
        $this->mulaiSesi();

        $state = $this->asDevice($token)->getJson('/api/v1/devices/me/state')->json('data');

        $this->assertArrayNotHasKey('remaining_seconds', $state['session']);
        $this->assertNotNull($state['session']['end_at']);
    }

    public function test_device_tanpa_station_ditolak(): void
    {
        $token = $this->register()->json('data.device_token');
        Device::query()->sole()->update(['station_id' => null]);

        $this->asDevice($token)->getJson('/api/v1/devices/me/state')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DEVICE_NOT_ASSIGNED');
    }

    public function test_heartbeat_memperbarui_last_seen(): void
    {
        $token = $this->register()->json('data.device_token');

        $this->asDevice($token)
            ->postJson('/api/v1/devices/heartbeat', ['uptime_seconds' => 3600, 'app_version' => '0.2.0'])
            ->assertOk()
            ->assertJsonPath('data.acknowledged', true);

        $device = Device::query()->sole();

        $this->assertNotNull($device->last_seen_at);
        $this->assertSame(3600, (int) $device->uptime_seconds);
        $this->assertSame('0.2.0', $device->app_version);
    }

    public function test_state_match_mendeteksi_tv_yang_basi(): void
    {
        /*
         * Inti reconciliation murah tanpa WebSocket (API.md §9) — justru
         * paling dibutuhkan ketika WebSocket-nya sedang putus.
         */
        $token = $this->register()->json('data.device_token');
        $id = $this->mulaiSesi();
        $endAt = BillingSession::find($id)->end_at->toIso8601ZuluString();

        // TV mengira tidak ada sesi -> basi.
        $this->asDevice($token)->postJson('/api/v1/devices/heartbeat', [])
            ->assertOk()
            ->assertJsonPath('data.state_match', false)
            ->assertJsonPath('data.state.session.id', $id);

        // TV tahu sesi dan end_at yang benar -> cocok.
        $this->asDevice($token)->postJson('/api/v1/devices/heartbeat', [
            'known_session_id' => $id,
            'known_end_at' => $endAt,
        ])->assertOk()->assertJsonPath('data.state_match', true);
    }

    public function test_end_at_berbeda_dianggap_basi(): void
    {
        // Skenario nyata: TV ketinggalan event extend.
        $token = $this->register()->json('data.device_token');
        $id = $this->mulaiSesi();

        $this->asDevice($token)->postJson('/api/v1/devices/heartbeat', [
            'known_session_id' => $id,
            'known_end_at' => '2020-01-01T00:00:00Z',
        ])->assertOk()->assertJsonPath('data.state_match', false);
    }

    public function test_broadcast_heartbeat_dibatasi_satu_per_tiga_puluh_detik(): void
    {
        // REALTIME.md §7. Di sini throttle memang benar: heartbeat yang
        // terbuang tidak menghilangkan informasi apa pun, karena last_seen_at
        // sudah tersimpan di database.
        $token = $this->register()->json('data.device_token');
        Event::fake([DeviceHeartbeat::class]);

        $this->asDevice($token)->postJson('/api/v1/devices/heartbeat', [])->assertOk();
        $this->asDevice($token)->postJson('/api/v1/devices/heartbeat', [])->assertOk();

        Event::assertDispatchedTimes(DeviceHeartbeat::class, 1);
    }

    // ── Daftar device untuk operator ─────────────────────────────────────

    public function test_operator_melihat_daftar_tv(): void
    {
        $this->register()->assertCreated();
        $this->actingAs($this->operator(), 'sanctum');

        $this->getJson('/api/v1/devices')
            ->assertOk()
            ->assertJsonPath('data.0.device_uid', 'android-id-aaa')
            ->assertJsonPath('data.0.station.code', 'ST01')
            ->assertJsonPath('data.0.status', 'OFFLINE')
            ->assertJsonPath('meta.offline_threshold_seconds', Device::OFFLINE_THRESHOLD_SECONDS);
    }

    public function test_status_online_setelah_heartbeat(): void
    {
        $token = $this->register()->json('data.device_token');
        $this->asDevice($token)->postJson('/api/v1/devices/heartbeat', [])->assertOk();

        $this->actingAs($this->operator(), 'sanctum');

        $this->getJson('/api/v1/devices')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'ONLINE');
    }

    private function mulaiSesi(): string
    {
        $this->actingAs($this->operator(), 'sanctum');
        $this->travelTo(Carbon::parse('2026-10-10T07:00:00Z'));

        $id = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->st01->id,
                'package_id' => $this->packageModel->id,
                'mode' => 'PREPAID',
            ])->json('data.id');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/payments", ['method' => 'CASH', 'amount' => 20000])
            ->assertCreated();

        // Kembali jadi "bukan siapa-siapa" supaya header device yang menentukan.
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $id;
    }
}
