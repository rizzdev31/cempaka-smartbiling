<?php

namespace Tests\Feature\Web;

use App\Enums\UserRole;
use App\Models\BillingSession;
use App\Models\CashExpense;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\Station;
use App\Models\StationType;
use App\Models\User;
use App\Support\Time\BusinessDay;
use App\Support\Web\DashboardData;
use App\Support\Web\ReportData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesBillingWorld;
use Tests\TestCase;

/**
 * Halaman Laporan — PRD §19 & §27, DEC-050.
 *
 * ## Yang dijaga di sini
 *
 * Bukan "halamannya muncul". Laporan yang tampil rapi tapi angkanya
 * sedikit salah jauh lebih berbahaya daripada halaman yang error: ia
 * dipakai mengambil keputusan, dan tidak ada yang menaruh curiga pada
 * angka yang terlihat masuk akal.
 *
 * Tiga hal yang paling mudah salah dan paling sulit terlihat:
 *
 * 1. Batas hari. `whereDate(..., today())` memotong pada 07:00 WIB dan
 *    membuang omzet dini hari ke hari sebelumnya.
 * 2. Baris yang tidak menjumlah ke totalnya sendiri.
 * 3. Durasi yang terhitung berkali-kali karena join ke tabel item.
 */
class ReportTest extends TestCase
{
    use MakesBillingWorld;
    use RefreshDatabase;

    private function owner(): User
    {
        return $this->operator(['name' => 'Bos', 'role' => UserRole::OWNER]);
    }

    /**
     * Satu sesi selesai dengan rental + F&B dan satu pembayaran tunai,
     * seluruhnya pada waktu `$jam` WIB hari ini.
     */
    private function transaksi(string $jam, int $rental, int $fnb = 0, string $metode = 'CASH'): void
    {
        $tz = BusinessDay::timezone();
        $saat = BusinessDay::now()->startOfDay()->setTimeFromTimeString($jam)->utc();

        $tipe = $this->tipeSekali();
        $station = $this->stationSekali($tipe);

        $sesi = BillingSession::query()->create([
            'code' => 'S-'.uniqid(),
            'station_id' => $station->id,
            'customer_name' => 'Uji',
            'package_id' => $this->paketSekali()->id,
            'package_name' => '1 Jam',
            'package_duration_minutes' => 60,
            'package_price' => $rental,
            'hourly_rate' => $rental,
            'mode' => 'PREPAID',
            'status' => 'COMPLETED',
            'started_at' => $saat,
            'end_at' => $saat->addHour(),
            'ended_at' => $saat->addHour(),
            'actual_duration_minutes' => 60,
            'billable_duration_minutes' => 60,
        ]);

        $sesi->items()->create([
            'type' => 'RENTAL', 'name' => '1 Jam', 'qty' => 1,
            'unit_price' => $rental, 'subtotal' => $rental, 'is_paid' => true,
        ]);

        if ($fnb > 0) {
            $sesi->items()->create([
                'type' => 'FNB', 'name' => 'F&B', 'qty' => 1,
                'unit_price' => $fnb, 'subtotal' => $fnb, 'is_paid' => true,
            ]);
        }

        // `created_at` diisi belakangan: model mengisinya dengan "sekarang"
        // saat dibuat, dan seluruh laporan memakai kolom itu sebagai waktu
        // transaksi.
        $sesi->items()->update(['created_at' => $saat]);

        Payment::query()->create([
            'session_id' => $sesi->id,
            'method' => $metode,
            'amount' => $rental + $fnb,
            'status' => 'CONFIRMED',
            'reference' => $metode === 'QRIS_STATIC' ? 'TRX-'.uniqid() : null,
            'confirmed_at' => $saat,
        ]);

        unset($tz);
    }

    private ?StationType $tipe = null;

    private ?Station $station = null;

    private function tipeSekali(): StationType
    {
        return $this->tipe ??= $this->stationType();
    }

    private function stationSekali(StationType $tipe): Station
    {
        return $this->station ??= $this->station($tipe);
    }

    private ?Package $paket = null;

