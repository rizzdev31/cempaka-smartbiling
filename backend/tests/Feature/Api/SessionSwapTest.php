<?php

namespace Tests\Feature\Api;

use App\Events\SessionSwapped;
use App\Models\BillingSession;
use App\Models\Package;
use App\Models\Station;
use App\Models\StationType;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Station Swap — API.md §7, PRD §15, R07, DEC-021.
 *
 * Yang diuji di sini bukan "swap berhasil", tapi **apa yang dijamin tidak
 * berubah**: session_id, end_at, items, payments. Itu isi R07.
 */
class SessionSwapTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private StationType $type;

    private Station $st01;

    private Station $st02;

    private Package $packageModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = $this->stationType('PS4 Slim');
        $this->st01 = $this->station($this->type, ['code' => 'ST01', 'name' => 'Station 1']);
        $this->st02 = $this->station($this->type, ['code' => 'ST02', 'name' => 'Station 2']);
        $this->packageModel = $this->package($this->type);

        $this->actingAs($this->operator(['name' => 'Budi']), 'sanctum');
    }

    private function activeSession(): BillingSession
    {
        $this->travelTo(Carbon::parse('2026-10-08T07:00:00Z'));

        /*
         * PREPAID yang sudah dibayar, bukan POSTPAID. Sejak DEC-034 Postpaid
         * tidak punya `end_at` sama sekali — padahal jaminan R07 yang diuji di
         * sini justru "end_at tidak berubah setelah swap".
         */
        $id = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->st01->id,
                'package_id' => $this->packageModel->id,
                'mode' => 'PREPAID',
            ])->json('data.id');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/payments", ['method' => 'CASH', 'amount' => 20000])
            ->assertCreated();

        return BillingSession::find($id);
    }

    private function doSwap(string $sessionId, string $targetId, array $headers = []): TestResponse
    {
        return $this->withHeaders($headers ?: $this->idempotent())
            ->postJson("/api/v1/sessions/{$sessionId}/swap", [
                'target_station_id' => $targetId,
                'reason' => 'TV bermasalah',
            ]);
    }

    public function test_swap_memindahkan_station_tanpa_mengubah_apa_pun_yang_lain(): void
    {
        $session = $this->activeSession();

        // Tambah F&B supaya ada item dan pembayaran yang bisa hilang kalau
        // implementasinya keliru membuat sesi baru.
        $teh = $this->fnbProduct();
        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$session->id}/fnb/orders", [
                'items' => [['product_id' => $teh->id, 'qty' => 1]],
            ])->assertCreated();

        $before = BillingSession::with(['items', 'payments'])->find($session->id);

        $this->travelTo(Carbon::parse('2026-10-08T07:30:00Z'));

        $this->doSwap($session->id, $this->st02->id)
            ->assertOk()
            ->assertJsonPath('data.station.code', 'ST02')
            // Inti R07: id dan end_at tidak boleh bergerak.
            ->assertJsonPath('data.id', $before->id)
            ->assertJsonPath('data.end_at', $before->end_at->toIso8601ZuluString())
            ->assertJsonPath('data.status', $before->status->value);

        $after = BillingSession::with(['items', 'payments'])->find($session->id);

        $this->assertSame($before->items->count(), $after->items->count());
        $this->assertSame($before->totals()->grandTotal(), $after->totals()->grandTotal());
    }

    public function test_station_lama_jadi_kosong_dan_station_baru_terpakai(): void
    {
        $session = $this->activeSession();

        $this->doSwap($session->id, $this->st02->id)->assertOk();

        $this->assertNull($this->st01->fresh()->currentSession());
        $this->assertSame($session->id, $this->st02->fresh()->currentSession()?->id);
    }

    public function test_tipe_konsol_berbeda_ditolak(): void
    {
        // DEC-021. Harga sesi sudah dibekukan saat dibuat; memindahkannya ke
        // tipe lain membuat customer bermain di PS5 dengan tarif PS4.
        $ps5 = $this->stationType('PS5 VIP');
        $st03 = $this->station($ps5, ['code' => 'ST03', 'name' => 'Station 3']);

        $session = $this->activeSession();

        $this->doSwap($session->id, $st03->id)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'STATION_TYPE_MISMATCH');
    }

    public function test_station_tujuan_sama_ditolak(): void
    {
        $session = $this->activeSession();

        $this->doSwap($session->id, $this->st01->id)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'TARGET_STATION_SAME');
    }

    public function test_station_tujuan_terpakai_ditolak(): void
    {
        $session = $this->activeSession();

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->st02->id,
                'package_id' => $this->packageModel->id,
                'mode' => 'POSTPAID',
            ])->assertCreated();

        $this->doSwap($session->id, $this->st02->id)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'STATION_HAS_ACTIVE_SESSION');
    }

    public function test_station_tujuan_maintenance_ditolak(): void
    {
        $session = $this->activeSession();
        $this->st02->update(['status' => 'MAINTENANCE']);

        $this->doSwap($session->id, $this->st02->id)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'STATION_NOT_AVAILABLE');
    }

    public function test_sesi_belum_dibayar_tidak_bisa_dipindah(): void
    {
        // PENDING_PAYMENT belum memakai TV mana pun.
        $id = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->st01->id,
                'package_id' => $this->packageModel->id,
                'mode' => 'PREPAID',
            ])->json('data.id');

        $this->doSwap($id, $this->st02->id)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SESSION_STATUS_INVALID');
    }

    public function test_swap_mengirim_event_ke_dua_station(): void
    {
        Event::fake([SessionSwapped::class]);

        $session = $this->activeSession();
        $this->doSwap($session->id, $this->st02->id)->assertOk();

        Event::assertDispatched(SessionSwapped::class, function (SessionSwapped $event) {
            // TV lama harus tahu untuk kembali idle, TV baru untuk mulai
            // menghitung — karena itu dua channel station (REALTIME.md §5).
            return $event->fromStationCode === 'ST01'
                && $event->toStationCode === 'ST02'
                && count($event->broadcastOn()) === 3;
        });
    }

    public function test_swap_tercatat_di_audit_dengan_asal_dan_tujuan(): void
    {
        $session = $this->activeSession();

        $this->doSwap($session->id, $this->st02->id)->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::SESSION_SWAPPED,
            'actor_name' => 'Budi',
            'subject_id' => $session->id,
        ]);
    }

    public function test_key_sama_tidak_memindah_dua_kali(): void
    {
        $session = $this->activeSession();
        $headers = $this->idempotent();

        $this->doSwap($session->id, $this->st02->id, $headers)->assertOk();
        $this->doSwap($session->id, $this->st02->id, $headers)->assertOk();

        $this->assertSame('ST02', BillingSession::find($session->id)->station->code);
    }
}
