<?php

namespace Tests\Feature\Web;

use App\Enums\FnbOrderStatus;
use App\Enums\UserRole;
use App\Models\FnbOrder;
use App\Models\Package;
use App\Models\Shift;
use App\Models\User;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Aksi ubah dari Admin Web — DEC-044.
 *
 * Yang paling penting di berkas ini: membuktikan bahwa jalur web **memakai
 * Service yang sama** dengan API. Bukti itu bukan dibaca dari kode,
 * melainkan dari akibatnya — izin per field ditegakkan, dan audit log
 * terisi. Keduanya hilang diam-diam kalau suatu saat ada yang mengganti
 * panggilan Service dengan `$model->update()`.
 */
class AdminActionsTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private function owner(): User
    {
        return $this->operator(['name' => 'Bos', 'role' => UserRole::OWNER]);
    }

    private function admin(): User
    {
        return $this->operator(['name' => 'Adi', 'role' => UserRole::ADMIN]);
    }

    private function paket(): Package
    {
        return $this->package($this->stationType());
    }

    // ── Harga paket ───────────────────────────────────────────────────

    public function test_owner_mengubah_harga_paket(): void
    {
        $paket = $this->paket();

        $this->actingAs($this->owner())
            ->patch(route('admin.packages.update', $paket), [
                'name' => $paket->name,
                'duration_minutes' => 60,
                'price' => 12_000,
                'is_active' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $this->assertSame(12_000, $paket->fresh()->price);
    }

    public function test_perubahan_harga_tercatat_di_audit(): void
    {
        /*
         * PRD §24. Pertanyaan "kenapa tagihan Senin beda dengan hari ini"
         * hanya bisa dijawab kalau harga lamanya tercatat — dan itu hanya
         * terjadi kalau web memanggil Service, bukan menulis ke model.
         */
        $paket = $this->paket();

        $this->actingAs($this->owner())->patch(route('admin.packages.update', $paket), [
            'name' => $paket->name,
            'duration_minutes' => 60,
            'price' => 15_000,
            'is_active' => '1',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::PACKAGE_UPDATED,
            'actor_name' => 'Bos',
        ]);
    }

    public function test_admin_tidak_bisa_menyentuh_paket_sama_sekali(): void
    {
        /*
         * DEC-020, dan letak penegakannya PENTING.
         *
         * Berbeda dari produk F&B, `MasterDataService::updatePackage()`
         * TIDAK memeriksa izin apa pun — di API, satu-satunya gerbang adalah
         * `can:pricing.manage` di route. Versi pertama route web ini
         * melewatkannya, dan admin bisa mengubah seluruh tarif rental.
         *
         * Test ini yang menemukannya, bukan pembacaan kode.
         */
        $paket = $this->paket();
        $harga = $paket->price;

        $this->actingAs($this->admin())
            ->patch(route('admin.packages.update', $paket), [
                'name' => $paket->name,
                'duration_minutes' => 60,
                'price' => 99_000,
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertSame($harga, $paket->fresh()->price);
    }

    public function test_admin_tidak_bisa_menambah_paket(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.packages.store'), [
                'station_type_id' => $this->stationType()->id,
                'name' => 'Selundupan',
                'duration_minutes' => 60,
                'price' => 1_000,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('packages', ['name' => 'Selundupan']);
    }

    public function test_penolakan_service_jadi_pesan_form_bukan_halaman_error(): void
    {
        /*
         * `ApiException` hanya dirender JSON untuk `api/*`. Dibiarkan lewat,
         * ia jadi halaman 500 kosong — padahal isinya kalimat yang justru
         * perlu dibaca.
         *
         * Diuji lewat produk F&B, karena di sanalah penolakan datang dari
         * SERVICE (per field), bukan dari route.
         */
        $produk = $this->fnbProduct(['price' => 12_000]);

        $this->actingAs($this->admin())
            ->patch(route('admin.fnb-products.update', $produk), [
                'price' => 20_000,
                'stock' => 5,
            ])
            ->assertRedirect()
            // `assertInvalid` mencocokkan sebagian — pesan Service-nya utuh
            // sampai ke form, bukan diganti kalimat umum.
            ->assertInvalid(['price' => 'owner']);
    }

    public function test_checkbox_tidak_dicentang_menonaktifkan_paket(): void
    {
        /*
         * Browser TIDAK mengirim checkbox yang tidak dicentang. Tanpa
         * `$request->boolean()`, menonaktifkan paket mustahil — nilainya
         * selalu terbaca "tetap seperti semula".
         */
        $paket = $this->paket();
        $this->assertTrue($paket->is_active);

        $this->actingAs($this->owner())->patch(route('admin.packages.update', $paket), [
            'name' => $paket->name,
            'duration_minutes' => 60,
            'price' => $paket->price,
            // `is_active` sengaja TIDAK dikirim, seperti browser.
        ]);

        $this->assertFalse($paket->fresh()->is_active);
    }

    public function test_owner_menambah_paket(): void
    {
        $tipe = $this->stationType();

        $this->actingAs($this->owner())
            ->post(route('admin.packages.store'), [
                'station_type_id' => $tipe->id,
                'name' => '2 Jam',
                'duration_minutes' => 120,
                'price' => 18_000,
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('packages', ['name' => '2 Jam', 'price' => 18_000]);
    }

    // ── F&B ───────────────────────────────────────────────────────────

    public function test_admin_boleh_mengubah_stok_tanpa_menyentuh_harga(): void
    {
        /*
         * Inti dari pembagian izin ini. "Mie Goreng habis" adalah kejadian
         * harian yang harus bisa ditangani admin sendiri.
         *
         * Form selalu MENGIRIM `price`, karena kolomnya memang ada di baris
         * itu. Kalau controller meneruskannya apa adanya, Service menolak —
         * dan admin tidak akan pernah bisa menandai stok habis.
         */
        $produk = $this->fnbProduct(['name' => 'Mie Goreng', 'price' => 12_000, 'stock' => 10]);

        $this->actingAs($this->admin())
            ->patch(route('admin.fnb-products.update', $produk), [
                'price' => 12_000,   // sama persis — tidak boleh dianggap perubahan
                'stock' => 0,
                'is_available' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $produk->fresh()->stock);
    }

    public function test_admin_tetap_ditolak_kalau_benar_benar_mengubah_harga(): void
    {
        $produk = $this->fnbProduct(['price' => 12_000]);

        $this->actingAs($this->admin())
            ->patch(route('admin.fnb-products.update', $produk), [
                'price' => 15_000,
                'stock' => 5,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(12_000, $produk->fresh()->price);
    }

    public function test_stok_kosong_berarti_tidak_dilacak_bukan_nol(): void
    {
        // API.md §8. Nol = habis, null = tidak dihitung (es batu, sambal).
        $produk = $this->fnbProduct(['stock' => 10]);

        $this->actingAs($this->owner())->patch(route('admin.fnb-products.update', $produk), [
            'price' => $produk->price,
            'stock' => '',
            'is_available' => '1',
        ]);

        $this->assertNull($produk->fresh()->stock);
    }

    public function test_status_pesanan_dimajukan(): void
    {
        $order = $this->pesanan();

        $this->actingAs($this->admin())
            ->post(route('admin.fnb-orders.status', $order), ['status' => 'PROCESSING'])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $this->assertSame(FnbOrderStatus::PROCESSING, $order->fresh()->status);
    }

    public function test_lompatan_status_yang_tidak_masuk_akal_ditolak(): void
    {
        // Service yang memutuskan transisi mana yang sah. Web tidak boleh
        // punya daftar sendiri.
        $order = $this->pesanan();

        $this->actingAs($this->admin())
            ->post(route('admin.fnb-orders.status', $order), ['status' => 'NGAWUR'])
            ->assertSessionHasErrors('status');
    }

    // ── Shift ─────────────────────────────────────────────────────────

    public function test_menutup_shift_dari_web(): void
    {
        $owner = $this->owner();
        $shift = Shift::query()->create([
            'operator_id' => $owner->id,
            'opened_at' => now()->subHour(),
            'opening_cash' => 200_000,
        ]);

        $this->actingAs($owner)
            ->post(route('admin.shifts.close', $shift), [
                'closing_cash' => 195_000,
                'note' => 'kurang 5rb',
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $this->assertNotNull($shift->fresh()->closed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::SHIFT_CLOSED]);
    }

    public function test_shift_yang_sudah_ditutup_tidak_bisa_ditutup_lagi(): void
    {
        $owner = $this->owner();
        $shift = Shift::query()->create([
            'operator_id' => $owner->id,
            'opened_at' => now()->subHours(2),
            'closed_at' => now()->subHour(),
            'opening_cash' => 200_000,
            'closing_cash' => 200_000,
        ]);

        $this->actingAs($owner)
            ->post(route('admin.shifts.close', $shift), ['closing_cash' => 100_000])
            ->assertSessionHasErrors('closing_cash');
    }

    // ── Pintu ─────────────────────────────────────────────────────────

    public function test_operator_tidak_bisa_memakai_aksi_web(): void
    {
        $paket = $this->paket();

        $this->actingAs($this->operator(['role' => UserRole::OPERATOR]))
            ->patch(route('admin.packages.update', $paket), [
                'name' => 'X', 'duration_minutes' => 60, 'price' => 1,
            ])
            ->assertRedirect('/login');
    }

    public function test_tamu_tidak_bisa_memakai_aksi_web(): void
    {
        $paket = $this->paket();

        $this->patch(route('admin.packages.update', $paket), [
            'name' => 'X', 'duration_minutes' => 60, 'price' => 1,
        ])->assertRedirect('/login');
    }

    private function pesanan(): FnbOrder
    {
        $produk = $this->fnbProduct();
        $type = $this->stationType();
        $station = $this->station($type);
        $package = $this->package($type);

        $this->actingAs($this->operator(['name' => 'Kasir']), 'sanctum');

        $sessionId = $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $station->id,
                'package_id' => $package->id,
                'mode' => 'PREPAID',
            ])->json('data.id');

        /*
         * Prepaid mulai sebagai PENDING_PAYMENT, dan sesi yang belum dibayar
         * tidak boleh menerima pesanan F&B. Dibayar dulu supaya sesinya
         * ACTIVE — meniru urutan yang sebenarnya di kasir.
         */
        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$sessionId}/payments", [
                'method' => 'CASH',
                'amount' => $package->price,
            ])->assertCreated();

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$sessionId}/fnb/orders", [
                'items' => [['product_id' => $produk->id, 'qty' => 1]],
            ])->assertCreated();

        $this->flushHeaders();

        return FnbOrder::query()->firstOrFail();
    }
}