    /*
     * `??=`, bukan dibuat ulang tiap kali.
     *
     * Nama tipe konsol dan kode station unik, jadi helper bawaan hanya
     * boleh dipanggil sekali per test. Dipanggil dua kali, yang gagal
     * bukan baris yang memanggilnya — dan pesan errornya tidak menyebut
     * kata "unik" sama sekali.
     */
    private function paketSekali(): Package
    {
        return $this->paket ??= $this->package($this->tipeSekali());
    }

    // ── Pintu ─────────────────────────────────────────────────────────

    public function test_halaman_laporan_terbuka_untuk_owner(): void
    {
        $this->actingAs($this->owner())
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('Laporan');
    }

    public function test_operator_tidak_bisa_membuka_laporan(): void
    {
        // Laporan memuat seluruh omzet dan pengeluaran. Operator memakai
        // tablet, dan tidak punya alasan melihat angka sepanjang bulan.
        $this->actingAs($this->operator(['role' => UserRole::OPERATOR]))
            ->get(route('admin.reports'))
            ->assertRedirect('/login');
    }

    // ── Angka ─────────────────────────────────────────────────────────

    public function test_uang_diterima_hanya_pembayaran_terkonfirmasi(): void
    {
        $this->transaksi('10:00', 20_000, 5_000);

        [$from, $to] = BusinessDay::today();
        $d = ReportData::all($from, $to);

        $this->assertSame(25_000, $d['diterima']);
        $this->assertSame(20_000, $d['rental']);
        $this->assertSame(5_000, $d['fnb']);
    }

    public function test_transaksi_dini_hari_tetap_masuk_hari_ini(): void
    {
        /*
         * Inti dari BusinessDay, dan jam paling ramai rental.
         *
         * Batas UTC memotong pada 07:00 WIB: transaksi jam 01:00 WIB akan
         * terbuang ke hari sebelumnya, dan laporan harian tidak akan pernah
         * cocok dengan isi laci.
         */
        $this->transaksi('01:00', 30_000);

        [$from, $to] = BusinessDay::today();

        $this->assertSame(30_000, ReportData::all($from, $to)['diterima']);
    }

    public function test_transaksi_tepat_tengah_malam_hanya_dihitung_sekali(): void
    {
        /*
         * Bug nyata, ditemukan karena Dashboard dan Laporan menyebut angka
         * berbeda untuk hari yang sama: Rp 346.000 lawan Rp 394.000.
         *
         * Penyebabnya `whereBetween`, yang inklusif di KEDUA ujungnya.
         * Pembayaran tepat pada batas hari ikut terhitung di hari ini DAN
         * besok. Tidak ada error, tidak ada yang ganjil di layar — hanya
         * dua halaman yang tidak sepakat, dan tidak ada cara tahu mana
         * yang benar.
         *
         * Rentang harus setengah terbuka: `>= from AND < to`.
         */
        $this->transaksi('10:00', 100_000);

        // Geser pembayarannya ke detik pertama HARI BERIKUTNYA.
        [$from, $to] = BusinessDay::today();
        Payment::query()->update(['confirmed_at' => $to]);

        $this->assertSame(
            0,
            ReportData::all($from, $to)['diterima'],
            'Transaksi tepat di batas atas milik hari BERIKUTNYA, bukan hari ini.',
        );

        [$besokDari, $besokSampai] = BusinessDay::range(BusinessDay::now()->addDay());

        $this->assertSame(100_000, ReportData::all($besokDari, $besokSampai)['diterima']);
    }

    public function test_laporan_dan_dashboard_sepakat_untuk_hari_yang_sama(): void
    {
        /*
         * Dua halaman, satu definisi. Kalau keduanya boleh menghitung
         * sendiri-sendiri, perbedaannya baru ketahuan saat seseorang
         * membandingkan — dan yang dicurigai pertama adalah sistemnya,
         * bukan definisinya.
         */
        $this->transaksi('09:00', 50_000, 10_000);
        $this->transaksi('23:30', 70_000, 0, 'QRIS_STATIC');

        [$from, $to] = BusinessDay::today();

        $this->assertSame(
            DashboardData::all()['revenue_today'],
            ReportData::all($from, $to)['diterima'],
        );
    }

