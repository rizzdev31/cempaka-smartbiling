<?php

namespace Tests\Feature\Web;

use App\Enums\PaymentMethod;
use App\Enums\SessionItemType;
use App\Enums\SessionMode;
use App\Enums\SessionStatus;
use App\Enums\UserRole;
use App\Models\BillingSession;
use App\Models\Payment;
use App\Models\SessionItem;
use App\Models\StationType;
use App\Support\Time\BusinessDay;
use App\Support\Web\DashboardData;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Angka dashboard Admin Web — PRD §19.
 *
 * Yang paling penting di berkas ini adalah test batas zona waktu. Sisanya
 * akan ketahuan salah saat dilihat; yang itu tidak — angkanya tetap masuk
 * akal, hanya tercatat di hari yang keliru.
 */
class DashboardDataTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private int $urut = 0;

    /** `stationType()` namanya unik — dibuat sekali lalu dipakai ulang. */
    private function tipe(): StationType
    {
        return StationType::query()->first() ?? $this->stationType();
    }

    /**
     * Sesi minimum. `BillingSession` tidak punya factory — seluruh suite
     * membuatnya lewat endpoint supaya aturan bisnisnya ikut jalan. Di sini
     * yang diuji adalah query dashboard, bukan aturan sesi, jadi barisnya
     * dibuat langsung.
     */
    private function sesi(array $attributes = []): BillingSession
    {
        $type = $this->tipe();
        $this->urut++;

        /*
         * Station dibuat hanya kalau belum diberikan. Menaruhnya di array
         * default tidak cukup — PHP mengevaluasi seluruh literal sebelum
         * `+` memilih, jadi station tambahan tetap lahir dan `stations_total`
         * ikut membengkak.
         */
        $attributes['station_id'] ??= $this->station($type, ['code' => 'STX'.$this->urut])->id;

        return BillingSession::query()->create($attributes + [
            'code' => 'S-TEST-'.str_pad((string) $this->urut, 4, '0', STR_PAD_LEFT),
            'package_id' => $this->package($type, ['name' => 'Paket '.$this->urut])->id,
            'package_name' => 'Paket 1 Jam',
            'package_duration_minutes' => 60,
            'package_price' => 10_000,
            'hourly_rate' => 10_000,
            'mode' => SessionMode::PREPAID,
            'status' => SessionStatus::COMPLETED,
        ]);
    }

    private function bayar(int $jumlah, CarbonImmutable $kapan, string $status = 'CONFIRMED'): Payment
    {
        $session = $this->sesi();

        return Payment::query()->create([
            'session_id' => $session->id,
            'method' => PaymentMethod::CASH,
            'amount' => $jumlah,
            'status' => $status,
            'confirmed_at' => $kapan,
        ]);
    }

    public function test_pendapatan_hari_ini_menjumlahkan_pembayaran_terkonfirmasi(): void
    {
        $this->bayar(10_000, BusinessDay::now()->setTime(14, 0)->utc());
        $this->bayar(25_000, BusinessDay::now()->setTime(16, 30)->utc());

        $this->assertSame(35_000, DashboardData::all()['revenue_today']);
    }

    public function test_pembayaran_jam_dua_pagi_masuk_hari_ini_bukan_kemarin(): void
    {
        /*
         * INI test yang paling penting di berkas ini.
         *
         * 02:00 WIB = 19:00 UTC HARI SEBELUMNYA. Query yang memotong hari
         * pada 00:00 UTC akan melemparnya ke kemarin — dan jam itu justru
         * jam tersibuk rental PlayStation.
         *
         * Kesalahannya tidak akan terlihat dari layar: angkanya tetap wajar,
         * hanya di kolom yang salah, dan baru ketahuan saat laporan harian
         * tidak cocok dengan isi laci.
         */
        $duaPagi = BusinessDay::now()->setTime(2, 0);

        // Prasyarat test: pastikan memang melintasi batas hari UTC.
        $this->assertNotSame(
            $duaPagi->format('Y-m-d'),
            $duaPagi->utc()->format('Y-m-d'),
            'Zona waktu display tidak menggeser tanggal — test ini tidak membuktikan apa pun.',
        );

        $this->bayar(50_000, $duaPagi->utc());

        $data = DashboardData::all();

        $this->assertSame(50_000, $data['revenue_today']);
        $this->assertSame(0, $data['revenue_yesterday']);
    }

    public function test_pembayaran_belum_terkonfirmasi_tidak_dihitung(): void
    {
        // Pendapatan = uang yang BENAR-BENAR diterima, bukan nilai transaksi.
        $this->bayar(80_000, BusinessDay::now()->setTime(13, 0)->utc(), status: 'PENDING');

        $this->assertSame(0, DashboardData::all()['revenue_today']);
    }

    public function test_pembayaran_kemarin_masuk_kolom_kemarin(): void
    {
        $this->bayar(17_000, BusinessDay::now()->subDay()->setTime(20, 0)->utc());

        $data = DashboardData::all();

        $this->assertSame(0, $data['revenue_today']);
        $this->assertSame(17_000, $data['revenue_yesterday']);
    }

    public function test_okupansi_dihitung_dari_sesi_yang_sedang_main(): void
    {
        $type = $this->stationType();
        $stations = collect(range(1, 4))->map(fn ($i) => $this->station($this->tipe(), ['code' => "ST0{$i}"]));

        $this->sesi(['station_id' => $stations[0]->id, 'status' => SessionStatus::ACTIVE]);
        $this->sesi(['station_id' => $stations[1]->id, 'status' => SessionStatus::WARNING]);
        // Yang sudah selesai tidak boleh ikut dihitung sebagai terpakai.
        $this->sesi(['station_id' => $stations[2]->id, 'status' => SessionStatus::COMPLETED]);

        $data = DashboardData::all();

        $this->assertSame(2, $data['sessions_active']);
        $this->assertSame(4, $data['stations_total']);
        $this->assertSame(50.0, $data['occupancy_percent']);
    }

    public function test_tanpa_station_okupansi_nol_bukan_error(): void
    {
        // Keadaan nyata di instalasi baru sebelum seeder dijalankan.
        $this->assertSame(0.0, DashboardData::all()['occupancy_percent']);
    }

    public function test_grafik_selalu_tujuh_hari_dengan_hari_ini_di_ujung(): void
    {
        $data = DashboardData::all()['revenue_by_day'];

        $this->assertCount(7, $data);
        $this->assertTrue($data[6]['active'] ?? false, 'Kolom terakhir harus hari ini.');
        $this->assertArrayNotHasKey('active', $data[0]);
    }

    public function test_grafik_menaruh_uang_di_hari_yang_benar(): void
    {
        $this->bayar(11_000, BusinessDay::now()->setTime(12, 0)->utc());
        $this->bayar(22_000, BusinessDay::now()->subDays(2)->setTime(12, 0)->utc());

        $perHari = DashboardData::all()['revenue_by_day'];

        $this->assertSame(11_000, $perHari[6]['value']);
        $this->assertSame(22_000, $perHari[4]['value']);
        $this->assertSame(0, $perHari[5]['value']);
    }

    public function test_hari_tanpa_transaksi_bernilai_nol_bukan_hilang(): void
    {
        // Kolom yang hilang akan menggeser seluruh grafik ke kiri dan
        // menempelkan angka pada hari yang salah.
        $perHari = DashboardData::all()['revenue_by_day'];

        $this->assertSame(7, count($perHari));
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], array_column($perHari, 'value'));
    }

    public function test_sesi_terakhir_memakai_total_dari_item_bukan_pembayaran(): void
    {
        /*
         * Sesi yang masih berjalan belum dibayar sama sekali. Menampilkan
         * Rp 0 untuk meja yang sudah main dua jam akan terbaca sebagai fakta.
         */
        $session = $this->sesi([
            'station_id' => $this->station($this->tipe(), ['code' => 'ST09'])->id,
            'status' => SessionStatus::ACTIVE,
            'customer_name' => 'Budi',
        ]);

        SessionItem::query()->create([
            'session_id' => $session->id,
            'type' => SessionItemType::RENTAL,
            'name' => 'Paket 1 Jam',
            'qty' => 1,
            'unit_price' => 10_000,
            'subtotal' => 10_000,
        ]);
        SessionItem::query()->create([
            'session_id' => $session->id,
            'type' => SessionItemType::FNB,
            'name' => 'Es Teh',
            'qty' => 2,
            'unit_price' => 5_000,
            'subtotal' => 10_000,
        ]);

        $baris = collect(DashboardData::all()['recent_sessions'])
            ->firstWhere('station', 'ST09');

        $this->assertNotNull($baris);
        $this->assertSame(20_000, $baris['total']);
        $this->assertSame('Budi', $baris['customer']);
        $this->assertSame('ACTIVE', $baris['status']);
    }

    public function test_diskon_tidak_menggelembungkan_total_sesi(): void
    {
        $session = $this->sesi([
            'station_id' => $this->station($this->tipe(), ['code' => 'ST08'])->id,
            'status' => SessionStatus::COMPLETED,
        ]);

        SessionItem::query()->create([
            'session_id' => $session->id, 'type' => SessionItemType::RENTAL,
            'name' => 'Paket', 'qty' => 1, 'unit_price' => 10_000, 'subtotal' => 10_000,
        ]);
        // Diskon disimpan sebagai subtotal NEGATIF; kalau ikut dijumlahkan
        // mentah ia malah mengurangi dua kali di sebagian laporan.
        SessionItem::query()->create([
            'session_id' => $session->id, 'type' => SessionItemType::DISCOUNT,
            'name' => 'Diskon member', 'qty' => 1, 'unit_price' => -2_000, 'subtotal' => -2_000,
        ]);

        $baris = collect(DashboardData::all()['recent_sessions'])->firstWhere('station', 'ST08');

        $this->assertSame(10_000, $baris['total']);
    }

    public function test_halaman_kosong_tidak_mengklaim_pertumbuhan_apa_pun(): void
    {
        /*
         * Penjaga terhadap kekambuhan yang nyata.
         *
         * Versi data contoh menulis "↑ 12,5%" dan "↑ 6m" sebagai teks mati di
         * view. Angka itu bertahan setelah datanya diganti data nyata, dan
         * mengklaim pertumbuhan di atas NOL sesi — bentuknya meyakinkan, dan
         * justru itu yang membuatnya berbahaya.
         *
         * Database kosong: tidak boleh ada satu pun panah perubahan.
         */
        $this->actingAs($this->operator(['role' => UserRole::OWNER]))
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('↑')
            ->assertDontSee('↓');
    }

    public function test_badge_perubahan_muncul_kalau_ada_pembandingnya(): void
    {
        // Kebalikannya: kalau kemarin ADA angkanya, badge wajib muncul —
        // kalau tidak, test di atas lulus hanya karena badge-nya mati total.
        $this->bayar(100_000, BusinessDay::now()->subDay()->setTime(20, 0)->utc());
        $this->bayar(150_000, BusinessDay::now()->setTime(13, 0)->utc());

        $this->actingAs($this->operator(['role' => UserRole::OWNER]))
            ->get('/admin')
            ->assertOk()
            ->assertSee('↑');
    }

    public function test_dashboard_kosong_tidak_melempar_error(): void
    {
        // Instalasi baru: tidak ada sesi, tidak ada pembayaran, tidak ada apa pun.
        $data = DashboardData::all();

        $this->assertSame(0, $data['revenue_today']);
        $this->assertSame(0, $data['sessions_active']);
        $this->assertSame(0, $data['avg_minutes']);
        $this->assertSame([], $data['recent_sessions']);
    }
}
