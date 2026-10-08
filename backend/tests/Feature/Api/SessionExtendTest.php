<?php

namespace Tests\Feature\Api;

use App\Enums\SessionStatus;
use App\Models\BillingSession;
use App\Models\Package;
use App\Models\Station;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * `POST /sessions/{id}/extend` — API.md §7, DEC-007.
 *
 * Paket default 1 jam / 20.000, jadi `hourly_rate` = 20.000 dan harga extend
 * 30 menit = 10.000. Angka itu muncul berulang di bawah.
 */
class SessionExtendTest extends TestCase
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

    /** Sesi Postpaid yang langsung ACTIVE, mulai tepat pada waktu yang diberikan. */
    private function activeSession(Carbon $startedAt): BillingSession
    {
        $this->travelTo($startedAt);

        /*
         * PREPAID, bukan POSTPAID. Sejak DEC-034 Postpaid tidak punya `end_at`
         * sama sekali — tidak ada yang bisa diperpanjang maupun dipindahkan
         * batasnya, jadi sesi bertimer harus datang dari Prepaid yang sudah
         * dibayar.
         */
        $id = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->stationModel->id,
                'package_id' => $this->packageModel->id,
                'mode' => 'PREPAID',
            ])->json('data.id');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/payments", ['method' => 'CASH', 'amount' => 20000])
            ->assertCreated();

        return BillingSession::find($id);
    }

    private function extend(string $sessionId, int $minutes, array $headers = []): TestResponse
    {
        return $this->withHeaders($headers ?: $this->idempotent())
            ->postJson("/api/v1/sessions/{$sessionId}/extend", ['duration_minutes' => $minutes]);
    }

    public function test_extend_tiga_puluh_menit_harganya_setengah_tarif_per_jam(): void
    {
        $session = $this->activeSession(Carbon::parse('2026-10-08T07:00:00Z'));

        $this->travelTo(Carbon::parse('2026-10-08T07:30:00Z'));

        $this->extend($session->id, 30)
            ->assertOk()
            ->assertJsonPath('data.extend.duration_minutes', 30)
            ->assertJsonPath('data.extend.price', 10000)
            ->assertJsonPath('data.session.totals.extend', 10000)
            // Rental Prepaid sudah lunas; extend masuk Open Tab dan dibayar
            // saat checkout (PRD §12).
            ->assertJsonPath('data.session.totals.balance_due', 10000);
    }

    public function test_end_at_baru_dihitung_dari_end_at_lama(): void
    {
        // Inti DEC-007 yang TIDAK dicabut DEC-033. Operator menekan tombol 2
        // menit sebelum habis; customer dapat 30 menit dari end_at lama,
        // bukan 32 menit.
        $session = $this->activeSession(Carbon::parse('2026-10-08T07:00:00Z'));

        $this->travelTo(Carbon::parse('2026-10-08T07:58:00Z'));

        $this->extend($session->id, 30)
            ->assertOk()
            ->assertJsonPath('data.extend.previous_end_at', '2026-10-08T08:00:00Z')
            ->assertJsonPath('data.extend.new_end_at', '2026-10-08T08:30:00Z');
    }

    public function test_durasi_bukan_kelipatan_tiga_puluh_ditolak(): void
    {
        $session = $this->activeSession(Carbon::parse('2026-10-08T07:00:00Z'));

        $this->travelTo(Carbon::parse('2026-10-08T07:30:00Z'));

        $this->extend($session->id, 45)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'EXTEND_DURATION_INVALID');

        $this->extend($session->id, 20)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'EXTEND_DURATION_INVALID');
    }

    public function test_masih_boleh_tepat_di_detik_waktu_habis(): void
    {
        $session = $this->activeSession(Carbon::parse('2026-10-08T07:00:00Z'));

        // Batasnya `now <= end_at` — tepat di 08:00:00 masih boleh.
        $this->travelTo(Carbon::parse('2026-10-08T08:00:00Z'));

        $this->extend($session->id, 30)->assertOk();
    }

    public function test_ditolak_begitu_waktunya_lewat(): void
    {
        // DEC-033 mencabut grace 10 menit: "habis ya habis". Lewat satu detik
        // pun ditolak.
        $session = $this->activeSession(Carbon::parse('2026-10-08T07:00:00Z'));

        $this->travelTo(Carbon::parse('2026-10-08T08:00:01Z'));

        $this->extend($session->id, 30)
            ->assertStatus(409)
            // Nama error code-nya sengaja tidak diubah supaya client yang sudah
            // menyalin daftar error tidak patah; artinya sekarang "waktunya
            // sudah lewat".
            ->assertJsonPath('error.code', 'EXTEND_GRACE_EXPIRED')
            ->assertJsonPath('error.details.grace_until', '2026-10-08T08:00:00Z');
    }

    public function test_sesi_yang_sudah_habis_tidak_bisa_dihidupkan_lagi(): void
    {
        // DEC-033. Customer yang ingin melanjutkan dibuatkan SESI BARU dengan
        // paket baru, bukan diperpanjang.
        $session = $this->activeSession(Carbon::parse('2026-10-08T07:00:00Z'));
        $session->update(['status' => SessionStatus::EXPIRED]);

        $this->travelTo(Carbon::parse('2026-10-08T08:05:00Z'));

        $this->extend($session->id, 30)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SESSION_STATUS_INVALID');
    }

    public function test_extend_dua_kali_menumpuk(): void
    {
        $session = $this->activeSession(Carbon::parse('2026-10-08T07:00:00Z'));

        $this->travelTo(Carbon::parse('2026-10-08T07:30:00Z'));
        $this->extend($session->id, 30)->assertOk();

        $this->extend($session->id, 60)
            ->assertOk()
            ->assertJsonPath('data.extend.new_end_at', '2026-10-08T09:30:00Z')
            ->assertJsonPath('data.session.totals.extend', 30000);
    }

    public function test_hak_waktu_bertambah_setelah_extend(): void
    {
        // Dasar perhitungan overstay DEC-023: paket 60 + extend 30 = 90 menit.
        $session = $this->activeSession(Carbon::parse('2026-10-08T07:00:00Z'));

        $this->travelTo(Carbon::parse('2026-10-08T07:30:00Z'));
        $this->extend($session->id, 30)->assertOk();

        $this->assertSame(90, BillingSession::find($session->id)->entitledMinutes());
    }

    public function test_sesi_belum_dibayar_tidak_bisa_diextend(): void
    {
        // PENDING_PAYMENT belum punya end_at — tidak ada yang bisa digeser.
        $id = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->stationModel->id,
                'package_id' => $this->packageModel->id,
                'mode' => 'PREPAID',
            ])->json('data.id');

        $this->extend($id, 30)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SESSION_STATUS_INVALID');
    }

    public function test_key_sama_tidak_menambah_waktu_dua_kali(): void
    {
        $session = $this->activeSession(Carbon::parse('2026-10-08T07:00:00Z'));
        $this->travelTo(Carbon::parse('2026-10-08T07:30:00Z'));

        $headers = $this->idempotent();

        $this->extend($session->id, 30, $headers)->assertOk();
        $this->extend($session->id, 30, $headers)->assertOk();

        $fresh = BillingSession::find($session->id);

        $this->assertSame('2026-10-08T08:30:00Z', $fresh->end_at->toIso8601ZuluString());
        $this->assertSame(10000, $fresh->totals()->extend);
    }

    public function test_extend_tercatat_di_audit_dengan_end_at_sebelum_dan_sesudah(): void
    {
        $session = $this->activeSession(Carbon::parse('2026-10-08T07:00:00Z'));
        $this->travelTo(Carbon::parse('2026-10-08T07:30:00Z'));

        $this->extend($session->id, 30)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::SESSION_EXTENDED,
            'actor_name' => 'Budi',
            'subject_id' => $session->id,
        ]);
    }

    public function test_extendable_dan_deadline_dikirim_server(): void
    {
        // Aturan kapan boleh extend tidak boleh diduplikasi di Flutter/Kotlin.
        $session = $this->activeSession(Carbon::parse('2026-10-08T07:00:00Z'));

        $this->travelTo(Carbon::parse('2026-10-08T07:30:00Z'));

        $this->getJson("/api/v1/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.extendable', true)
            // Sejak DEC-033 deadline-nya sama persis dengan end_at.
            ->assertJsonPath('data.extend_deadline_at', '2026-10-08T08:00:00Z');

        $this->travelTo(Carbon::parse('2026-10-08T08:00:01Z'));

        $this->getJson("/api/v1/sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('data.extendable', false);
    }
}
