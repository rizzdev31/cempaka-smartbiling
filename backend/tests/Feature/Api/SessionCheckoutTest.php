<?php

namespace Tests\Feature\Api;

use App\Enums\SessionStatus;
use App\Models\BillingSession;
use App\Models\CustomerCredit;
use App\Models\Package;
use App\Models\Station;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/** Checkout — API.md §7, PRD §12, T13, DEC-023, DEC-024, DEC-026. */
class SessionCheckoutTest extends TestCase
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

        $this->actingAs($this->operator(['name' => 'Budi']), 'sanctum');
    }

    /** Sesi yang sudah berjalan, mulai pada 07:00. */
    private function running(string $mode, ?string $customerId = null): string
    {
        $this->travelTo(Carbon::parse('2026-10-08T07:00:00Z'));

        $id = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', array_filter([
                'station_id' => $this->stationModel->id,
                'package_id' => $this->packageModel->id,
                'mode' => $mode,
                'customer_id' => $customerId,
            ]))->json('data.id');

        if ($mode === 'PREPAID') {
            $this->withHeaders($this->idempotent())
                ->postJson("/api/v1/sessions/{$id}/payments", ['method' => 'CASH', 'amount' => 20000])
                ->assertCreated();
        }

        return $id;
    }

    private function checkout(string $id, array $body, array $headers = []): TestResponse
    {
        return $this->withHeaders($headers ?: $this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/checkout", $body + ['payments' => []]);
    }

    public function test_postpaid_ditagih_dari_durasi_aktual_dengan_rounding(): void
    {
        $id = $this->running('POSTPAID');

        // 63 menit -> sisa 3 menit, toleransi DEC-009 -> ditagih 60 menit.
        $this->travelTo(Carbon::parse('2026-10-08T08:03:00Z'));

        $this->checkout($id, ['payments' => [['method' => 'CASH', 'amount' => 20000]]])
            ->assertOk()
            ->assertJsonPath('data.session.status', 'COMPLETED')
            ->assertJsonPath('data.receipt.actual_duration_minutes', 63)
            ->assertJsonPath('data.receipt.billable_duration_minutes', 60)
            ->assertJsonPath('data.receipt.totals.grand_total', 20000);
    }

    public function test_prepaid_berhenti_awal_tetap_bayar_paket_penuh(): void
    {
        // DEC-024 — sisa hangus untuk non-member.
        $id = $this->running('PREPAID');

        $this->travelTo(Carbon::parse('2026-10-08T07:40:00Z'));

        $this->checkout($id, ['payments' => []])
            ->assertOk()
            ->assertJsonPath('data.receipt.actual_duration_minutes', 40)
            ->assertJsonPath('data.receipt.totals.grand_total', 20000)
            ->assertJsonPath('data.receipt.totals.balance_due', 0);

        $this->assertSame(0, CustomerCredit::query()->count());
    }

    public function test_prepaid_overstay_ditagih_sebagai_baris_terpisah(): void
    {
        // DEC-023. Main 95 menit dengan paket 60 -> lewat 35 -> dibulatkan
        // jadi 30 menit -> 10.000.
        $id = $this->running('PREPAID');

        $this->travelTo(Carbon::parse('2026-10-08T08:35:00Z'));

        $response = $this->checkout($id, ['payments' => [['method' => 'CASH', 'amount' => 10000]]])
            ->assertOk()
            ->assertJsonPath('data.receipt.actual_duration_minutes', 95)
            ->assertJsonPath('data.receipt.billable_duration_minutes', 90)
            // Rental prepaid TIDAK dihitung ulang (PRD §12).
            ->assertJsonPath('data.session.totals.rental', 20000)
            ->assertJsonPath('data.session.totals.adjustment', 10000);

        $this->assertStringContainsString(
            'Kelebihan waktu',
            collect($response->json('data.receipt.lines'))->pluck('name')->implode(' | '),
        );
    }

    public function test_lewat_dalam_toleransi_tidak_ditagih(): void
    {
        // Lewat 4 menit — toleransi 5 menit DEC-009 (OD-021).
        $id = $this->running('PREPAID');

        $this->travelTo(Carbon::parse('2026-10-08T08:04:00Z'));

        $this->checkout($id, ['payments' => []])
            ->assertOk()
            ->assertJsonPath('data.session.totals.adjustment', 0)
            ->assertJsonPath('data.receipt.totals.grand_total', 20000);
    }

    public function test_pembayaran_kurang_ditolak(): void
    {
        $id = $this->running('POSTPAID');
        $this->travelTo(Carbon::parse('2026-10-08T08:00:00Z'));

        $this->checkout($id, ['payments' => [['method' => 'CASH', 'amount' => 15000]]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'CHECKOUT_INSUFFICIENT_PAYMENT');

        // Sesi tidak boleh ikut tertutup kalau pembayarannya ditolak.
        $this->assertNotSame(SessionStatus::COMPLETED, BillingSession::find($id)->status);
    }

    public function test_pembayaran_lebih_ditolak_karena_tidak_ada_kembalian(): void
    {
        $id = $this->running('POSTPAID');
        $this->travelTo(Carbon::parse('2026-10-08T08:00:00Z'));

        $this->checkout($id, ['payments' => [['method' => 'CASH', 'amount' => 25000]]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYMENT_AMOUNT_EXCEEDS_BALANCE');
    }

    public function test_qris_tanpa_referensi_ditolak(): void
    {
        $id = $this->running('POSTPAID');
        $this->travelTo(Carbon::parse('2026-10-08T08:00:00Z'));

        $this->checkout($id, ['payments' => [['method' => 'QRIS_STATIC', 'amount' => 20000]]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'PAYMENT_REFERENCE_REQUIRED');
    }

    public function test_member_mendapat_saldo_dari_sisa_waktu(): void
    {
        // DEC-026 — sisa 20 menit pada tarif 20.000/jam = 6.666.
        $member = $this->activeMember();
        $id = $this->running('PREPAID', $member->id);

        $this->travelTo(Carbon::parse('2026-10-08T07:40:00Z'));

        $this->checkout($id, ['payments' => []])->assertOk();

        $this->assertSame(6666, $member->fresh()->creditBalance());
        $this->assertDatabaseHas('customer_credits', [
            'customer_id' => $member->id,
            'type' => 'EARNED',
            'amount' => 6666,
        ]);
    }

    public function test_saldo_mengurangi_tagihan_apa_pun(): void
    {
        // DEC-026 — "digabungkan dengan tambahan biling lainnya", jadi saldo
        // juga boleh menutup F&B, bukan hanya rental.
        $member = $this->activeMember();
        CustomerCredit::query()->create([
            'customer_id' => $member->id,
            'type' => 'EARNED',
            'amount' => 5000,
        ]);

        $id = $this->running('POSTPAID', $member->id);

        $teh = $this->fnbProduct(['price' => 5000]);
        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/fnb/orders", [
                'items' => [['product_id' => $teh->id, 'qty' => 1]],
            ])->assertCreated();

        $this->travelTo(Carbon::parse('2026-10-08T08:00:00Z'));

        // rental 20.000 + F&B 5.000 - saldo 5.000 = 20.000.
        $this->checkout($id, [
            'use_credit' => true,
            'payments' => [['method' => 'CASH', 'amount' => 20000]],
        ])->assertOk()
            ->assertJsonPath('data.session.totals.discount', -5000)
            ->assertJsonPath('data.session.totals.balance_due', 0);

        $this->assertSame(0, $member->fresh()->creditBalance());
    }

    public function test_non_member_tidak_mendapat_saldo(): void
    {
        $walkIn = $this->member('Tanpa Kartu');   // customer tanpa membership
        $id = $this->running('PREPAID', $walkIn->id);

        $this->travelTo(Carbon::parse('2026-10-08T07:40:00Z'));
        $this->checkout($id, ['payments' => []])->assertOk();

        $this->assertSame(0, $walkIn->fresh()->creditBalance());
    }

    public function test_station_kembali_kosong_setelah_checkout(): void
    {
        $id = $this->running('POSTPAID');
        $this->travelTo(Carbon::parse('2026-10-08T08:00:00Z'));

        $this->checkout($id, ['payments' => [['method' => 'CASH', 'amount' => 20000]]])->assertOk();

        $this->assertNull($this->stationModel->fresh()->currentSession());
    }

    public function test_sesi_selesai_tidak_bisa_checkout_lagi(): void
    {
        $id = $this->running('POSTPAID');
        $this->travelTo(Carbon::parse('2026-10-08T08:00:00Z'));

        $this->checkout($id, ['payments' => [['method' => 'CASH', 'amount' => 20000]]])->assertOk();

        $this->checkout($id, ['payments' => []])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SESSION_STATUS_INVALID');
    }

    public function test_checkout_tercatat_di_audit_dengan_nomor_struk(): void
    {
        $id = $this->running('POSTPAID');
        $this->travelTo(Carbon::parse('2026-10-08T08:00:00Z'));

        $this->checkout($id, ['payments' => [['method' => 'CASH', 'amount' => 20000]]])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::SESSION_CHECKOUT,
            'actor_name' => 'Budi',
            'subject_id' => $id,
        ]);
    }
}