    public function test_transaksi_kemarin_tidak_ikut(): void
    {
        $this->transaksi('10:00', 20_000);

        // Geser satu pembayaran ke kemarin.
        Payment::query()->update(['confirmed_at' => BusinessDay::now()->subDay()->utc()]);

        [$from, $to] = BusinessDay::today();

        $this->assertSame(0, ReportData::all($from, $to)['diterima']);
    }

    public function test_kolom_omzet_station_menjumlah_ke_rental_plus_fnb(): void
    {
        /*
         * Versi pertama tidak begitu: tabel memakai "sesi yang berakhir"
         * sementara angka besar memakai "item yang dibuat", dan selisihnya
         * Rp 48.000 pada data uji.
         *
         * Laporan yang baris-barisnya tidak menjumlah ke totalnya sendiri
         * akan selalu dicurigai rusak, dan yang mengeceknya tidak punya cara
         * tahu mana yang benar.
         */
        $this->transaksi('10:00', 20_000, 5_000);
        $this->transaksi('14:00', 30_000, 7_000);

        [$from, $to] = BusinessDay::today();
        $d = ReportData::all($from, $to);

        $this->assertSame(
            $d['rental'] + $d['fnb'],
            array_sum(array_column($d['perStation'], 'omzet')),
        );
    }

    public function test_jam_main_tidak_terhitung_dua_kali_karena_dua_item(): void
    {
        /*
         * Sesi dengan rental + F&B punya DUA baris item. Join ke tabel item
         * lalu `SUM(billable_duration_minutes)` akan menghitung durasinya
         * dua kali — dan "jam main" bisa melebihi jumlah jam dalam sehari
         * tanpa ada yang terlihat salah.
         */
        $this->transaksi('10:00', 20_000, 5_000);

        [$from, $to] = BusinessDay::today();
        $d = ReportData::all($from, $to);

        $this->assertSame(60, array_sum(array_column($d['perStation'], 'menit')));
        $this->assertSame(1, array_sum(array_column($d['perStation'], 'sesi')));
    }

    public function test_pengeluaran_kasir_dikelompokkan_dan_mengurangi_kas_bersih(): void
    {
        $this->transaksi('10:00', 100_000);

        $owner = $this->owner();
        $shift = Shift::query()->create([
            'operator_id' => $owner->id,
            'opened_at' => now(),
            'opening_cash' => 0,
        ]);

        foreach ([['Galon & air', 20_000], ['Galon & air', 5_000], ['Kebersihan', 12_000]] as [$kat, $nilai]) {
            CashExpense::query()->create([
                'shift_id' => $shift->id,
                'amount' => $nilai,
                'category' => $kat,
                'note' => 'uji pengeluaran',
                'actor_id' => $owner->id,
            ]);
        }

        [$from, $to] = BusinessDay::today();
        $d = ReportData::all($from, $to);

        $this->assertSame(37_000, $d['pengeluaranTotal']);
        $this->assertSame(25_000, $d['pengeluaran']['Galon & air']);
        $this->assertSame(12_000, $d['pengeluaran']['Kebersihan']);
    }

    public function test_tunai_dan_qris_dipisah(): void
    {
        $this->transaksi('10:00', 20_000, 0, 'CASH');
        $this->transaksi('11:00', 30_000, 0, 'QRIS_STATIC');

        [$from, $to] = BusinessDay::today();
        $d = ReportData::all($from, $to);

        $this->assertSame(20_000, $d['tunai']);
        $this->assertSame(30_000, $d['qris']);
        $this->assertSame(50_000, $d['diterima']);
    }

