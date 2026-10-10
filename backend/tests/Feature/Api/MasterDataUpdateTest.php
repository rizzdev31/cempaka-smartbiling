<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Events\MasterDataUpdated;
use App\Models\BillingSession;
use App\Models\FnbProduct;
use App\Models\Package;
use App\Models\Station;
use App\Models\StationType;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Mengubah master data — DEC-019, DEC-020, DEC-040.
 *
 * Dua hal yang paling penting diuji di sini:
 *
 * 1. Harga sesi yang SEDANG BERJALAN tidak ikut berubah. Kalau ini bocor,
 *    tagihan customer berubah setelah dia duduk.
 * 2. Setiap perubahan memicu `master.updated`, supaya tablet lain tidak
 *    terus menampilkan harga lama.
 */
class MasterDataUpdateTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private StationType $type;

    private Station $stationModel;

    private Package $packageModel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->type = $this->stationType('PS4');
        $this->stationModel = $this->station($this->type);
        $this->packageModel = $this->package($this->type);   // 60 menit / 20.000
    }

    private function owner(): \App\Models\User
    {
        return $this->operator(['name' => 'Bos', 'role' => UserRole::OWNER]);
    }

    // ── Siapa yang boleh ─────────────────────────────────────────────────

    public function test_owner_bisa_mengubah_harga(): void
    {
        $this->actingAs($this->owner(), 'sanctum');

        $this->patchJson("/api/v1/packages/{$this->packageModel->id}", ['price' => 25000])
            ->assertOk()
            ->assertJsonPath('data.price', 25000)
            ->assertJsonPath('data.hourly_rate', 25000);
    }

    public function test_operator_tidak_boleh_mengubah_harga(): void
    {
        // DEC-020 — yang menyentuh harga hanya owner.
        $this->actingAs($this->operator(), 'sanctum');

        $this->patchJson("/api/v1/packages/{$this->packageModel->id}", ['price' => 1000])
            ->assertStatus(403);

        $this->assertSame(20000, (int) $this->packageModel->fresh()->price);
    }

    public function test_admin_pun_tidak_boleh_mengubah_harga(): void
    {
        // DEC-020 memisahkan owner dari admin khusus untuk harga.
        $this->actingAs($this->operator(['role' => UserRole::ADMIN]), 'sanctum');

        $this->patchJson("/api/v1/packages/{$this->packageModel->id}", ['price' => 1000])
            ->assertStatus(403);
    }

    // ── Yang paling penting ──────────────────────────────────────────────

    public function test_harga_naik_tidak_mengubah_tagihan_sesi_berjalan(): void
    {
        /*
         * Customer sudah disebutkan harganya di depan dan sudah duduk. Harga
         * itu tidak boleh bergerak. Harga dibekukan ke baris sesi saat dibuat
         * — test ini yang menjaganya tetap begitu.
         */
        $this->actingAs($this->operator(), 'sanctum');
        $this->travelTo(Carbon::parse('2026-10-10T07:00:00Z'));

        $id = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->stationModel->id,
                'package_id' => $this->packageModel->id,
                'mode' => 'PREPAID',
            ])->json('data.id');

        // Owner menaikkan harga di tengah hari.
        $this->actingAs($this->owner(), 'sanctum');
        $this->patchJson("/api/v1/packages/{$this->packageModel->id}", ['price' => 50000])->assertOk();

        $sesi = BillingSession::find($id);

        $this->assertSame(20000, (int) $sesi->package_price);
        $this->assertSame(20000, (int) $sesi->hourly_rate);
        $this->assertSame(20000, $sesi->totals()->rental);
    }

    public function test_harga_extend_juga_memakai_tarif_yang_dibekukan(): void
    {
        // Rumus extend (DEC-007) memakai `hourly_rate` sesi, bukan tarif
        // paket sekarang — kalau tidak, extend jadi lebih mahal di tengah sesi.
        $this->actingAs($this->operator(), 'sanctum');
        $this->travelTo(Carbon::parse('2026-10-10T07:00:00Z'));

        $id = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->stationModel->id,
                'package_id' => $this->packageModel->id,
                'mode' => 'PREPAID',
            ])->json('data.id');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/payments", ['method' => 'CASH', 'amount' => 20000])
            ->assertCreated();

        $this->actingAs($this->owner(), 'sanctum');
        $this->patchJson("/api/v1/packages/{$this->packageModel->id}", ['price' => 50000])->assertOk();

        $this->actingAs($this->operator(), 'sanctum');
        $this->travelTo(Carbon::parse('2026-10-10T07:30:00Z'));

        // Tarif beku 20.000/jam -> extend 30 menit tetap 10.000, bukan 25.000.
        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/extend", ['duration_minutes' => 30])
            ->assertOk()
            ->assertJsonPath('data.extend.price', 10000);
    }

    public function test_sesi_baru_memakai_harga_yang_baru(): void
    {
        // Yang dibekukan hanya sesi yang sudah ada. Sesi berikutnya ikut
        // harga terbaru — itu memang gunanya mengubah harga.
        $this->actingAs($this->owner(), 'sanctum');
        $this->patchJson("/api/v1/packages/{$this->packageModel->id}", ['price' => 35000])->assertOk();

        $this->actingAs($this->operator(), 'sanctum');

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->stationModel->id,
                'package_id' => $this->packageModel->id,
                'mode' => 'PREPAID',
            ])->assertCreated()
            ->assertJsonPath('data.package.price', 35000)
            ->assertJsonPath('data.totals.rental', 35000);
    }

    // ── Penyebaran ke tablet lain ────────────────────────────────────────

    public function test_perubahan_harga_memberitahu_semua_tablet(): void
    {
        Event::fake([MasterDataUpdated::class]);
        $this->actingAs($this->owner(), 'sanctum');

        $this->patchJson("/api/v1/packages/{$this->packageModel->id}", ['price' => 25000])->assertOk();

        Event::assertDispatched(MasterDataUpdated::class, function (MasterDataUpdated $e) {
            return $e->resource === 'package'
                && $e->action === 'updated'
                && $e->id === $this->packageModel->id;
        });
    }

    public function test_paket_baru_juga_memberitahu(): void
    {
        Event::fake([MasterDataUpdated::class]);
        $this->actingAs($this->owner(), 'sanctum');

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/packages', [
                'station_type_id' => $this->type->id,
                'name' => '5 Jam',
                'duration_minutes' => 300,
                'price' => 45000,
            ])->assertCreated()
            ->assertJsonPath('data.hourly_rate', 9000);

        Event::assertDispatched(MasterDataUpdated::class, fn ($e) => $e->action === 'created');
    }

    public function test_tidak_dikirim_ke_channel_tv(): void
    {
        // TV tidak pernah menampilkan harga — layarnya cuma timer.
        $event = new MasterDataUpdated('package', 'updated', $this->packageModel->id);

        $this->assertCount(1, $event->broadcastOn());
        $this->assertSame('private-operator', $event->broadcastOn()[0]->name);
    }

    public function test_paket_baru_langsung_muncul_di_daftar(): void
    {
        $this->actingAs($this->owner(), 'sanctum');

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/packages', [
                'station_type_id' => $this->type->id,
                'name' => '5 Jam',
                'duration_minutes' => 300,
                'price' => 45000,
            ])->assertCreated();

        $this->getJson("/api/v1/packages?station_id={$this->stationModel->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_paket_dinonaktifkan_hilang_dari_daftar(): void
    {
        // Dinonaktifkan, bukan dihapus — sesi lama tetap punya acuan paketnya.
        $this->actingAs($this->owner(), 'sanctum');

        $this->patchJson("/api/v1/packages/{$this->packageModel->id}", ['is_active' => false])->assertOk();

        $this->getJson("/api/v1/packages?station_id={$this->stationModel->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertNotNull(Package::find($this->packageModel->id));
    }

    public function test_nama_paket_tidak_boleh_dobel_dalam_satu_tipe_konsol(): void
    {
        $this->actingAs($this->owner(), 'sanctum');

        $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/packages', [
                'station_type_id' => $this->type->id,
                'name' => $this->packageModel->name,
                'duration_minutes' => 60,
                'price' => 1000,
            ])->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    // ── Menu F&B ─────────────────────────────────────────────────────────

    public function test_operator_boleh_menandai_menu_habis(): void
    {
        // Kejadian harian yang tidak boleh menunggu owner.
        $teh = $this->fnbProduct();
        $this->actingAs($this->operator(), 'sanctum');

        $this->patchJson("/api/v1/fnb/products/{$teh->id}", ['is_available' => false])
            ->assertOk()
            ->assertJsonPath('data.is_available', false);
    }

    public function test_operator_tidak_boleh_mengubah_harga_menu(): void
    {
        $teh = $this->fnbProduct();
        $this->actingAs($this->operator(), 'sanctum');

        $this->patchJson("/api/v1/fnb/products/{$teh->id}", ['price' => 1])
            ->assertStatus(403);

        $this->assertSame(5000, (int) $teh->fresh()->price);
    }

    public function test_owner_boleh_mengubah_harga_menu(): void
    {
        $teh = $this->fnbProduct();
        $this->actingAs($this->owner(), 'sanctum');

        $this->patchJson("/api/v1/fnb/products/{$teh->id}", ['price' => 6000])
            ->assertOk()
            ->assertJsonPath('data.price', 6000);
    }

    public function test_harga_menu_naik_tidak_mengubah_order_yang_sudah_jalan(): void
    {
        // Nama & harga dibekukan saat order dibuat.
        $teh = $this->fnbProduct();
        $this->actingAs($this->operator(), 'sanctum');

        $id = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->stationModel->id,
                'package_id' => $this->packageModel->id,
                'mode' => 'POSTPAID',
            ])->json('data.id');

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/fnb/orders", [
                'items' => [['product_id' => $teh->id, 'qty' => 2]],
            ])->assertCreated();

        $this->actingAs($this->owner(), 'sanctum');
        $this->patchJson("/api/v1/fnb/products/{$teh->id}", ['price' => 20000])->assertOk();

        $this->assertSame(10000, BillingSession::find($id)->totals()->fnb);
    }

    // ── Jejak ────────────────────────────────────────────────────────────

    public function test_perubahan_harga_tercatat_dengan_nilai_sebelum_dan_sesudah(): void
    {
        /*
         * PRD §24. Pertanyaan "kenapa tagihan hari Senin beda dengan hari ini"
         * hanya bisa dijawab kalau harga lamanya tercatat.
         */
        $this->actingAs($this->owner(), 'sanctum');

        $this->patchJson("/api/v1/packages/{$this->packageModel->id}", ['price' => 25000])->assertOk();

        $audit = \App\Models\AuditLog::query()
            ->where('action', AuditAction::PACKAGE_UPDATED)
            ->sole();

        $this->assertSame(20000, $audit->before['price']);
        $this->assertSame(25000, $audit->after['price']);
        $this->assertSame('Bos', $audit->actor_name);
    }

    public function test_perubahan_menu_juga_tercatat(): void
    {
        $teh = $this->fnbProduct();
        $this->actingAs($this->operator(['name' => 'Budi']), 'sanctum');

        $this->patchJson("/api/v1/fnb/products/{$teh->id}", ['stock' => 0])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::FNB_PRODUCT_UPDATED,
            'actor_name' => 'Budi',
        ]);
    }
}
