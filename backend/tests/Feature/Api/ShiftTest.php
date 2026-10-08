<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\BillingSession;
use App\Models\Payment;
use App\Models\Shift;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Shift kasir — API.md §10, PRD §20.
 *
 * Yang paling penting di sini bukan "shift bisa dibuka", tapi bahwa
 * `shift_id` benar-benar menempel pada payment. Tanpa itu, uang yang masuk
 * tidak bisa dihubungkan ke siapa yang jaga.
 */
class ShiftTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private function open(array $body = [], array $headers = []): TestResponse
    {
        return $this->withHeaders($headers ?: $this->idempotent())
            ->postJson('/api/v1/shifts/open', $body + ['opening_cash' => 200000]);
    }

    public function test_buka_shift(): void
    {
        $this->actingAs($this->operator(['name' => 'Budi']), 'sanctum');

        $this->open()
            ->assertCreated()
            ->assertJsonPath('data.operator.name', 'Budi')
            ->assertJsonPath('data.opening_cash', 200000)
            ->assertJsonPath('data.closed_at', null)
            ->assertJsonPath('data.summary.total', 0);
    }

    public function test_tidak_boleh_punya_dua_shift_terbuka(): void
    {
        // Kalau boleh, payment-nya akan masuk ke shift mana pun yang kebetulan
        // ditemukan lebih dulu.
        $this->actingAs($this->operator(), 'sanctum');

        $this->open()->assertCreated();

        $this->open()
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SHIFT_ALREADY_OPEN');
    }

    public function test_shift_current_null_sebelum_dibuka(): void
    {
        // "Belum buka shift" adalah keadaan normal di awal hari, bukan error.
        $this->actingAs($this->operator(), 'sanctum');

        $this->getJson('/api/v1/shifts/current')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_shift_current_mengembalikan_shift_terbuka(): void
    {
        $this->actingAs($this->operator(), 'sanctum');
        $this->open()->assertCreated();

        $this->getJson('/api/v1/shifts/current')
            ->assertOk()
            ->assertJsonPath('data.opening_cash', 200000);
    }

    public function test_payment_menempel_ke_shift_yang_terbuka(): void
    {
        // Inti dari seluruh fitur ini.
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);

        $this->actingAs($this->operator(), 'sanctum');
        $shiftId = $this->open()->json('data.id');

        $sessionId = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $station->id,
                'package_id' => $package->id,
                'mode' => 'PREPAID',
            ])->json('data.id');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$sessionId}/payments", ['method' => 'CASH', 'amount' => 20000])
            ->assertCreated();

        $this->assertSame($shiftId, Payment::query()->first()->shift_id);
        $this->assertSame($shiftId, BillingSession::find($sessionId)->shift_id);
    }

    public function test_ringkasan_memisahkan_cash_dan_qris(): void
    {
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);

        $this->actingAs($this->operator(), 'sanctum');
        $this->open()->assertCreated();

        $sessionId = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $station->id,
                'package_id' => $package->id,
                'mode' => 'PREPAID',
            ])->json('data.id');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$sessionId}/payments", ['method' => 'CASH', 'amount' => 12000])
            ->assertCreated();

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$sessionId}/payments", [
                'method' => 'QRIS_STATIC', 'amount' => 8000, 'reference' => 'TRX-1',
            ])->assertCreated();

        $this->getJson('/api/v1/shifts/current')
            ->assertOk()
            ->assertJsonPath('data.summary.cash', 12000)
            ->assertJsonPath('data.summary.qris', 8000)
            ->assertJsonPath('data.summary.total', 20000)
            // Nilai transaksi, bukan uang masuk — dasar ini masih OD-013.
            ->assertJsonPath('data.summary.rental', 20000);
    }

    public function test_tutup_shift_mencatat_selisih_kas_di_audit(): void
    {
        $this->actingAs($this->operator(['name' => 'Budi']), 'sanctum');
        $shiftId = $this->open()->json('data.id');

        // Tidak ada uang masuk, jadi kas seharusnya tetap 200.000.
        // Operator menghitung 195.000 — selisih -5.000 harus tercatat.
        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/shifts/{$shiftId}/close", ['closing_cash' => 195000, 'note' => 'kurang 5rb'])
            ->assertOk()
            ->assertJsonPath('data.closing_cash', 195000);

        $this->assertNotNull(Shift::find($shiftId)->closed_at);

        $audit = \App\Models\AuditLog::query()
            ->where('action', AuditAction::SHIFT_CLOSED)
            ->first();

        $this->assertSame(-5000, $audit->after['difference']);
        $this->assertSame(200000, $audit->after['expected_cash']);
    }

    public function test_selisih_kas_tidak_menghalangi_penutupan(): void
    {
        // Shift yang tidak bisa ditutup karena selisih akan membuat operator
        // mengarang angka supaya bisa pulang.
        $this->actingAs($this->operator(), 'sanctum');
        $shiftId = $this->open()->json('data.id');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/shifts/{$shiftId}/close", ['closing_cash' => 0])
            ->assertOk();
    }

    public function test_shift_yang_sudah_ditutup_tidak_bisa_ditutup_lagi(): void
    {
        $this->actingAs($this->operator(), 'sanctum');
        $shiftId = $this->open()->json('data.id');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/shifts/{$shiftId}/close", ['closing_cash' => 200000])
            ->assertOk();

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/shifts/{$shiftId}/close", ['closing_cash' => 200000])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SHIFT_NOT_OPEN');
    }

    public function test_operator_lain_tidak_boleh_menutup_shift_orang(): void
    {
        $budi = $this->operator(['name' => 'Budi']);
        $this->actingAs($budi, 'sanctum');
        $shiftId = $this->open()->json('data.id');

        $this->actingAs($this->operator(['name' => 'Siti']), 'sanctum');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/shifts/{$shiftId}/close", ['closing_cash' => 200000])
            ->assertStatus(403);
    }

    public function test_admin_boleh_menutup_shift_orang(): void
    {
        // Operator yang lupa menutup shift harus bisa dibereskan tanpa
        // menunggu dia kembali.
        $this->actingAs($this->operator(), 'sanctum');
        $shiftId = $this->open()->json('data.id');

        $this->actingAs($this->operator(['name' => 'Bos', 'role' => UserRole::ADMIN]), 'sanctum');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/shifts/{$shiftId}/close", ['closing_cash' => 200000])
            ->assertOk();
    }

    public function test_buka_shift_tercatat_di_audit(): void
    {
        $this->actingAs($this->operator(['name' => 'Budi']), 'sanctum');
        $this->open()->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::SHIFT_OPENED,
            'actor_name' => 'Budi',
        ]);
    }
}
