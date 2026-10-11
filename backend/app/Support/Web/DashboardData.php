<?php

namespace App\Support\Web;

use App\Enums\FnbOrderStatus;
use App\Enums\SessionItemType;
use App\Enums\SessionStatus;
use App\Models\BillingSession;
use App\Models\FnbOrder;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Station;
use App\Support\Time\BusinessDay;
use Illuminate\Support\Facades\DB;

/**
 * Angka dashboard Admin Web — PRD §19 "Dashboard".
 *
 * Bentuk array-nya mengikuti yang dipakai view sejak DEC-042, jadi tampilan
 * tidak ikut berubah saat data contohnya diganti data nyata.
 *
 * ## Dua aturan yang dipegang di sini
 *
 * **1. "Hari ini" adalah hari Jakarta, bukan hari UTC.** Semua rentang lewat
 * [BusinessDay]. Lihat kelas itu untuk alasannya — ringkasnya, batas UTC
 * memotong tepat di jam tersibuk rental dan laporannya tidak akan pernah
 * cocok dengan isi laci.
 *
 * **2. "Pendapatan" = uang yang BENAR-BENAR diterima**, yaitu pembayaran
 * berstatus CONFIRMED — bukan nilai transaksi. Sesi Open Tab yang masih
 * berjalan punya tagihan berjalan, tapi uangnya belum ada di laci. Mencampur
 * keduanya membuat angka dashboard tidak bisa dicocokkan dengan apa pun.
 *
 * Definisi yang sama dipakai `ShiftService::summary()`, jadi omzet shift dan
 * omzet harian memakai dasar yang sama.
 */
final class DashboardData
{
    /** Status yang berarti station sedang dipakai. */
    private const SEDANG_MAIN = [SessionStatus::ACTIVE, SessionStatus::WARNING];

    /** Pesanan F&B yang belum sampai ke meja. */
    private const FNB_BELUM_SELESAI = [
        FnbOrderStatus::PENDING,
        FnbOrderStatus::PROCESSING,
        FnbOrderStatus::READY,
    ];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        [$dariIni, $sampaiIni] = BusinessDay::today();
        [$dariKemarin, $sampaiKemarin] = BusinessDay::yesterday();

        $sesiAktif = self::sesiSedangMain()->count();
        $stationTotal = Station::query()->count();
        $selesaiHariIni = self::sesiSelesai($dariIni, $sampaiIni);
        $selesaiKemarin = self::sesiSelesai($dariKemarin, $sampaiKemarin);

