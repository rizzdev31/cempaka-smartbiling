<?php

namespace Tests\Feature\Api;

use App\Enums\SessionItemType;
use App\Models\BillingSession;
use App\Models\Package;
use App\Models\Station;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/** `POST /sessions/{id}/payments` — API.md §7, PRD §21. */
class SessionPaymentTest extends TestCase
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

    private function makeSession(string $mode): array
    {
        return $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->stationModel->id,
                'package_id' => $this->packageModel->id,
                'mode' => $mode,
            ])->json('data');
    }

    private function pay(string $sessionId, array $payload, array $headers = []): TestResponse
    {
        return $this->withHeaders($headers ?: $this->idempotent())
            ->postJson("/api/v1/sessions/{$sessionId}/payments", $payload);
    }

    public function test_pembayaran_rental_menyalakan_timer_prepaid(): void
    {
        $session = $this->makeSession('PREPAID');

        $response = $this->pay($session['id'], ['method' => 'CASH', 'amount' => 20000]);

        $response->assertCreated()
            ->assertJsonPath('data.payment.method', 'CASH')
            ->assertJsonPath('data.payment.status', 'CONFIRMED')
            // Status, started_at, dan end_at berubah sekaligus — karena itu
            // session ikut dikirim balik, bukan hanya payment-nya.
            ->assertJsonPath('data.session.status', 'ACTIVE')
            ->assertJsonPath('data.session.totals.paid', 20000)
            ->assertJsonPath('data.session.totals.balance_due', 0);

        $this->assertNotNull($response->json('data.session.started_at'));
        $this->assertNotNull($response->json('data.session.end_at'));
    }

    public function test_end_at_prepaid_sepanjang_durasi_paket(): void
    {
        $session = $this->makeSession('PREPAID');

        $this->pay($session['id'], ['method' => 'CASH', 'amount' => 20000])->assertCreated();

        $fresh = BillingSession::find($session['id']);

        $this->assertSame(60, (int) $fresh->started_at->diffInMinutes($fresh->end_at));
    }

    public function test_rental_ditandai_lunas_setelah_dibayar(): void
    {
        $session = $this->makeSession('PREPAID');

        $this->pay($session['id'], ['method' => 'CASH', 'amount' => 20000])->assertCreated();

        $this->assertTrue(
            BillingSession::find($session['id'])
                ->items()
                ->where('type', SessionItemType::RENTAL->value)
                ->first()
                ->is_paid,
        );
    }

    public function test_pembayaran_melebihi_tagihan_ditolak(): void
    {
        // Kembalian tidak dicatat di V1 (API.md §7). Kelebihan yang masuk
        // membuat laporan kas tidak bisa dicocokkan.
        $session = $this->makeSession('PREPAID');

        $this->pay($session['id'], ['method' => 'CASH', 'amount' => 25000])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYMENT_AMOUNT_EXCEEDS_BALANCE')
            ->assertJsonPath('error.details.balance_due', 20000);
    }

    public function test_qris_tanpa_referensi_ditolak(): void
    {
        // QRIS statis tidak punya callback gateway — nomor referensi satu-satunya
        // bukti yang bisa dicocokkan saat rekonsiliasi.
        $session = $this->makeSession('PREPAID');

        $this->pay($session['id'], ['method' => 'QRIS_STATIC', 'amount' => 20000])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYMENT_REFERENCE_REQUIRED');
    }

    public function test_qris_dengan_referensi_diterima(): void
    {
        $session = $this->makeSession('PREPAID');

        $this->pay($session['id'], [
            'method' => 'QRIS_STATIC',
            'amount' => 20000,
            'reference' => 'TRX-998877',
        ])->assertCreated()
            ->assertJsonPath('data.payment.reference', 'TRX-998877')
            ->assertJsonPath('data.session.status', 'ACTIVE');
    }

    public function test_pembayaran_sebagian_belum_menyalakan_timer(): void
    {
        // PRD §11 menaruh PENDING_PAYMENT justru supaya station tidak terpakai
        // oleh sesi yang belum lunas.
        $session = $this->makeSession('PREPAID');

        $this->pay($session['id'], ['method' => 'CASH', 'amount' => 5000])
            ->assertCreated()
            ->assertJsonPath('data.session.status', 'PENDING_PAYMENT')
            ->assertJsonPath('data.session.started_at', null)
            ->assertJsonPath('data.session.totals.balance_due', 15000);
    }

    public function test_sisa_pembayaran_menyalakan_timer(): void
    {
        $session = $this->makeSession('PREPAID');

        $this->pay($session['id'], ['method' => 'CASH', 'amount' => 5000])->assertCreated();

        $this->pay($session['id'], ['method' => 'CASH', 'amount' => 15000])
            ->assertCreated()
            ->assertJsonPath('data.session.status', 'ACTIVE')
            ->assertJsonPath('data.session.totals.paid', 20000);
    }

    public function test_key_sama_tidak_mencatat_pembayaran_dua_kali(): void
    {
        // R06 — pertahanan utama terhadap duplicate payment.
        $session = $this->makeSession('PREPAID');
        $headers = $this->idempotent();

        $this->pay($session['id'], ['method' => 'CASH', 'amount' => 20000], $headers)->assertCreated();
        $this->pay($session['id'], ['method' => 'CASH', 'amount' => 20000], $headers)->assertCreated();

        $this->assertSame(1, BillingSession::find($session['id'])->payments()->count());
    }

    public function test_tanpa_idempotency_key_ditolak(): void
    {
        $session = $this->makeSession('PREPAID');

        // withHeaders() Laravel bertahan antar request dalam satu test, jadi
        // Idempotency-Key dari pembuatan sesi masih menempel. Tanpa flush,
        // yang diuji jadi "key dipakai ulang" (409), bukan "key tidak ada" (400).
        $this->flushHeaders();

        $this->postJson("/api/v1/sessions/{$session['id']}/payments", [
            'method' => 'CASH', 'amount' => 20000,
        ])->assertStatus(400)
            ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REQUIRED');
    }

    public function test_pembayaran_tercatat_di_audit(): void
    {
        $session = $this->makeSession('PREPAID');

        $this->pay($session['id'], ['method' => 'CASH', 'amount' => 20000])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PAYMENT_CONFIRMED,
            'actor_name' => 'Budi',
            'subject_id' => $session['id'],
        ]);
    }

    public function test_timer_mulai_tercatat_terpisah_di_audit(): void
    {
        // Prepaid dibuat lebih dulu dan baru aktif setelah dibayar, jadi
        // "sesi dibuat" dan "timer mulai" adalah dua kejadian berbeda.
        $session = $this->makeSession('PREPAID');

        $this->pay($session['id'], ['method' => 'CASH', 'amount' => 20000])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::SESSION_ACTIVATED,
            'subject_id' => $session['id'],
        ]);
    }
}
