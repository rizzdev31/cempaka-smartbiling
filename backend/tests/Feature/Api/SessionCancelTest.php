<?php

namespace Tests\Feature\Api;

use App\Enums\SessionStatus;
use App\Models\BillingSession;
use App\Models\Package;
use App\Models\Station;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/** `POST /sessions/{id}/cancel` — API.md §7. */
class SessionCancelTest extends TestCase
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
        $this->packageModel = $this->package($type);

        $this->actingAs($this->operator(['name' => 'Budi']), 'sanctum');
    }

    private function makeSession(string $mode): string
    {
        return $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->stationModel->id,
                'package_id' => $this->packageModel->id,
                'mode' => $mode,
            ])->json('data.id');
    }

    private function cancel(string $id, array $headers = []): TestResponse
    {
        return $this->withHeaders($headers ?: $this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/cancel", ['reason' => 'Customer berubah pikiran']);
    }

    public function test_sesi_belum_dibayar_bisa_dibatalkan(): void
    {
        $id = $this->makeSession('PREPAID');

        $this->cancel($id)
            ->assertOk()
            ->assertJsonPath('data.status', 'CANCELLED');

        $this->assertSame(SessionStatus::CANCELLED, BillingSession::find($id)->status);
    }

    public function test_station_langsung_kosong_setelah_dibatalkan(): void
    {
        $id = $this->makeSession('PREPAID');

        $this->assertNotNull($this->stationModel->fresh()->currentSession());

        $this->cancel($id)->assertOk();

        $this->assertNull($this->stationModel->fresh()->currentSession());
    }

    public function test_station_bisa_langsung_dipakai_sesi_baru(): void
    {
        // Operator salah memilih station; dia harus bisa segera mengulang.
        $id = $this->makeSession('PREPAID');
        $this->cancel($id)->assertOk();

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->stationModel->id,
                'package_id' => $this->packageModel->id,
                'mode' => 'PREPAID',
            ])->assertCreated();
    }

    public function test_alasan_tersimpan(): void
    {
        $id = $this->makeSession('PREPAID');

        $this->cancel($id)->assertOk();

        $this->assertSame('Customer berubah pikiran', BillingSession::find($id)->cancel_reason);
    }

    public function test_sesi_yang_sudah_berjalan_tidak_bisa_dibatalkan(): void
    {
        // Sesi berjalan diselesaikan lewat checkout. Kalau boleh dibatalkan,
        // uang yang sudah masuk dan F&B yang sudah keluar kehilangan jejak.
        $id = $this->makeSession('POSTPAID');

        $this->cancel($id)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SESSION_STATUS_INVALID');
    }

    public function test_sesi_yang_sudah_menerima_uang_tidak_bisa_dibatalkan(): void
    {
        /*
         * Pembayaran sebagian memang mungkin: customer membayar 5.000 dari
         * 20.000 lalu berubah pikiran. V1 tidak punya refund, jadi
         * membatalkannya akan meninggalkan uang yang tidak terhubung ke
         * transaksi mana pun.
         */
        $id = $this->makeSession('PREPAID');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/payments", ['method' => 'CASH', 'amount' => 5000])
            ->assertCreated();

        $this->cancel($id)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SESSION_HAS_PAYMENT')
            ->assertJsonPath('error.details.paid', 5000);

        // Sesinya harus tetap hidup supaya kasir bisa menyelesaikannya.
        $this->assertSame(SessionStatus::PENDING_PAYMENT, BillingSession::find($id)->status);
    }

    public function test_sesi_berjalan_yang_sudah_dibayar_memberi_alasan_yang_benar(): void
    {
        /*
         * Ditemukan saat menjalankan golden path, bukan oleh test: sesi ACTIVE
         * hampir selalu juga sudah dibayar. Kalau pemeriksaan uang didahulukan,
         * operator menerima "sesi sudah menerima pembayaran" padahal alasan
         * sebenarnya "sesi sudah berjalan, selesaikan lewat checkout" — dan dia
         * akan mencari uangnya alih-alih menekan tombol yang benar.
         */
        $id = $this->makeSession('PREPAID');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/payments", ['method' => 'CASH', 'amount' => 20000])
            ->assertCreated();

        $this->cancel($id)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SESSION_STATUS_INVALID');
    }
    public function test_sesi_yang_sudah_dibatalkan_tidak_bisa_dibatalkan_lagi(): void
    {
        $id = $this->makeSession('PREPAID');
        $this->cancel($id)->assertOk();

        $this->cancel($id)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SESSION_STATUS_INVALID');
    }

    public function test_tanpa_idempotency_key_ditolak(): void
    {
        $id = $this->makeSession('PREPAID');
        $this->flushHeaders();

        $this->postJson("/api/v1/sessions/{$id}/cancel", [])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
    }

    public function test_pembatalan_tercatat_di_audit(): void
    {
        $id = $this->makeSession('PREPAID');

        $this->cancel($id)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::SESSION_CANCELLED,
            'actor_name' => 'Budi',
            'subject_id' => $id,
        ]);
    }
}