    public function test_hpp_memakai_angka_yang_dibekukan_bukan_harga_modal_hari_ini(): void
    {
        /*
         * DEC-050. Tanpa pembekuan, laporan laba bulan lalu ikut berubah
         * setiap kali harga modal diperbarui — tanpa error, dan tanpa ada
         * yang bisa menjelaskan kenapa angkanya tidak sama lagi.
         */
        $produk = $this->fnbProduct(['name' => 'Es Teh', 'price' => 5_000, 'hpp' => 1_500]);

        $tipe = $this->tipeSekali();
        $sesi = BillingSession::query()->create([
            'code' => 'S-'.uniqid(),
            'station_id' => $this->stationSekali($tipe)->id,
            'customer_name' => 'Uji',
            'package_id' => $this->paketSekali()->id,
            'package_name' => '1 Jam',
            'package_duration_minutes' => 60,
            'package_price' => 20_000,
            'hourly_rate' => 20_000,
            'mode' => 'PREPAID',
            'status' => 'ACTIVE',
            'started_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        $order = $sesi->fnbOrders()->create([
            'code' => 'F-'.uniqid(),
            'status' => 'DELIVERED',
            'source' => 'OPERATOR',
            'total' => 10_000,
        ]);

        $order->items()->create([
            'fnb_product_id' => $produk->id,
            'name' => 'Es Teh',
            'qty' => 2,
            'unit_price' => 5_000,
            'unit_hpp' => 1_500,   // dibekukan saat order dibuat
            'subtotal' => 10_000,
        ]);

        // Harga modal naik SETELAH penjualan.
        $produk->update(['hpp' => 2_500]);

        [$from, $to] = BusinessDay::today();
        $d = ReportData::all($from, $to);

        // 2 x 1.500, bukan 2 x 2.500.
        $this->assertSame(3_000, $d['hpp']['modal']);
        $this->assertSame(0, $d['hpp']['tanpaHpp']);
    }

    public function test_porsi_tanpa_hpp_dihitung_bukan_dianggap_nol(): void
    {
        /*
         * Baris lama (sebelum DEC-050) tidak punya `unit_hpp`. Menganggapnya
         * nol membuat laba kotor terlihat lebih besar dari yang sebenarnya,
         * dan tidak ada yang tahu sebagian datanya hilang.
         */
        $produk = $this->fnbProduct(['name' => 'Kopi', 'price' => 7_000, 'hpp' => null]);

        $tipe = $this->tipeSekali();
        $sesi = BillingSession::query()->create([
            'code' => 'S-'.uniqid(),
            'station_id' => $this->stationSekali($tipe)->id,
            'customer_name' => 'Uji',
            'package_id' => $this->paketSekali()->id,
            'package_name' => '1 Jam',
            'package_duration_minutes' => 60,
            'package_price' => 20_000,
            'hourly_rate' => 20_000,
            'mode' => 'PREPAID',
            'status' => 'ACTIVE',
            'started_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        $order = $sesi->fnbOrders()->create([
            'code' => 'F-'.uniqid(), 'status' => 'DELIVERED',
            'source' => 'OPERATOR', 'total' => 21_000,
        ]);

        $order->items()->create([
            'fnb_product_id' => $produk->id, 'name' => 'Kopi', 'qty' => 3,
            'unit_price' => 7_000, 'unit_hpp' => null, 'subtotal' => 21_000,
        ]);

        [$from, $to] = BusinessDay::today();
        $d = ReportData::all($from, $to);

        $this->assertSame(0, $d['hpp']['modal']);
        $this->assertSame(3, $d['hpp']['tanpaHpp']);

        $this->actingAs($this->owner())
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('3 porsi terjual tanpa HPP tercatat');
    }

    // ── Rentang ───────────────────────────────────────────────────────

    public function test_satu_hari_digambar_per_jam_dua_puluh_empat_batang(): void
    {
        // "Hari ini" adalah tampilan yang paling sering dibuka, dan satu
        // batang tunggal di kotak selebar layar tidak memberi tahu apa pun.
        [$from, $to] = BusinessDay::today();
        $d = ReportData::all($from, $to);

        $this->assertCount(24, $d['perHari']);
        $this->assertSame('Jam', $d['satuanWaktu']);
    }

    public function test_rentang_beberapa_hari_digambar_per_hari(): void
    {
        $r = ReportData::rentang('7-hari', null, null);
        $d = ReportData::all($r['from'], $r['to']);

        $this->assertCount(7, $d['perHari']);
        $this->assertSame('Hari', $d['satuanWaktu']);
    }

