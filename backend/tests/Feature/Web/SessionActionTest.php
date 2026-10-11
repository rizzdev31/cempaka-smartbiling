<?php

namespace Tests\Feature\Web;

use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\BillingSession;
use App\Models\Customer;
use App\Models\FnbOrderItem;
use App\Models\Package;
use App\Models\Shift;
use App\Models\Station;
use App\Models\StationType;
use App\Models\User;
use App\Support\Audit\AuditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Operasi sesi dari Admin Web — DEC-045.
 *
 * Sekarang ada DUA pintu ke operasi yang memegang uang. Itu aman hanya
 * karena keduanya memanggil Service yang sama. Test di sini membuktikannya
 * lewat **akibatnya** — state machine ditegakkan, harga dihitung server,
 * audit log terisi — bukan dengan membaca kode.
 *
 * Golden path versi web: mulai → bayar → F&B → extend → checkout.
 */
class SessionActionTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private StationType $tipe;

    private Station $station;

    private Package $paket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tipe = $this->stationType();
        $this->station = $this->station($this->tipe, ['code' => 'ST01']);
        $this->paket = $this->package($this->tipe, ['name' => '1 Jam', 'price' => 10_000]);
    }

    private function owner(): User
    {
        return $this->operator(['name' => 'Bos', 'role' => UserRole::OWNER]);
    }

    private function mulai(array $extra = []): BillingSession
    {
        $this->post(route('admin.sessions.store'), $extra + [
            'station_id' => $this->station->id,
            'package_id' => $this->paket->id,
            'mode' => 'PREPAID',
            'customer_name' => 'Andi',
        ])->assertRedirect();

        return BillingSession::query()->latest('created_at')->firstOrFail();
    }

    // ── Golden path lewat web ─────────────────────────────────────────

    public function test_alur_penuh_mulai_sampai_checkout(): void
    {
        $this->actingAs($this->owner());

        $sesi = $this->mulai();
        $this->assertSame(SessionStatus::PENDING_PAYMENT, $sesi->status);

        // Bayar → sesi mulai berjalan.
        $this->post(route('admin.sessions.pay', $sesi), ['method' => 'CASH', 'amount' => 10_000])
            ->assertSessionHasNoErrors();
        $this->assertSame(SessionStatus::ACTIVE, $sesi->fresh()->status);

        // F&B masuk ke tagihan meja.
        $produk = $this->fnbProduct(['name' => 'Es Teh', 'price' => 5_000]);
        $this->post(route('admin.sessions.fnb', $sesi), [
            'items' => [['product_id' => $produk->id, 'qty' => 2]],
        ])->assertSessionHasNoErrors();

        // Extend 30 menit.
        $this->post(route('admin.sessions.extend', $sesi), ['duration_minutes' => 30])
            ->assertSessionHasNoErrors();

        // Checkout sisanya.
        $sisa = $sesi->fresh()->totals()->balanceDue();
        $this->post(route('admin.sessions.checkout', $sesi), ['method' => 'CASH', 'amount' => $sisa])
            ->assertSessionHasNoErrors();

        $selesai = $sesi->fresh();
        $this->assertSame(SessionStatus::COMPLETED, $selesai->status);
        $this->assertNotNull($selesai->receipt_number);
    }

    public function test_harga_extend_dihitung_server_bukan_dikirim_client(): void
    {
        /*
         * CLAUDE.md §4. Form tidak pernah mengirim harga; kalau suatu saat
         * ia mengirimnya dan server memakainya, test ini akan tetap lulus —
         * karena itu yang diperiksa adalah HASILNYA: 30 menit pada tarif
         * 10.000/jam harus jadi 5.000, apa pun yang dikirim.
         */
        $this->actingAs($this->owner());
        $sesi = $this->mulai();
        $this->post(route('admin.sessions.pay', $sesi), ['method' => 'CASH', 'amount' => 10_000]);

        $this->post(route('admin.sessions.extend', $sesi), [
            'duration_minutes' => 30,
            'price' => 1,          // diabaikan — bukan field yang divalidasi
        ])->assertSessionHasNoErrors();

        $extend = $sesi->fresh()->items()->where('type', 'EXTEND')->firstOrFail();
        $this->assertSame(5_000, (int) $extend->subtotal);
    }

    // ── State machine tetap yang memutuskan ───────────────────────────

    public function test_sesi_yang_sudah_selesai_tidak_bisa_di_checkout_lagi(): void
    {
        $this->actingAs($this->owner());
        $sesi = $this->mulai();
        $this->post(route('admin.sessions.pay', $sesi), ['method' => 'CASH', 'amount' => 10_000]);
        $this->post(route('admin.sessions.checkout', $sesi), []);

        $this->assertSame(SessionStatus::COMPLETED, $sesi->fresh()->status);

        // Pintu kedua tidak boleh punya jalan pintas.
        $this->post(route('admin.sessions.checkout', $sesi), [])
            ->assertSessionHasErrors();
    }

    public function test_station_yang_sudah_dipakai_menolak_sesi_kedua(): void
    {
        $this->actingAs($this->owner());
        $this->mulai();

        $this->post(route('admin.sessions.store'), [
            'station_id' => $this->station->id,
            'package_id' => $this->paket->id,
            'mode' => 'PREPAID',
            'customer_name' => 'Budi',
        ])->assertSessionHasErrors();

        $this->assertSame(1, BillingSession::query()->count());
    }

    public function test_penolakan_service_jadi_pesan_form_bukan_halaman_500(): void
    {
        /*
         * `ApiException` hanya dirender JSON untuk `api/*`. Dibiarkan lewat
         * di web ia jadi halaman 500 kosong — padahal isinya kalimat yang
         * justru perlu dibaca.
         */
        $this->actingAs($this->owner());
        $sesi = $this->mulai();

        $response = $this->post(route('admin.sessions.extend', $sesi), ['duration_minutes' => 30]);

        $response->assertRedirect();
        $this->assertNotEmpty($response->getSession()->get('errors'));
    }

    // ── Aturan khusus web ─────────────────────────────────────────────

    public function test_alasan_pembatalan_wajib_di_web(): void
    {
        /*
         * API membolehkannya kosong. Di web diwajibkan: membatalkan sesi
         * dari belakang meja tanpa alasan adalah persis yang perlu
         * dijelaskan saat laporan tidak cocok.
         */
        $this->actingAs($this->owner());
        $sesi = $this->mulai();

        $this->post(route('admin.sessions.cancel', $sesi), [])
            ->assertSessionHasErrors('reason');

        $this->post(route('admin.sessions.cancel', $sesi), ['reason' => 'salah station'])
            ->assertSessionHasNoErrors();

        $this->assertSame(SessionStatus::CANCELLED, $sesi->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::SESSION_CANCELLED]);
    }

    public function test_member_dipilih_maka_nama_ketikan_diabaikan(): void
    {
        /*
         * DEC-008: satu sesi satu customer. Dua sumber nama akan membuat
         * struk dan riwayat member menyebut orang yang berbeda.
         */
        $this->actingAs($this->owner());
        $customer = Customer::query()->create(['name' => 'Siti', 'phone' => '0812999']);

        $sesi = $this->mulai(['customer_id' => $customer->id, 'customer_name' => 'Bukan Siti']);

        $this->assertSame($customer->id, $sesi->customer_id);
        $this->assertNull($sesi->customer_name);
        $this->assertSame('Siti', $sesi->customerLabel());
    }

    public function test_baris_fnb_dengan_jumlah_nol_dibuang(): void
    {
        /*
         * Form menampilkan SELURUH menu dengan kolom jumlah. Tanpa
         * penyaringan, setiap pesanan berisi puluhan baris nol.
         */
        $this->actingAs($this->owner());
        $sesi = $this->mulai();
        $this->post(route('admin.sessions.pay', $sesi), ['method' => 'CASH', 'amount' => 10_000]);

        $a = $this->fnbProduct(['name' => 'Es Teh', 'price' => 5_000]);
        $b = $this->fnbProduct(['name' => 'Kopi', 'price' => 8_000]);

        $this->post(route('admin.sessions.fnb', $sesi), [
            'items' => [
                ['product_id' => $a->id, 'qty' => 1],
                ['product_id' => $b->id, 'qty' => 0],
            ],
        ])->assertSessionHasNoErrors();

        /*
         * Diperiksa di `fnb_order_items`, bukan di `session_items`: sesi
         * hanya mendapat SATU baris ringkasan per pesanan, jadi baris nol
         * yang lolos tidak akan terlihat dari sana.
         */
        $baris = FnbOrderItem::query()->get();

        $this->assertCount(1, $baris);
        $this->assertSame('Es Teh', $baris->first()->name);
        $this->assertSame(5_000, (int) $baris->first()->subtotal);
    }

    public function test_semua_jumlah_nol_ditolak_dengan_pesan(): void
    {
        $this->actingAs($this->owner());
        $sesi = $this->mulai();
        $produk = $this->fnbProduct();

        $this->post(route('admin.sessions.fnb', $sesi), [
            'items' => [['product_id' => $produk->id, 'qty' => 0]],
        ])->assertSessionHasErrors('items');
    }

    // ── Shift ─────────────────────────────────────────────────────────

    public function test_buka_shift_dari_web(): void
    {
        $this->actingAs($this->owner())
            ->post(route('admin.shifts.open'), ['opening_cash' => 200_000])
            ->assertSessionHas('sukses');

        $this->assertDatabaseHas('shifts', ['opening_cash' => 200_000, 'closed_at' => null]);
    }

    public function test_tidak_boleh_dua_shift_terbuka(): void
    {
        $owner = $this->owner();
        Shift::query()->create([
            'operator_id' => $owner->id, 'opened_at' => now(), 'opening_cash' => 100_000,
        ]);

        $this->actingAs($owner)
            ->post(route('admin.shifts.open'), ['opening_cash' => 200_000])
            ->assertSessionHasErrors('opening_cash');
    }

    // ── Pintu ─────────────────────────────────────────────────────────

    public function test_operator_tidak_bisa_memakai_aksi_web(): void
    {
        // Operator memakai tablet. Admin Web tetap owner/admin saja
        // (DEC-043) — DEC-045 menambah FITUR, bukan membuka pintunya.
        $this->actingAs($this->operator(['role' => UserRole::OPERATOR]))
            ->post(route('admin.sessions.store'), [
                'station_id' => $this->station->id,
                'package_id' => $this->paket->id,
                'mode' => 'PREPAID',
            ])
            ->assertRedirect('/login');

        $this->assertSame(0, BillingSession::query()->count());
    }

    public function test_tamu_tidak_bisa_memakai_aksi_web(): void
    {
        $this->post(route('admin.sessions.store'), [
            'station_id' => $this->station->id,
            'package_id' => $this->paket->id,
            'mode' => 'PREPAID',
        ])->assertRedirect('/login');
    }
}
