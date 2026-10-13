<?php

namespace Tests\Feature\Web;

use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\BillingSession;
use App\Models\Customer;
use App\Models\Membership;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Seluruh halaman Admin Web — PRD §19.
 *
 * Halaman Blade gagal saat DIRENDER, bukan saat ditulis: relasi yang salah
 * nama, kolom yang tidak ada, komponen yang kurang prop. Semuanya hanya
 * muncul kalau halamannya benar-benar dibuka.
 *
 * Berkas ini membukanya — dua kali untuk setiap halaman: sekali dengan
 * database kosong, sekali dengan data terisi. Keduanya perlu. Halaman yang
 * lulus hanya saat kosong tidak pernah melewati perulangan isinya; yang
 * lulus hanya saat terisi akan meledak di instalasi baru.
 */
class AdminPagesTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    /** @return list<array{string}> */
    public static function halaman(): array
    {
        return [
            'dashboard' => ['admin.dashboard'],
            'sesi' => ['admin.sessions'],
            'station' => ['admin.stations'],
            'fnb' => ['admin.fnb'],
            'harga' => ['admin.pricing'],
            'customer' => ['admin.customers'],
            'shift' => ['admin.shifts'],
            'audit' => ['admin.audit'],
            'pembayaran' => ['admin.payments'],
        ];
    }

    private function owner(): User
    {
        return $this->operator(['name' => 'Bos', 'role' => UserRole::OWNER]);
    }

    /** Data secukupnya supaya setiap perulangan di view benar-benar jalan. */
    private function isiDunia(): void
    {
        $type = $this->stationType();
        $station = $this->station($type, ['code' => 'ST01']);
        $package = $this->package($type);
        $this->fnbProduct(['name' => 'Es Teh', 'stock' => 2]);
        $this->fnbProduct(['name' => 'Indomie', 'stock' => null, 'category' => 'MAKANAN']);

        $customer = Customer::query()->create(['name' => 'Budi', 'phone' => '628123456789']);
        Membership::query()->create([
            'customer_id' => $customer->id,
            'tier' => 'BASIC',
            'is_active' => true,
            'joined_at' => now(),
        ]);

        BillingSession::query()->create([
            'code' => 'S-TEST-0001',
            'station_id' => $station->id,
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'package_name' => $package->name,
            'package_duration_minutes' => 60,
            'package_price' => 10_000,
            'hourly_rate' => 10_000,
            'mode' => SessionMode::PREPAID,
            'status' => SessionStatus::ACTIVE,
            'started_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        Shift::query()->create([
            'operator_id' => $this->owner()->id,
            'opened_at' => now()->subHours(2),
            'closed_at' => now()->subHour(),
            'opening_cash' => 200_000,
            'closing_cash' => 250_000,
            'note' => 'serah terima',
        ]);
    }

    #[DataProvider('halaman')]
    public function test_halaman_terbuka_saat_database_kosong(string $route): void
    {
        $this->actingAs($this->owner())->get(route($route))->assertOk();
    }

    #[DataProvider('halaman')]
    public function test_halaman_terbuka_saat_ada_data(string $route): void
    {
        $this->isiDunia();

        $this->actingAs($this->owner())->get(route($route))->assertOk();
    }

    #[DataProvider('halaman')]
    public function test_halaman_tertutup_untuk_tamu(string $route): void
    {
        $this->get(route($route))->assertRedirect('/login');
    }

    #[DataProvider('halaman')]
    public function test_halaman_tertutup_untuk_operator(string $route): void
    {
        // Operator memakai tablet. Admin Web menampilkan pendapatan seluruh
        // usaha dan audit log — bukan urusannya (DEC-043).
        $this->actingAs($this->operator(['role' => UserRole::OPERATOR]))
            ->get(route($route))
            ->assertRedirect('/login');
    }

    // ── Isi yang tidak boleh salah ────────────────────────────────────

    public function test_admin_melihat_harga_tapi_ditandai_hanya_lihat(): void
    {
        /*
         * DEC-020 mengunci perubahan tarif untuk owner. Admin tetap boleh
         * MELIHAT — mengetahui tarif bagian dari pekerjaannya — dan
         * halamannya harus menjelaskan kenapa dikunci, bukan sekadar
         * menyembunyikan tombol.
         */
        $this->isiDunia();

        $this->actingAs($this->operator(['role' => UserRole::ADMIN]))
            ->get(route('admin.pricing'))
            ->assertOk()
            ->assertSee('Hanya lihat')
            ->assertSee('DEC-020');
    }

    public function test_owner_ditandai_boleh_mengubah_harga(): void
    {
        $this->isiDunia();

        $this->actingAs($this->owner())
            ->get(route('admin.pricing'))
            ->assertOk()
            ->assertSee('Anda boleh mengubah harga');
    }

    public function test_filter_sesi_menyaring_dan_tidak_meledak_untuk_status_karangan(): void
    {
        $this->isiDunia();

        $this->actingAs($this->owner())
            ->get(route('admin.sessions', ['status' => 'ACTIVE']))
            ->assertOk()
            ->assertSee('S-TEST-0001');

        // Status yang tidak ada di enum diabaikan, bukan membuat query gagal.
        $this->actingAs($this->owner())
            ->get(route('admin.sessions', ['status' => 'NGAWUR']))
            ->assertOk();
    }

    public function test_pencarian_customer_menyaring(): void
    {
        $this->isiDunia();
        Customer::query()->create(['name' => 'Siti', 'phone' => '628999']);

        $this->actingAs($this->owner())
            ->get(route('admin.customers', ['q' => 'Budi']))
            ->assertOk()
            ->assertSee('Budi')
            ->assertDontSee('Siti');
    }

    public function test_station_tanpa_tv_disebut_belum_didaftarkan_bukan_offline(): void
    {
        /*
         * Dua keadaan yang berbeda akibatnya. "Offline" membuat teknisi
         * mencari kabel pada TV yang memang belum pernah dipasang.
         */
        $this->isiDunia();

        $this->actingAs($this->owner())
            ->get(route('admin.stations'))
            ->assertOk()
            ->assertSee('Belum didaftarkan');
    }

    public function test_kas_seharusnya_dikurangi_pengeluaran_kasir(): void
    {
        /*
         * DEC-046, dulu OD-029.
         *
         * Kas seharusnya = Modal + Tunai − Pengeluaran. Suku terakhirnya
         * dulu tidak ada, dan kasir jujur yang mengambil Rp 20.000 untuk
         * beli galon terlihat kekurangan Rp 20.000 saat tutup shift.
         *
         * Yang diperiksa ANGKANYA, bukan ada-tidaknya kata "pengeluaran" di
         * halaman. Kalimat yang menjelaskan rumus bisa benar sementara
         * hitungannya salah — itu keadaan versi sebelumnya.
         */
        $this->actingAs($owner = $this->owner());

        $shift = Shift::query()->create([
            'operator_id' => $owner->id,
            'opened_at' => now(),
            'opening_cash' => 200_000,
        ]);

        app(ShiftService::class)->recordExpense($shift, [
            'amount' => 20_000,
            'category' => 'Galon & air',
            'note' => 'beli galon 2 buah',
        ], $owner);

        $this->get(route('admin.shifts'))
            ->assertOk()
            ->assertSee('Rp 180.000')          // 200.000 − 20.000
            ->assertSee('beli galon 2 buah');  // keterangannya, bukan hanya nominalnya
    }
}
