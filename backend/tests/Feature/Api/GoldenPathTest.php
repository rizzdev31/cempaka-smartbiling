<?php

namespace Tests\Feature\Api;

use App\Enums\SessionStatus;
use App\Events\FnbOrderCreated;
use App\Events\PaymentConfirmed;
use App\Events\SessionExpired;
use App\Events\SessionExtended;
use App\Events\SessionStarted;
use App\Events\SessionSwapped;
use App\Events\SessionUpdated;
use App\Models\BillingSession;
use App\Models\CustomerCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Golden Path ST01 — CLAUDE.md §8, PRD §29, ROADMAP Tahap 0 exit criteria.
 *
 *     start -> timer -> F&B -> extend -> warning -> checkout -> completed
 *
 * Satu test yang menjalankan satu hari operasional dari awal sampai struk.
 * Ini yang menentukan Tahap 0 selesai atau belum — kalau test ini merah,
 * tidak ada gunanya mengejar enam TV.
 */
class GoldenPathTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    public function test_golden_path_satu_station_dari_start_sampai_completed(): void
    {
        Event::fake([
            SessionStarted::class, SessionUpdated::class, SessionExtended::class,
            SessionExpired::class, SessionSwapped::class, PaymentConfirmed::class,
            FnbOrderCreated::class,
        ]);

        $type = $this->stationType('PS4 Slim');
        $st01 = $this->station($type, ['code' => 'ST01', 'name' => 'Station 1']);
        $package = $this->package($type);              // 1 Jam / 20.000
        $teh = $this->fnbProduct(['name' => 'Teh Manis', 'price' => 5000]);
        $operator = $this->operator(['name' => 'Budi']);

        $this->actingAs($operator, 'sanctum');

        // ── 07:00 start, Prepaid ────────────────────────────────────────────
        $this->travelTo(Carbon::parse('2026-10-08T07:00:00Z'));

        $session = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $st01->id,
                'package_id' => $package->id,
                'mode' => 'PREPAID',
            ])->assertCreated()
            ->assertJsonPath('data.status', 'PENDING_PAYMENT')
            ->json('data');

        $id = $session['id'];

        // Timer belum jalan sebelum dibayar.
        Event::assertNotDispatched(SessionStarted::class);

        // ── 07:01 bayar rental -> timer mulai ───────────────────────────────
        $this->travelTo(Carbon::parse('2026-10-08T07:01:00Z'));

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/payments", ['method' => 'CASH', 'amount' => 20000])
            ->assertCreated()
            ->assertJsonPath('data.session.status', 'ACTIVE')
            ->assertJsonPath('data.session.end_at', '2026-10-08T08:01:00Z')
            ->assertJsonPath('data.session.totals.balance_due', 0);

        Event::assertDispatched(SessionStarted::class);
        Event::assertDispatched(PaymentConfirmed::class);

        // ── 07:20 F&B masuk Open Tab ────────────────────────────────────────
        $this->travelTo(Carbon::parse('2026-10-08T07:20:00Z'));

        $order = $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/fnb/orders", [
                'items' => [['product_id' => $teh->id, 'qty' => 2]],
                'note' => 'tanpa gula',
            ])->assertCreated()
            ->assertJsonPath('data.order.total', 10000)
            ->assertJsonPath('data.order.status', 'PENDING')
            // Rental sudah lunas; yang belum dibayar hanya F&B.
            ->assertJsonPath('data.session.totals.balance_due', 10000)
            ->json('data.order');

        Event::assertDispatched(FnbOrderCreated::class);

        // Antrian dapur bergerak sampai diantar.
        foreach (['PROCESSING', 'READY', 'DELIVERED'] as $status) {
            $this->postJson("/api/v1/fnb/orders/{$order['id']}/status", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('data.order.status', $status);
        }

        // ── 07:55 extend 30 menit ───────────────────────────────────────────
        $this->travelTo(Carbon::parse('2026-10-08T07:55:00Z'));

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/extend", ['duration_minutes' => 30])
            ->assertOk()
            ->assertJsonPath('data.extend.price', 10000)
            // end_at lama + 30, bukan dari waktu approve (DEC-007).
            ->assertJsonPath('data.extend.new_end_at', '2026-10-08T08:31:00Z');

        Event::assertDispatched(SessionExtended::class);

        // ── 08:25 scheduler menandai WARNING (sisa 6 menit) ─────────────────
        $this->travelTo(Carbon::parse('2026-10-08T08:25:00Z'));
        $this->artisan('sessions:reconcile')->assertSuccessful();

        $this->assertSame(SessionStatus::WARNING, BillingSession::find($id)->status);

        // ── 08:40 lewat end_at: EXPIRED, tapi sesi TIDAK berhenti (DEC-023) ─
        $this->travelTo(Carbon::parse('2026-10-08T08:40:00Z'));
        $this->artisan('sessions:reconcile')->assertSuccessful();

        $fresh = BillingSession::find($id);
        $this->assertSame(SessionStatus::EXPIRED, $fresh->status);
        $this->assertTrue($fresh->status->occupiesStation(), 'EXPIRED harus tetap memakai station');
        Event::assertDispatched(SessionExpired::class);

        // ── 08:45 checkout ──────────────────────────────────────────────────
        //
        // Hak waktu 90 menit (paket 60 + extend 30), mulai 07:01, jadi habis
        // 08:31. Selesai 08:45 -> aktual 104 menit -> lewat 14 menit ->
        // dibulatkan jadi 30 menit -> 10.000 (DEC-023).
        //
        // Tagihan: F&B 10.000 + extend 10.000 + overstay 10.000 = 30.000.
        $this->travelTo(Carbon::parse('2026-10-08T08:45:00Z'));

        $receipt = $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/checkout", [
                'payments' => [['method' => 'CASH', 'amount' => 30000]],
            ])->assertOk()
            ->assertJsonPath('data.session.status', 'COMPLETED')
            ->assertJsonPath('data.session.totals.balance_due', 0)
            ->assertJsonPath('data.receipt.actual_duration_minutes', 104)
            ->assertJsonPath('data.receipt.billable_duration_minutes', 120)
            ->json('data.receipt');

        // Rental prepaid tidak dihitung ulang (PRD §12).
        $this->assertSame(50000, $receipt['totals']['grand_total']);
        $this->assertStringStartsWith('INV-', $receipt['number']);

        // ── station kembali kosong ──────────────────────────────────────────
        $this->assertNull($st01->fresh()->currentSession());

        // Walk-in non-member: tidak ada saldo tersimpan (DEC-024).
        $this->assertSame(0, CustomerCredit::query()->count());
    }

    public function test_golden_path_member_menyimpan_sisa_waktu_lalu_memakainya(): void
    {
        // DEC-024 + DEC-026 — jalur kedua: berhenti lebih awal sebagai member.
        $type = $this->stationType();
        $st01 = $this->station($type);
        $package = $this->package($type);        // 60 menit / 20.000
        $member = $this->activeMember('Siti');

        $this->actingAs($this->operator(), 'sanctum');

        $this->travelTo(Carbon::parse('2026-10-08T07:00:00Z'));

        $id = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $st01->id,
                'package_id' => $package->id,
                'mode' => 'PREPAID',
                'customer_id' => $member->id,
            ])->assertCreated()->json('data.id');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/payments", ['method' => 'CASH', 'amount' => 20000])
            ->assertCreated();

        // Berhenti di menit ke-40 -> sisa 20 menit -> 6.666 masuk saldo.
        $this->travelTo(Carbon::parse('2026-10-08T07:40:00Z'));

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/checkout", ['payments' => []])
            ->assertOk()
            ->assertJsonPath('data.session.status', 'COMPLETED');

        $this->assertSame(6666, $member->fresh()->creditBalance());

        // ── sesi kedua: saldo dipakai menutup sebagian tagihan ──────────────
        $this->travelTo(Carbon::parse('2026-10-08T09:00:00Z'));

        $kedua = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $st01->id,
                'package_id' => $package->id,
                'mode' => 'POSTPAID',
                'customer_id' => $member->id,
            ])->assertCreated()->json('data.id');

        $this->travelTo(Carbon::parse('2026-10-08T10:00:00Z'));

        // Rental 20.000 dikurangi saldo 6.666 -> sisa bayar 13.334.
        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$kedua}/checkout", [
                'use_credit' => true,
                'payments' => [['method' => 'CASH', 'amount' => 13334]],
            ])->assertOk()
            ->assertJsonPath('data.session.totals.discount', -6666)
            ->assertJsonPath('data.session.totals.balance_due', 0);

        $this->assertSame(0, $member->fresh()->creditBalance());
    }
}
