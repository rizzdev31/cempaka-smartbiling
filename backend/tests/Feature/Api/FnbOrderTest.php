<?php

namespace Tests\Feature\Api;

use App\Enums\SessionItemType;
use App\Models\BillingSession;
use App\Models\FnbProduct;
use App\Models\Package;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/** F&B — API.md §8, PRD §13, T05. */
class FnbOrderTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private Station $stationModel;

    private Package $packageModel;

    private FnbProduct $teh;

    protected function setUp(): void
    {
        parent::setUp();

        $type = $this->stationType();
        $this->stationModel = $this->station($type);
        $this->packageModel = $this->package($type);
        $this->teh = $this->fnbProduct(['name' => 'Teh Manis', 'price' => 5000]);

        $this->actingAs($this->operator(['name' => 'Budi']), 'sanctum');
    }

    private function session(string $mode = 'POSTPAID'): string
    {
        return $this->withHeaders($this->idempotent())
            ->postJson('/api/v1/sessions', [
                'station_id' => $this->stationModel->id,
                'package_id' => $this->packageModel->id,
                'mode' => $mode,
            ])->json('data.id');
    }

    private function order(string $sessionId, array $items, array $headers = []): TestResponse
    {
        return $this->withHeaders($headers ?: $this->idempotent())
            ->postJson("/api/v1/sessions/{$sessionId}/fnb/orders", ['items' => $items]);
    }

    public function test_menu_bisa_dibaca(): void
    {
        $this->getJson('/api/v1/fnb/products')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Teh Manis')
            ->assertJsonPath('data.0.price', 5000)
            // null = stok tidak dilacak. Tidak boleh dipaksa jadi 0 — artinya
            // berbeda: 0 berarti habis.
            ->assertJsonPath('data.0.stock', null);
    }

    public function test_order_masuk_open_tab_session(): void
    {
        $id = $this->session();

        $this->order($id, [['product_id' => $this->teh->id, 'qty' => 2]])
            ->assertCreated()
            ->assertJsonPath('data.order.total', 10000)
            ->assertJsonPath('data.order.status', 'PENDING')
            ->assertJsonPath('data.order.station_code', 'ST01')
            ->assertJsonPath('data.session.totals.fnb', 10000)
            // Rental Postpaid 20.000 + F&B 10.000.
            ->assertJsonPath('data.session.totals.balance_due', 30000);

        $this->assertSame(
            1,
            BillingSession::find($id)->items()->where('type', SessionItemType::FNB->value)->count(),
        );
    }

    public function test_harga_selalu_dari_server(): void
    {
        // PRD §8. Kalau client boleh mengirim harga, diskon bisa dibuat dari
        // tablet. Harga yang dikirim di body harus diabaikan sepenuhnya.
        $id = $this->session();

        $this->withHeaders($this->idempotent())
            ->postJson("/api/v1/sessions/{$id}/fnb/orders", [
                'items' => [['product_id' => $this->teh->id, 'qty' => 1, 'unit_price' => 1]],
            ])->assertCreated()
            ->assertJsonPath('data.order.total', 5000);
    }

    public function test_sesi_belum_dibayar_tidak_bisa_pesan(): void
    {
        // Ini yang menutup ghost order (T05): makanan keluar untuk sesi yang
        // belum punya Open Tab yang bisa ditagih.
        $id = $this->session('PREPAID');

        $this->order($id, [['product_id' => $this->teh->id, 'qty' => 1]])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SESSION_NOT_ORDERABLE');
    }

    public function test_produk_tidak_tersedia_ditolak(): void
    {
        $id = $this->session();
        $this->teh->update(['is_available' => false]);

        $this->order($id, [['product_id' => $this->teh->id, 'qty' => 1]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    }

    public function test_stok_yang_dilacak_berkurang_dan_tidak_boleh_minus(): void
    {
        $id = $this->session();
        $mie = $this->fnbProduct(['name' => 'Mie Goreng', 'price' => 12000, 'stock' => 3]);

        $this->order($id, [['product_id' => $mie->id, 'qty' => 2]])->assertCreated();
        $this->assertSame(1, (int) $mie->fresh()->stock);

        $this->order($id, [['product_id' => $mie->id, 'qty' => 2]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED');

        $this->assertSame(1, (int) $mie->fresh()->stock);
    }

    public function test_antrian_bergerak_sampai_diantar(): void
    {
        $id = $this->session();
        $orderId = $this->order($id, [['product_id' => $this->teh->id, 'qty' => 1]])->json('data.order.id');

        foreach (['PROCESSING', 'READY', 'DELIVERED'] as $status) {
            $this->postJson("/api/v1/fnb/orders/{$orderId}/status", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('data.order.status', $status);
        }
    }

    public function test_tidak_boleh_melompati_status(): void
    {
        $id = $this->session();
        $orderId = $this->order($id, [['product_id' => $this->teh->id, 'qty' => 1]])->json('data.order.id');

        $this->postJson("/api/v1/fnb/orders/{$orderId}/status", ['status' => 'DELIVERED'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'FNB_STATUS_TRANSITION_INVALID');
    }

    public function test_order_dibatalkan_tidak_ikut_ditagih(): void
    {
        $id = $this->session();
        $orderId = $this->order($id, [['product_id' => $this->teh->id, 'qty' => 2]])->json('data.order.id');

        $this->assertSame(30000, BillingSession::find($id)->totals()->balanceDue());

        $this->postJson("/api/v1/fnb/orders/{$orderId}/status", ['status' => 'CANCELLED'])->assertOk();

        // Kembali ke rental saja.
        $this->assertSame(20000, BillingSession::find($id)->totals()->balanceDue());
    }

    public function test_antrian_bisa_difilter_per_status(): void
    {
        $id = $this->session();
        $a = $this->order($id, [['product_id' => $this->teh->id, 'qty' => 1]])->json('data.order.id');
        $this->order($id, [['product_id' => $this->teh->id, 'qty' => 1]])->assertCreated();

        $this->postJson("/api/v1/fnb/orders/{$a}/status", ['status' => 'PROCESSING'])->assertOk();

        $this->getJson('/api/v1/fnb/orders?status=PENDING')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'PENDING');
    }

    public function test_key_sama_tidak_membuat_order_dua_kali(): void
    {
        $id = $this->session();
        $headers = $this->idempotent();

        $this->order($id, [['product_id' => $this->teh->id, 'qty' => 1]], $headers)->assertCreated();
        $this->order($id, [['product_id' => $this->teh->id, 'qty' => 1]], $headers)->assertCreated();

        $this->assertSame(1, BillingSession::find($id)->fnbOrders()->count());
    }
}