        return [
            'generated_at' => now(),

            'revenue_today' => self::uangMasuk($dariIni, $sampaiIni),
            'revenue_yesterday' => self::uangMasuk($dariKemarin, $sampaiKemarin),

            'sessions_active' => $sesiAktif,
            'stations_total' => $stationTotal,

            'fnb_open' => FnbOrder::query()
                ->whereIn('status', self::FNB_BELUM_SELESAI)
                ->count(),

            'members_new' => Membership::query()
                ->whereBetween('joined_at', [$dariIni, $sampaiIni])
                ->count(),

            /*
             * Okupansi SEKARANG, bukan rata-rata sepanjang hari. Yang dicari
             * owner saat membuka halaman ini adalah "berapa meja terisi detik
             * ini", dan rata-rata harian akan menjawab pertanyaan lain dengan
             * angka yang mirip — jenis kekeliruan yang tidak terlihat.
             */
            'occupancy_percent' => $stationTotal === 0
                ? 0.0
                : round($sesiAktif / $stationTotal * 100, 1),

            /*
             * Pembanding kemarin ikut dikirim untuk SETIAP angka yang punya
             * badge perubahan.
             *
             * Versi data contoh menulis "↑ 12,5%" sebagai teks mati di view.
             * Begitu datanya jadi nyata, badge itu tetap mengklaim naik 12,5%
             * di atas angka nol — persis jenis kebohongan yang paling sulit
             * disadari, karena bentuknya meyakinkan.
             */
            'sessions_done_today' => $selesaiHariIni->count(),
            'sessions_done_yesterday' => $selesaiKemarin->count(),
            'avg_minutes' => (int) round($selesaiHariIni->avg('actual_duration_minutes') ?? 0),
            'avg_minutes_yesterday' => (int) round($selesaiKemarin->avg('actual_duration_minutes') ?? 0),

            'revenue_by_day' => self::uangMasukPerHari(7),
            'recent_sessions' => self::sesiTerakhir(8),
        ];
    }

    /** Selisih persen, dibulatkan satu desimal. Pembagi nol dianggap 0. */
    public static function delta(int $now, int $before): float
    {
        return $before === 0 ? 0.0 : round((($now - $before) / $before) * 100, 1);
    }

    private static function uangMasuk(\DateTimeInterface $dari, \DateTimeInterface $sampai): int
    {
        return (int) Payment::query()
            ->where('status', 'CONFIRMED')
            ->where('confirmed_at', '>=', $dari)
            ->where('confirmed_at', '<', $sampai)
            ->sum('amount');
    }

    /** @return list<array{label: string, value: int, active?: bool}> */
    private static function uangMasukPerHari(int $hari): array
    {
        $rentang = BusinessDay::lastDays($hari);

        /*
         * Satu query untuk seluruh rentang, bukan satu query per hari.
         * Tujuh query berurutan terasa ringan di laptop kosong dan menjadi
         * beban nyata begitu tabel payments berisi ribuan baris.
         */
        $awal = $rentang[0]['from'];
        $akhir = $rentang[count($rentang) - 1]['to'];

        $zona = BusinessDay::timezone();

        $perHari = Payment::query()
            ->where('status', 'CONFIRMED')
            ->where('confirmed_at', '>=', $awal)
            ->where('confirmed_at', '<', $akhir)
            // Dikonversi ke zona rental DI DALAM query — mengelompokkan
            // dengan DATE(confirmed_at) mentah akan memotong pada 07:00 WIB.
            ->selectRaw('DATE(CONVERT_TZ(confirmed_at, ?, ?)) AS hari, SUM(amount) AS jumlah', ['+00:00', self::offset($zona)])
            ->groupBy('hari')
            ->pluck('jumlah', 'hari');

        $hasil = [];

        foreach ($rentang as $i => $r) {
            $kunci = $r['date']->format('Y-m-d');

            $hasil[] = array_filter([
                'label' => $r['label'],
                'value' => (int) ($perHari[$kunci] ?? 0),
                'active' => $i === count($rentang) - 1 ? true : null,
            ], fn ($v) => $v !== null);
        }

        return $hasil;
    }

    /**
     * `CONVERT_TZ` butuh tabel zona waktu MySQL yang sering belum diisi di
     * Windows, jadi offset-nya dikirim sebagai angka (`+07:00`) yang selalu
     * dipahami. Konsekuensinya DST tidak ditangani — tidak relevan untuk
     * WIB, yang tidak punya DST.
     */
    private static function offset(string $zona): string
    {
        return BusinessDay::now()->setTimezone($zona)->format('P');
    }

    private static function sesiSedangMain()
    {
        return BillingSession::query()->whereIn('status', self::SEDANG_MAIN);
    }

    private static function sesiSelesai(\DateTimeInterface $dari, \DateTimeInterface $sampai)
    {
        return BillingSession::query()
            ->where('status', SessionStatus::COMPLETED)
            ->where('ended_at', '>=', $dari)
            ->where('ended_at', '<', $sampai)
            ->get();
    }

    /**
     * Sesi terbaru untuk tabel di bawah dashboard.
     *
     * Totalnya dihitung dari `session_items`, bukan dari pembayaran: sesi
     * yang masih berjalan belum dibayar sama sekali, dan menampilkan Rp 0
     * untuk meja yang sudah main dua jam akan terbaca sebagai fakta.
     *
     * @return list<array<string, mixed>>
     */
    private static function sesiTerakhir(int $jumlah): array
    {
        $sesi = BillingSession::query()
            ->with(['station.stationType'])
            ->orderByDesc('created_at')
            ->limit($jumlah)
            ->get();

        if ($sesi->isEmpty()) {
            return [];
        }

        $total = DB::table('session_items')
            ->whereIn('session_id', $sesi->pluck('id'))
            ->whereNot('type', SessionItemType::DISCOUNT->value)
            ->groupBy('session_id')
            ->pluck(DB::raw('SUM(subtotal)'), 'session_id');

        return $sesi->map(fn (BillingSession $s) => [
            'station' => $s->station?->code ?? '—',
            'console' => $s->station?->stationType?->name ?? '—',
            'customer' => $s->customerLabel(),
            'package' => $s->package_name ?? 'Open Tab',
            'started' => $s->started_at
                ? $s->started_at->setTimezone(BusinessDay::timezone())->format('H:i')
                : '—',
            'status' => $s->status->value,
            'minutes' => (int) ($s->actual_duration_minutes ?? 0),
            'total' => (int) ($total[$s->id] ?? 0),
        ])->all();
    }
}