    public function test_hari_tanpa_transaksi_tetap_punya_batangnya(): void
    {
        /*
         * Kalau hari kosong dibuang, dua hari sepi berturut-turut akan
         * terlihat seperti satu hari dan trennya berbohong.
         */
        $r = ReportData::rentang('7-hari', null, null);
        $d = ReportData::all($r['from'], $r['to']);

        $this->assertSame(7, count($d['perHari']));
        $this->assertSame(0, $d['perHari'][0]['total']);
    }

    public function test_tanggal_terbalik_dibetulkan_bukan_menghasilkan_laporan_kosong(): void
    {
        /*
         * Dua kolom tanggal yang tertukar akan menghasilkan rentang negatif,
         * dan laporan kosong terlihat persis seperti "tidak ada transaksi".
         */
        $r = ReportData::rentang(null, '2026-10-12', '2026-10-05');

        $this->assertTrue($r['from']->lessThan($r['to']));
        $this->assertSame(8, $r['hari']);
    }

    public function test_tanggal_ngawur_jatuh_ke_hari_ini_bukan_error_500(): void
    {
        $r = ReportData::rentang(null, 'bukan-tanggal', 'juga-bukan');

        $this->assertSame('Hari ini', $r['label']);
    }

    public function test_rentang_kustom_menang_atas_pilihan_periode(): void
    {
        $r = ReportData::rentang('30-hari', '2026-10-01', '2026-10-03');

        $this->assertSame(3, $r['hari']);
    }

    public function test_rentang_sangat_panjang_tidak_menggambar_grafik(): void
    {
        // 90 batang tidak terbaca di layar mana pun. Angkanya tetap benar.
        $r = ReportData::rentang(null, '2025-01-01', '2026-10-12');
        $d = ReportData::all($r['from'], $r['to']);

        $this->assertSame([], $d['perHari']);

        $this->actingAs($this->owner())
            ->get(route('admin.reports', ['dari' => '2025-01-01', 'sampai' => '2026-10-12']))
            ->assertOk()
            ->assertSee('Rentang terlalu panjang untuk grafik');
    }

    // ── Halaman ───────────────────────────────────────────────────────

    public function test_halaman_menampilkan_angka_yang_sama_dengan_datanya(): void
    {
        $this->transaksi('10:00', 150_000, 25_000);

        $this->actingAs($this->owner())
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('Rp 175.000')   // uang diterima
            ->assertSee('Rp 150.000')   // rental
            ->assertSee('Rp 25.000');   // F&B
    }

    public function test_menu_terlaris_memakai_nama_saat_terjual(): void
    {
        /*
         * Nama dibekukan ke baris order. Menu yang sudah diganti namanya
         * harus tetap tampil dengan nama saat ia terjual — kalau tidak,
         * laporan bulan lalu berubah isinya setiap kali menu di-rename.
         */
        $produk = $this->fnbProduct(['name' => 'Indomie Telur', 'price' => 12_000]);

        $tipe = $this->tipeSekali();
        $sesi = BillingSession::query()->create([
            'code' => 'S-'.uniqid(),
            'station_id' => $this->stationSekali($tipe)->id,
            'customer_name' => 'Uji', 'package_id' => $this->paketSekali()->id,
            'package_name' => '1 Jam',
            'package_duration_minutes' => 60, 'package_price' => 20_000,
            'hourly_rate' => 20_000, 'mode' => 'PREPAID', 'status' => 'ACTIVE',
            'started_at' => now(), 'end_at' => now()->addHour(),
        ]);

        $order = $sesi->fnbOrders()->create([
            'code' => 'F-'.uniqid(), 'status' => 'DELIVERED',
            'source' => 'OPERATOR', 'total' => 12_000,
        ]);
        $order->items()->create([
            'fnb_product_id' => $produk->id, 'name' => 'Indomie Telur', 'qty' => 1,
            'unit_price' => 12_000, 'unit_hpp' => 4_000, 'subtotal' => 12_000,
        ]);

        $produk->update(['name' => 'Mie Goreng Spesial']);

        $this->actingAs($this->owner())
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('Indomie Telur')
            ->assertDontSee('Mie Goreng Spesial');
    }
}
