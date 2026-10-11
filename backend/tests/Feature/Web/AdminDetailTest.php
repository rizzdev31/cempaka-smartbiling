<?php

namespace Tests\Feature\Web;

use App\Enums\PaymentMethod;
use App\Enums\SessionItemType;
use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\BillingSession;
use App\Models\Customer;
use App\Models\FnbProduct;
use App\Models\Payment;
use App\Models\SessionItem;
use App\Models\User;
use App\Support\Time\BusinessDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Detail sesi, struk, pembayaran, dan pendaftaran member dari web.
 *
 * Halaman detail sesi punya satu tugas: menjawab "kenapa tagihannya
 * segitu". Yang diuji di sini adalah apakah jawabannya benar-benar ada di
 * layar — termasuk angka yang membuat orang bertanya.
 */
class AdminDetailTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private function owner(): User
    {
        return $this->operator(['name' => 'Bos', 'role' => UserRole::OWNER]);
    }

    private function sesi(array $attributes = []): BillingSession
    {
        $type = $this->stationType();

        return BillingSession::query()->create($attributes + [
            'code' => 'S-UJI-0001',
            'station_id' => $this->station($type, ['code' => 'ST01'])->id,
            'package_id' => $this->package($type)->id,
            'package_name' => 'Paket 1 Jam',
            'package_duration_minutes' => 60,
            'package_price' => 10_000,
            'hourly_rate' => 10_000,
            'mode' => SessionMode::PREPAID,
            'status' => SessionStatus::COMPLETED,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHour(),
            'actual_duration_minutes' => 63,
            'billable_duration_minutes' => 60,
        ]);
    }

    // ── Detail sesi ───────────────────────────────────────────────────

    public function test_detail_sesi_menampilkan_tiap_baris_tagihan(): void
    {
        $sesi = $this->sesi();

        SessionItem::query()->create([
            'session_id' => $sesi->id, 'type' => SessionItemType::RENTAL,
            'name' => 'Paket 1 Jam', 'qty' => 1, 'unit_price' => 10_000, 'subtotal' => 10_000,
        ]);
        SessionItem::query()->create([
            'session_id' => $sesi->id, 'type' => SessionItemType::FNB,
            'name' => 'Es Teh', 'qty' => 2, 'unit_price' => 5_000, 'subtotal' => 10_000,
        ]);

        $this->actingAs($this->owner())
            ->get(route('admin.session', $sesi))
            ->assertOk()
            ->assertSee('Paket 1 Jam')
            ->assertSee('Es Teh')
            ->assertSee('Rp 20.000');
    }

    public function test_durasi_sebenarnya_dan_yang_ditagih_tampil_berdampingan(): void
    {
        /*
         * DEC-009. "Kenapa 63 menit ditagih 60?" adalah pertanyaan yang pasti
         * muncul, dan jawabannya harus ada di layar yang sama — bukan di
         * dokumen yang tidak akan dibuka siapa pun saat customer menunggu.
         */
        $sesi = $this->sesi();

        $this->actingAs($this->owner())
            ->get(route('admin.session', $sesi))
            ->assertOk()
            ->assertSee('63m')
            ->assertSee('60m');
    }

    public function test_tarif_yang_dibekukan_ditampilkan(): void
    {
        // DEC-040. Angka ini yang menjelaskan kenapa tagihan tidak ikut
        // berubah setelah owner menaikkan harga.
        $sesi = $this->sesi(['hourly_rate' => 8_000]);

        $this->actingAs($this->owner())
            ->get(route('admin.session', $sesi))
            ->assertOk()
            ->assertSee('Rp 8.000');
    }

    public function test_open_tab_tidak_disebut_kehilangan_data(): void
    {
        // DEC-034: Postpaid tidak punya `end_at`. Itu keadaan yang benar,
        // bukan data hilang.
        $sesi = $this->sesi([
            'mode' => SessionMode::POSTPAID,
            'end_at' => null,
            'status' => SessionStatus::ACTIVE,
        ]);

        $this->actingAs($this->owner())
            ->get(route('admin.session', $sesi))
            ->assertOk()
            ->assertSee('Open Tab');
    }

    public function test_kelebihan_bayar_terlihat(): void
    {
        /*
         * `balanceDue()` dijaga tidak negatif supaya tablet tidak menampilkan
         * tagihan minus (API.md §7). Tapi di halaman audit, kelebihan itu
         * JUSTRU yang perlu terlihat — ia berarti ada yang salah.
         */
        $sesi = $this->sesi();

        SessionItem::query()->create([
            'session_id' => $sesi->id, 'type' => SessionItemType::RENTAL,
            'name' => 'Paket', 'qty' => 1, 'unit_price' => 10_000, 'subtotal' => 10_000,
        ]);
        Payment::query()->create([
            'session_id' => $sesi->id, 'method' => PaymentMethod::CASH,
            'amount' => 15_000, 'status' => 'CONFIRMED', 'confirmed_at' => now(),
        ]);

        $this->actingAs($this->owner())
            ->get(route('admin.session', $sesi))
            ->assertOk()
            ->assertSee('Kelebihan bayar')
            ->assertSee('Rp 5.000');
    }

    // ── Struk ─────────────────────────────────────────────────────────

    public function test_tautan_whatsapp_muncul_kalau_customer_punya_nomor(): void
    {
        $customer = Customer::query()->create(['name' => 'Budi', 'phone' => '0812-3456-7890']);

        $sesi = $this->sesi([
            'customer_id' => $customer->id,
            'receipt_number' => 'R-20261011-0001',
            'receipt_issued_at' => now(),
        ]);

        $this->actingAs($this->owner())
            ->get(route('admin.session', $sesi))
            ->assertOk()
            ->assertSee('R-20261011-0001')
            // Nomor sudah diubah ke bentuk yang diterima wa.me — DEC-035.
            ->assertSee('wa.me/6281234567890', escape: false);
    }

    public function test_tanpa_nomor_dijelaskan_kenapa_tidak_bisa_dikirim(): void
    {
        // Walk-in tanpa customer: tombolnya tidak boleh ada, dan alasannya
        // harus terbaca. Tombol mati tanpa penjelasan akan ditekan berulang.
        $sesi = $this->sesi([
            'receipt_number' => 'R-20261011-0002',
            'receipt_issued_at' => now(),
        ]);

        $this->actingAs($this->owner())
            ->get(route('admin.session', $sesi))
            ->assertOk()
            ->assertDontSee('wa.me/', escape: false)
            ->assertSee('tidak ada nomor tujuan');
    }

    public function test_sesi_belum_checkout_tidak_menampilkan_struk(): void
    {
        $sesi = $this->sesi(['status' => SessionStatus::ACTIVE, 'receipt_number' => null]);

        $this->actingAs($this->owner())
            ->get(route('admin.session', $sesi))
            ->assertOk()
            ->assertDontSee('Kirim struk');
    }

    // ── Pembayaran ────────────────────────────────────────────────────

    public function test_halaman_pembayaran_menjumlahkan_per_metode(): void
    {
        $sesi = $this->sesi();

        foreach ([[PaymentMethod::CASH, 12_000], [PaymentMethod::QRIS_STATIC, 8_000]] as [$metode, $jumlah]) {
            Payment::query()->create([
                'session_id' => $sesi->id, 'method' => $metode, 'amount' => $jumlah,
                'status' => 'CONFIRMED', 'confirmed_at' => BusinessDay::now()->setTime(14, 0)->utc(),
            ]);
        }

        $this->actingAs($this->owner())
            ->get(route('admin.payments'))
            ->assertOk()
            ->assertSee('Rp 12.000')
            ->assertSee('Rp 8.000')
            ->assertSee('Rp 20.000');
    }

    public function test_pembayaran_belum_dikonfirmasi_tampil_tapi_tidak_dijumlahkan(): void
    {
        /*
         * Menyembunyikannya membuat selisih laci jadi misteri; ikut
         * menjumlahkannya membuat omzet terlihat lebih besar dari uang yang
         * benar-benar ada. Keduanya salah — jadi tampil, tapi di luar total.
         */
        $sesi = $this->sesi();

        Payment::query()->create([
            'session_id' => $sesi->id, 'method' => PaymentMethod::CASH, 'amount' => 50_000,
            'status' => 'PENDING', 'confirmed_at' => BusinessDay::now()->setTime(14, 0)->utc(),
        ]);

        $response = $this->actingAs($this->owner())->get(route('admin.payments'))->assertOk();

        // Barisnya ADA — lengkap dengan nominalnya…
        $response->assertSee('PENDING')->assertSee('Rp 50.000');

        // …tapi tidak masuk hitungan. Diperiksa dari angkanya langsung,
        // bukan dari teks halaman: "Rp 0" muncul di banyak tempat dan
        // assertion atasnya tidak akan membuktikan apa pun.
        $this->assertSame(0, $response->viewData('cash'));
        $this->assertSame(0, $response->viewData('qris'));
    }

    // ── Customer & member dari web ────────────────────────────────────

    public function test_menambah_customer_dari_web(): void
    {
        $this->actingAs($this->owner())
            ->post(route('admin.customers.store'), ['name' => 'Rina', 'phone' => '08123000111'])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('customers', ['name' => 'Rina']);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_menambah_customer_sekaligus_jadi_member(): void
    {
        $this->actingAs($this->owner())
            ->post(route('admin.customers.store'), [
                'name' => 'Siti', 'phone' => '08123000222', 'jadikan_member' => '1',
            ])
            ->assertSessionHas('sukses');

        $this->assertDatabaseCount('memberships', 1);
    }

    public function test_nomor_hp_ganda_ditolak_dengan_pesan_yang_jelas(): void
    {
        Customer::query()->create(['name' => 'Budi', 'phone' => '08123000333']);

        $this->actingAs($this->owner())
            ->post(route('admin.customers.store'), ['name' => 'Bukan Budi', 'phone' => '08123000333'])
            ->assertInvalid(['phone' => 'sudah terdaftar']);
    }

    public function test_menjadikan_member_customer_lama(): void
    {
        $customer = Customer::query()->create(['name' => 'Agus']);

        $this->actingAs($this->owner())
            ->post(route('admin.customers.membership', $customer))
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('memberships', ['customer_id' => $customer->id, 'is_active' => true]);
    }

    // ── Produk F&B baru + HPP ─────────────────────────────────────────

    public function test_owner_menambah_menu(): void
    {
        $this->fnbProduct(['name' => 'Es Teh', 'category' => 'Minuman']);

        $this->actingAs($this->owner())
            ->post(route('admin.fnb-products.store'), [
                'category' => 'Minuman',
                'name' => 'Es Jeruk',
                'price' => 7_000,
                'hpp' => 3_000,
                'stock' => '',
            ])
            ->assertRedirect()
            ->assertSessionHas('sukses');

        $produk = FnbProduct::query()->where('name', 'Es Jeruk')->firstOrFail();

        $this->assertSame(7_000, $produk->price);
        // Kosong = tidak dilacak, bukan nol.
        $this->assertNull($produk->stock);
    }

    public function test_admin_tidak_bisa_menambah_menu(): void
    {
        // Menambah = menetapkan harga → owner (DEC-020). Kalau tidak, admin
        // punya jalan memutar yang persis sama dengan mengubah harga.
        $this->actingAs($this->operator(['role' => UserRole::ADMIN]))
            ->post(route('admin.fnb-products.store'), [
                'category' => 'Minuman', 'name' => 'Selundupan', 'price' => 1_000,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('fnb_products', ['name' => 'Selundupan']);
    }

    public function test_kategori_karangan_ditolak(): void
    {
        /*
         * "MINUMAN", "Minuman", dan "minuman " akan tampil sebagai tiga
         * kelompok berbeda di halaman menu, dan tidak ada yang menyadari
         * sampai daftarnya jadi panjang.
         */
        $this->fnbProduct(['category' => 'Minuman']);

        $this->actingAs($this->owner())
            ->post(route('admin.fnb-products.store'), [
                'category' => 'MINUMAN', 'name' => 'Es Campur', 'price' => 9_000,
            ])
            ->assertInvalid('category');
    }

    public function test_owner_mengubah_hpp(): void
    {
        $produk = $this->fnbProduct(['price' => 10_000, 'hpp' => 4_000]);

        $this->actingAs($this->owner())
            ->patch(route('admin.fnb-products.update', $produk), [
                'price' => 10_000,
                'hpp' => 5_000,
                'stock' => '',
                'is_available' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(5_000, (int) $produk->fresh()->hpp);
    }

    public function test_admin_tidak_bisa_mengubah_hpp(): void
    {
        // OD-033: HPP menentukan margin di setiap laporan laba, jadi dikunci
        // untuk owner sampai user memutuskan sebaliknya.
        $produk = $this->fnbProduct(['price' => 10_000, 'hpp' => 4_000]);

        $this->actingAs($this->operator(['role' => UserRole::ADMIN]))
            ->patch(route('admin.fnb-products.update', $produk), [
                'price' => 10_000,
                'hpp' => 9_000,
                'stock' => '',
            ])
            ->assertInvalid(['price' => 'HPP']);

        $this->assertSame(4_000, (int) $produk->fresh()->hpp);
    }

    public function test_admin_tetap_bisa_mengubah_stok_walau_hpp_ada_di_form(): void
    {
        /*
         * Form selalu mengirim `hpp` karena kolomnya ada di baris itu.
         * Kalau controller meneruskannya apa adanya, admin tidak akan pernah
         * bisa menandai menu habis — padahal itu pekerjaan hariannya.
         */
        $produk = $this->fnbProduct(['price' => 10_000, 'hpp' => 4_000, 'stock' => 10]);

        $this->actingAs($this->operator(['role' => UserRole::ADMIN]))
            ->patch(route('admin.fnb-products.update', $produk), [
                'price' => 10_000,
                'hpp' => 4_000,   // sama persis
                'stock' => 0,
                'is_available' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $produk->fresh()->stock);
    }
}
