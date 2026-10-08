<?php

namespace Tests\Feature\Api;

use App\Models\BillingSession;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/** `POST /sessions` — API.md §7. */
class SessionCreateTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private function create(array $payload, array $headers = []): TestResponse
    {
        return $this->withHeaders($headers ?: $this->idempotent())
            ->postJson('/api/v1/sessions', $payload);
    }

    public function test_prepaid_dibuat_menunggu_bayar_dan_timer_belum_jalan(): void
    {
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);
        $this->actingAs($this->operator(), 'sanctum');

        $response = $this->create([
            'station_id' => $station->id,
            'package_id' => $package->id,
            'mode' => 'PREPAID',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'PENDING_PAYMENT')
            // Timer Prepaid tidak boleh jalan sebelum dibayar (API.md §7).
            ->assertJsonPath('data.started_at', null)
            ->assertJsonPath('data.end_at', null)
            ->assertJsonPath('data.extendable', false)
            ->assertJsonPath('data.totals.rental', 20000)
            ->assertJsonPath('data.totals.balance_due', 20000);
    }

    public function test_postpaid_langsung_aktif_dengan_rental_belum_dibayar(): void
    {
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);
        $this->actingAs($this->operator(), 'sanctum');

        $response = $this->create([
            'station_id' => $station->id,
            'package_id' => $package->id,
            'mode' => 'POSTPAID',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'ACTIVE')
            // DEC-034 — Postpaid tidak punya batas waktu.
            ->assertJsonPath('data.end_at', null)
            ->assertJsonPath('data.extendable', false)
            /*
             * Belum ada baris rental: harganya baru pasti saat sesi ditutup.
             * Yang ditampilkan adalah tagihan berjalan — minimum satu blok
             * 30 menit (DEC-009), jadi 10.000 pada tarif 20.000/jam.
             */
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.totals.rental', 10000)
            ->assertJsonPath('data.totals.balance_due', 10000);

        $this->assertNotNull($response->json('data.started_at'));
    }

    public function test_harga_dibekukan_saat_session_dibuat(): void
    {
        // Owner menaikkan tarif di tengah sesi (DEC-019/020) tidak boleh
        // mengubah tagihan sesi yang sudah berjalan.
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);
        $this->actingAs($this->operator(), 'sanctum');

        $id = $this->create([
            'station_id' => $station->id,
            'package_id' => $package->id,
            'mode' => 'POSTPAID',
        ])->json('data.id');

        $package->update(['price' => 50000]);

        $this->assertSame(20000, (int) BillingSession::find($id)->package_price);
        $this->assertSame(20000, (int) BillingSession::find($id)->hourly_rate);
    }

    public function test_station_yang_sudah_terpakai_ditolak(): void
    {
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);
        $this->actingAs($this->operator(), 'sanctum');

        $this->create([
            'station_id' => $station->id, 'package_id' => $package->id, 'mode' => 'POSTPAID',
        ])->assertCreated();

        $this->create([
            'station_id' => $station->id, 'package_id' => $package->id, 'mode' => 'POSTPAID',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'STATION_HAS_ACTIVE_SESSION');
    }

    public function test_station_maintenance_ditolak(): void
    {
        $type = $this->stationType();
        $station = $this->station($type, ['status' => 'MAINTENANCE']);
        $package = $this->package($type);
        $this->actingAs($this->operator(), 'sanctum');

        $this->create([
            'station_id' => $station->id, 'package_id' => $package->id, 'mode' => 'POSTPAID',
        ])->assertStatus(409)
            ->assertJsonPath('error.code', 'STATION_NOT_AVAILABLE');
    }

    public function test_paket_tipe_konsol_lain_ditolak(): void
    {
        // DEC-019: paket PS4 di station PS5 akan membekukan tarif yang salah
        // untuk seluruh sesi, termasuk harga extend.
        $ps4 = $this->stationType('PS4 Slim');
        $ps5 = $this->stationType('PS5 VIP');
        $station = $this->station($ps5);
        $paketPs4 = $this->package($ps4);
        $this->actingAs($this->operator(), 'sanctum');

        $this->create([
            'station_id' => $station->id, 'package_id' => $paketPs4->id, 'mode' => 'POSTPAID',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_tanpa_customer_dicatat_sebagai_walk_in(): void
    {
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);
        $this->actingAs($this->operator(), 'sanctum');

        $this->create([
            'station_id' => $station->id, 'package_id' => $package->id, 'mode' => 'POSTPAID',
        ])->assertCreated()
            ->assertJsonPath('data.customer', null)
            ->assertJsonPath('data.customer_name', 'Walk-in');
    }

    public function test_member_dipakai_sebagai_customer(): void
    {
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);
        $member = $this->member('Siti');
        $this->actingAs($this->operator(), 'sanctum');

        $this->create([
            'station_id' => $station->id,
            'package_id' => $package->id,
            'mode' => 'POSTPAID',
            'customer_id' => $member->id,
        ])->assertCreated()
            ->assertJsonPath('data.customer.name', 'Siti')
            // DEC-008: satu FK tunggal — nama bebas tidak ikut diisi.
            ->assertJsonPath('data.customer_name', null);
    }

    public function test_tanpa_idempotency_key_ditolak(): void
    {
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);
        $this->actingAs($this->operator(), 'sanctum');

        $this->postJson('/api/v1/sessions', [
            'station_id' => $station->id, 'package_id' => $package->id, 'mode' => 'POSTPAID',
        ])->assertStatus(400)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
    }

    public function test_key_sama_tidak_membuat_session_kedua(): void
    {
        // Pertahanan utama terhadap operator yang menekan tombol dua kali (R06).
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);
        $this->actingAs($this->operator(), 'sanctum');

        $headers = $this->idempotent();
        $payload = ['station_id' => $station->id, 'package_id' => $package->id, 'mode' => 'POSTPAID'];

        $first = $this->create($payload, $headers)->assertCreated();
        $second = $this->create($payload, $headers)->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, BillingSession::query()->count());
    }

    public function test_pembuatan_session_tercatat_di_audit(): void
    {
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);
        $this->actingAs($this->operator(['name' => 'Budi']), 'sanctum');

        $this->create([
            'station_id' => $station->id, 'package_id' => $package->id, 'mode' => 'POSTPAID',
        ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::SESSION_CREATED,
            'actor_name' => 'Budi',
            'subject_type' => 'session',
        ]);
    }

    public function test_nomor_session_berurutan_per_hari(): void
    {
        $type = $this->stationType();
        $package = $this->package($type);
        $this->actingAs($this->operator(), 'sanctum');

        $a = $this->station($type, ['code' => 'ST01', 'name' => 'Station 1']);
        $b = $this->station($type, ['code' => 'ST02', 'name' => 'Station 2']);

        $first = $this->create(['station_id' => $a->id, 'package_id' => $package->id, 'mode' => 'POSTPAID']);
        $second = $this->create(['station_id' => $b->id, 'package_id' => $package->id, 'mode' => 'POSTPAID']);

        $this->assertStringEndsWith('-0001', $first->json('data.code'));
        $this->assertStringEndsWith('-0002', $second->json('data.code'));
    }
}
