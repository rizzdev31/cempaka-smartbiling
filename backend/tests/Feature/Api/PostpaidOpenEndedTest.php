<?php

namespace Tests\Feature\Api;

use App\Enums\SessionStatus;
use App\Models\BillingSession;
use App\Models\Package;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Postpaid tanpa batas waktu — DEC-034.
 *
 * "Main dulu berapapun, lalu kalau mau bayar baru TV-nya mati."
 *
 * Yang diuji di sini adalah hal yang paling mudah salah: bahwa sesi Postpaid
 * TIDAK pernah berhenti sendiri, dan bahwa tagihan yang dilihat operator ikut
 * bertambah seiring waktu — bukan diam di harga paket.
 */
class PostpaidOpenEndedTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private Station $stationModel;

    private Package $packageModel;

    protected function setUp(): void
    {
        parent::setUp();

        $type = $this->stationType();
        $this->stationModel = $this->station($type);
        $this->packageModel = $this->package($type);   // 60 menit / 20.000

        $this->actingAs($this->operator(), 'sanctum');
    }

    private function start(): string
    {
        $this->travelTo(Carbon::parse('2026-10-08T07:00:00Z'));

        return $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->stationModel->id,
                'package_id' => $this->packageModel->id,
                'mode' => 'POSTPAID',
            ])->assertCreated()->json('data.id');
    }

    public function test_postpaid_tidak_punya_batas_waktu(): void
    {
        $id = $this->start();

        $this->getJson("/api/v1/sessions/{$id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.end_at', null)
            ->assertJsonPath('data.extend_deadline_at', null)
            ->assertJsonPath('data.extendable', false);
    }

    public function test_tidak_pernah_jadi_warning_atau_expired(): void
    {
        // Inti DEC-034. Tiga jam lewat durasi paket, scheduler dijalankan
        // berkali-kali, sesinya tetap hidup.
        $id = $this->start();

        foreach (['08:30', '10:00', '13:00'] as $jam) {
            $this->travelTo(Carbon::parse("2026-10-08T{$jam}:00Z"));
            $this->artisan('sessions:reconcile')->assertSuccessful();
        }

        $this->assertSame(SessionStatus::ACTIVE, BillingSession::find($id)->status);
    }

    public function test_tagihan_berjalan_bertambah_seiring_waktu(): void
    {
        /*
         * Kalau baris rental dibuat di awal dengan harga paket, angka ini akan
         * diam di 20.000 sejak menit pertama — dan operator menagihkannya ke
         * customer yang baru main 5 menit.
         */
        $id = $this->start();

        // Menit ke-0: minimum satu blok 30 menit (DEC-009) = 10.000.
        $this->assertSame(10000, BillingSession::find($id)->totals()->rental);

        // Menit ke-45: dibulatkan ke 60 menit = 20.000.
        $this->travelTo(Carbon::parse('2026-10-08T07:45:00Z'));
        $this->assertSame(20000, BillingSession::find($id)->totals()->rental);

        // Menit ke-90 = 30.000.
        $this->travelTo(Carbon::parse('2026-10-08T08:30:00Z'));
        $this->assertSame(30000, BillingSession::find($id)->totals()->rental);
    }

    public function test_baris_rental_belum_ada_sampai_checkout(): void
    {
        $id = $this->start();

        $this->assertSame(0, BillingSession::find($id)->items()->count());

        $this->travelTo(Carbon::parse('2026-10-08T08:30:00Z'));

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/checkout", [
                'payments' => [['method' => 'CASH', 'amount' => 30000]],
            ])->assertOk()
            ->assertJsonPath('data.receipt.lines.0.name', 'Paket 1 Jam (90 menit)')
            ->assertJsonPath('data.receipt.lines.0.subtotal', 30000);
    }

    public function test_tidak_bisa_diextend(): void
    {
        // Tidak ada `end_at` yang bisa digeser — Postpaid sudah tak terbatas.
        $id = $this->start();

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/extend", ['duration_minutes' => 30])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SESSION_STATUS_INVALID');
    }

    public function test_checkout_menagih_waktu_aktual(): void
    {
        $id = $this->start();

        // Main 3 jam 5 menit -> 185 menit -> sisa 5, toleransi DEC-009 ->
        // 180 menit -> 60.000.
        $this->travelTo(Carbon::parse('2026-10-08T10:05:00Z'));

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/checkout", [
                'payments' => [['method' => 'CASH', 'amount' => 60000]],
            ])->assertOk()
            ->assertJsonPath('data.receipt.actual_duration_minutes', 185)
            ->assertJsonPath('data.receipt.billable_duration_minutes', 180)
            ->assertJsonPath('data.receipt.totals.grand_total', 60000);
    }

    public function test_fnb_tetap_bisa_dipesan_kapan_pun(): void
    {
        // Sesi tidak pernah keluar dari ACTIVE, jadi dapur tidak pernah
        // menolak pesanan karena "sesi sudah habis".
        $id = $this->start();
        $teh = $this->fnbProduct();

        $this->travelTo(Carbon::parse('2026-10-08T12:00:00Z'));

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/fnb/orders", [
                'items' => [['product_id' => $teh->id, 'qty' => 1]],
            ])->assertCreated();
    }

    public function test_station_tetap_terpakai_sampai_checkout(): void
    {
        $id = $this->start();

        $this->travelTo(Carbon::parse('2026-10-08T12:00:00Z'));
        $this->artisan('sessions:reconcile')->assertSuccessful();

        $this->assertSame($id, $this->stationModel->fresh()->currentSession()?->id);
    }
}
