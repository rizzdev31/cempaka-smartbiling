<?php

namespace Tests\Feature\Api;

use App\Enums\SessionItemType;
use App\Models\BillingSession;
use App\Models\Customer;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Customer & membership — API.md §6, DEC-027, DEC-029.
 *
 * Alur yang dibuktikan di sini adalah yang sebelumnya buntu: customer Prepaid
 * berhenti lebih awal, operator menawarkan membership supaya sisa waktunya
 * tidak hangus (DEC-024), dan sekarang operator benar-benar bisa melakukannya.
 */
class CustomerMembershipTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    public function test_operator_bisa_mendaftarkan_customer_baru(): void
    {
        // DEC-027. Sebelumnya 403.
        $this->actingAs($this->operator(['name' => 'Budi']), 'sanctum');

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/customers', ['name' => 'Siti', 'phone' => '081234567890'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Siti')
            ->assertJsonPath('data.membership', null)
            ->assertJsonPath('data.credit_balance', 0);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CUSTOMER_CREATED,
            'actor_name' => 'Budi',
        ]);
    }

    public function test_nomor_telepon_tidak_boleh_dobel(): void
    {
        // Nomor adalah satu-satunya cara operator mencari member yang lupa
        // namanya — kalau dobel, pencariannya jadi ambigu.
        $this->actingAs($this->operator(), 'sanctum');
        $this->member('Siti')->update(['phone' => '081234567890']);

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/customers', ['name' => 'Siti Kedua', 'phone' => '081234567890'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_nomor_telepon_boleh_kosong(): void
    {
        // Memaksanya akan membuat operator mengarang nomor demi melewati form.
        $this->actingAs($this->operator(), 'sanctum');

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/customers', ['name' => 'Tanpa Nomor'])
            ->assertCreated()
            ->assertJsonPath('data.phone', null);
    }

    public function test_cari_customer_berdasarkan_nama_atau_telepon(): void
    {
        $this->actingAs($this->operator(), 'sanctum');
        $this->member('Siti Rahayu')->update(['phone' => '081200001111']);
        $this->member('Budi Santoso')->update(['phone' => '081299998888']);

        $this->getJson('/api/v1/customers?q=Siti')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Siti Rahayu');

        $this->getJson('/api/v1/customers?q=9999')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Budi Santoso');
    }

    public function test_daftar_member_menagih_biaya_ke_sesi_berjalan(): void
    {
        // DEC-029 — Rp 10.000 masuk Open Tab sesi, bukan transaksi terpisah.
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);

        $this->actingAs($this->operator(), 'sanctum');
        $this->travelTo(Carbon::parse('2026-10-08T07:00:00Z'));

        $sessionId = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $station->id,
                'package_id' => $package->id,
                'mode' => 'POSTPAID',
            ])->json('data.id');

        $customer = $this->member('Siti');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/customers/{$customer->id}/membership", ['session_id' => $sessionId])
            ->assertCreated()
            ->assertJsonPath('data.customer.membership.is_active', true)
            ->assertJsonPath('data.fee', 10000);

        $session = BillingSession::find($sessionId);

        $this->assertSame(30000, $session->totals()->balanceDue());   // rental 20rb + biaya 10rb
        $this->assertSame(10000, $session->totals()->adjustment);
    }

    public function test_sesi_walk_in_ditautkan_ke_member_baru(): void
    {
        // Tanpa ini, sesi yang dimulai sebagai Walk-in tetap tidak punya
        // customer_id, dan sisa waktunya tidak akan masuk ke akun siapa pun
        // saat checkout (DEC-024).
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);

        $this->actingAs($this->operator(), 'sanctum');

        $sessionId = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $station->id,
                'package_id' => $package->id,
                'mode' => 'POSTPAID',
            ])->assertCreated()
            ->assertJsonPath('data.customer_name', 'Walk-in')
            ->json('data.id');

        $customer = $this->member('Siti');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/customers/{$customer->id}/membership", ['session_id' => $sessionId])
            ->assertCreated();

        $session = BillingSession::find($sessionId);

        $this->assertSame($customer->id, $session->customer_id);
        $this->assertNull($session->customer_name);
    }

    public function test_daftar_member_tanpa_sesi_tidak_menagih_apa_pun(): void
    {
        $this->actingAs($this->operator(), 'sanctum');
        $customer = $this->member('Siti');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/customers/{$customer->id}/membership", [])
            ->assertCreated()
            ->assertJsonPath('data.customer.membership.tier', 'SILVER');

        $this->assertSame(0, BillingSession::query()->count());
    }

    public function test_customer_yang_sudah_member_ditolak(): void
    {
        $this->actingAs($this->operator(), 'sanctum');
        $member = $this->activeMember('Siti');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/customers/{$member->id}/membership", [])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CUSTOMER_ALREADY_MEMBER');
    }

    public function test_alur_lengkap_jadi_member_lalu_sisa_waktu_tersimpan(): void
    {
        // Inilah alur yang DEC-024 maksud dan yang sebelum DEC-027 buntu.
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);        // 60 menit / 20.000

        $this->actingAs($this->operator(), 'sanctum');
        $this->travelTo(Carbon::parse('2026-10-08T07:00:00Z'));

        // Walk-in membayar di muka.
        $sessionId = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $station->id,
                'package_id' => $package->id,
                'mode' => 'PREPAID',
            ])->json('data.id');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$sessionId}/payments", ['method' => 'CASH', 'amount' => 20000])
            ->assertCreated();

        // Menit ke-40 dia mau pulang. Operator menawarkan membership.
        $this->travelTo(Carbon::parse('2026-10-08T07:40:00Z'));

        $customer = Customer::query()->create(['name' => 'Siti', 'phone' => '081234567890']);

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/customers/{$customer->id}/membership", ['session_id' => $sessionId])
            ->assertCreated();

        // Tagihan tersisa hanya biaya pendaftaran.
        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$sessionId}/checkout", [
                'payments' => [['method' => 'CASH', 'amount' => 10000]],
            ])->assertOk()
            ->assertJsonPath('data.session.status', 'COMPLETED');

        // Sisa 20 menit senilai 6.666 masuk saldo — tidak hangus lagi.
        $this->assertSame(6666, $customer->fresh()->creditBalance());
    }

    public function test_item_biaya_member_terbaca_di_struk(): void
    {
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);

        $this->actingAs($this->operator(), 'sanctum');
        $this->travelTo(Carbon::parse('2026-10-08T07:00:00Z'));

        $sessionId = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $station->id,
                'package_id' => $package->id,
                'mode' => 'POSTPAID',
            ])->json('data.id');

        $customer = $this->member('Siti');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/customers/{$customer->id}/membership", ['session_id' => $sessionId])
            ->assertCreated();

        $item = BillingSession::find($sessionId)
            ->items()
            ->where('type', SessionItemType::ADJUSTMENT->value)
            ->first();

        $this->assertSame('Biaya daftar member', $item->name);
        $this->assertSame(10000, (int) $item->subtotal);
    }

    public function test_pendaftaran_membership_tercatat_di_audit(): void
    {
        $this->actingAs($this->operator(['name' => 'Budi']), 'sanctum');
        $customer = $this->member('Siti');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/customers/{$customer->id}/membership", [])
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::MEMBERSHIP_CREATED,
            'actor_name' => 'Budi',
            'subject_id' => $customer->id,
        ]);
    }
}
