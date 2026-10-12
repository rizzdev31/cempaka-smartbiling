<?php

namespace App\Support\Web;

use App\Enums\PaymentMethod;
use App\Enums\SessionItemType;
use App\Enums\SessionStatus;
use App\Models\CashExpense;
use App\Models\Payment;
use App\Support\Time\BusinessDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Angka halaman Laporan — PRD §19 "Reports" & §27, DEC-050.
 *
 * ## Satu definisi "pendapatan", dipakai di seluruh sistem
 *
 * **Pendapatan = uang yang BENAR-BENAR diterima**, yaitu payment berstatus
 * CONFIRMED di dalam rentang. Bukan nilai transaksi.
 *
 * Definisi yang sama dipakai `DashboardData` dan `ShiftService::summary()`.
 * Itu bukan kebetulan: kalau Laporan memakai dasar yang berbeda, angkanya
 * tidak akan pernah cocok dengan dashboard maupun dengan isi laci — dan
 * yang pertama dicurigai adalah sistemnya, bukan definisinya.
 *
 * Konsekuensi yang harus diketahui pembacanya, dan disebutkan di halaman:
 * sesi Open Tab yang masih berjalan **belum** masuk laporan. Uangnya memang
 * belum ada.
 *
 * ## Batas hari = tengah malam WIB
 *
 * Lewat [BusinessDay]. Batas UTC memotong tepat di jam tersibuk rental
 * (07:00 WIB) dan membuang omzet dini hari ke hari sebelumnya.
 *
 * ## Rentangnya SETENGAH TERBUKA: `>= from AND < to`
 *
 * Bukan `whereBetween`, yang inklusif di KEDUA ujungnya. Versi pertama
 * berkas ini memakai `whereBetween` di dua belas tempat, dan akibatnya
 * transaksi tepat pada tengah malam dihitung di hari ini **dan** besok.
 *
 * Ketahuannya karena Dashboard melaporkan Rp 346.000 untuk hari yang sama
 * dengan laporan ini menyebut Rp 394.000 — selisih Rp 48.000 dari satu
 * transaksi di detik pergantian hari. Tidak ada error, tidak ada yang
 * ganjil di layar mana pun; hanya dua halaman yang tidak sepakat.
 *
 * `BusinessDay::range()` sudah memperingatkan ini di komentarnya sendiri.
 */
final class ReportData
{
    /** Pilihan rentang di layar. Kuncinya ikut ke URL. */
    public const PERIODE = [
        'hari-ini' => 'Hari ini',
        '7-hari' => '7 hari terakhir',
        '30-hari' => '30 hari terakhir',
        'bulan-ini' => 'Bulan ini',
    ];

    /**
     * Terjemahkan pilihan periode jadi rentang UTC.
     *
     * Rentang kustom (`dari`/`sampai`) menang atas `periode`. Keduanya
     * divalidasi di sini, bukan di view: tanggal yang tidak masuk akal
     * ("sampai" lebih awal dari "dari") akan menghasilkan laporan kosong
     * yang terlihat seperti tidak ada transaksi.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable, label: string, hari: int}
     */
    public static function rentang(?string $periode, ?string $dari, ?string $sampai): array
    {
        $tz = BusinessDay::timezone();

        if ($dari && $sampai) {
            try {
                $a = CarbonImmutable::parse($dari, $tz)->startOfDay();
                $b = CarbonImmutable::parse($sampai, $tz)->startOfDay();
            } catch (\Throwable) {
                return self::rentang('hari-ini', null, null);
            }

            // Terbalik dibetulkan, bukan ditolak: niatnya jelas, dan pesan
            // error untuk dua kolom tanggal yang tertukar hanya menambah
            // langkah tanpa menambah kejelasan.
            if ($b->lessThan($a)) {
                [$a, $b] = [$b, $a];
            }

            return [
                'from' => $a->utc(),
                'to' => $b->addDay()->startOfDay()->utc(),
                'label' => $a->translatedFormat('d M Y').' – '.$b->translatedFormat('d M Y'),
                'hari' => (int) $a->diffInDays($b) + 1,
            ];
        }

        $now = BusinessDay::now();

        return match ($periode) {
            '7-hari' => self::dariHari($now->subDays(6), $now, '7 hari terakhir'),
            '30-hari' => self::dariHari($now->subDays(29), $now, '30 hari terakhir'),
            'bulan-ini' => self::dariHari($now->startOfMonth(), $now, $now->translatedFormat('F Y')),
            default => self::dariHari($now, $now, 'Hari ini'),
        };
    }

    /** @return array{from: CarbonImmutable, to: CarbonImmutable, label: string, hari: int} */
    private static function dariHari(CarbonImmutable $a, CarbonImmutable $b, string $label): array
    {
        return [
            'from' => $a->startOfDay()->utc(),
            'to' => $b->addDay()->startOfDay()->utc(),
            'label' => $label,
            'hari' => (int) $a->startOfDay()->diffInDays($b->startOfDay()) + 1,
        ];
    }

    /** @return array<string, mixed> */
    public static function all(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $pembayaran = Payment::query()
            ->where('status', 'CONFIRMED')
            ->where('confirmed_at', '>=', $from)->where('confirmed_at', '<', $to)
            ->get();

        /*
         * Sesi yang SELESAI di dalam rentang, bukan yang dimulai.
         *
         * Sesi yang mulai 23:30 dan selesai 01:00 masuk ke hari ia selesai.
         * Dasar yang sama dipakai dashboard; memakai `created_at` di sini
         * akan membuat dua halaman melaporkan jumlah sesi yang berbeda
         * untuk hari yang sama.
         */
        $sesiSelesai = DB::table('sessions')
            ->where('status', SessionStatus::COMPLETED->value)
            ->where('ended_at', '>=', $from)->where('ended_at', '<', $to);

        return [
            'rental' => self::nilaiItem($from, $to, [SessionItemType::RENTAL, SessionItemType::EXTEND]),
            'fnb' => self::nilaiItem($from, $to, [SessionItemType::FNB]),

            'tunai' => (int) $pembayaran->where('method', PaymentMethod::CASH)->sum('amount'),
            'qris' => (int) $pembayaran->where('method', PaymentMethod::QRIS_STATIC)->sum('amount'),
            'diterima' => (int) $pembayaran->sum('amount'),
            'jumlahBayar' => $pembayaran->count(),

            'pengeluaran' => self::pengeluaran($from, $to),
            'pengeluaranTotal' => (int) CashExpense::query()->where('created_at', '>=', $from)->where('created_at', '<', $to)->sum('amount'),

            'hpp' => self::hpp($from, $to),

            /*
             * Sesi yang BERAKHIR dalam rentang. Berbeda dari jumlah sesi di
             * tabel Performa Station, yang menghitung sesi yang
             * BERTRANSAKSI dalam rentang — sesi lintas tengah malam masuk
             * ke salah satunya saja.
             *
             * Keduanya ada karena menjawab pertanyaan yang berbeda, tapi
             * halaman hanya menampilkan yang kedua. Yang ini dipakai test
             * dan tersedia kalau nanti diperlukan; menampilkan keduanya
             * tanpa penjelasan hanya akan terbaca sebagai angka yang
             * bertentangan.
             */
            'sesiSelesai' => (int) $sesiSelesai->count(),
            'menitMain' => (int) ($sesiSelesai->sum('billable_duration_minutes') ?? 0),

            'perStation' => self::perStation($from, $to),

            /*
             * Satu hari digambar PER JAM, bukan satu batang.
             *
             * "Hari ini" adalah tampilan yang paling sering dibuka, dan satu
             * batang tunggal di kotak selebar layar tidak memberi tahu apa
             * pun — sementara sebaran per jam langsung menjawab pertanyaan
             * yang memang ditanyakan pemilik rental: ramainya jam berapa.
             */
            'perHari' => $from->diffInHours($to) <= 24
                ? self::perJam($from, $to)
                : self::perHari($from, $to),
            'satuanWaktu' => $from->diffInHours($to) <= 24 ? 'Jam' : 'Hari',

            'menuTeratas' => self::menuTeratas($from, $to),
            'perOperator' => self::perOperator($from, $to),
        ];
    }

    /**
     * Nilai transaksi per jenis item — BUKAN uang masuk.
     *
     * Dipakai untuk menjawab "berapa yang terjual", yang berbeda dari
     * "berapa yang diterima". Keduanya ditampilkan berdampingan di halaman,
     * dengan selisihnya disebut, supaya tidak ada yang mengira salah satunya
     * salah.
     *
     * @param  list<SessionItemType>  $jenis
     */
    private static function nilaiItem(CarbonImmutable $from, CarbonImmutable $to, array $jenis): int
    {
        return (int) DB::table('session_items')
            ->whereIn('type', array_map(fn (SessionItemType $t) => $t->value, $jenis))
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->sum('subtotal');
    }

    /** @return array<string, int> kategori => total */
    private static function pengeluaran(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return CashExpense::query()
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->groupBy('category')
            ->selectRaw('category, SUM(amount) AS total')
            ->pluck('total', 'category')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Modal F&B yang terjual — DEC-050.
     *
     * `unit_hpp` dibekukan saat order dibuat. Baris lama (sebelum DEC-050)
     * tidak punya nilainya, dan **tidak diisi mundur**: HPP saat itu memang
     * tidak pernah disimpan, dan mengisinya dengan angka hari ini akan
     * terlihat seperti data yang benar padahal tebakan.
     *
     * `tanpaHpp` adalah jumlah baris yang begitu. Halaman menyebutkannya,
     * karena laba yang dihitung dari sebagian data bukan laba.
     *
     * @return array{modal: int, tanpaHpp: int}
     */
    private static function hpp(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $baris = DB::table('fnb_order_items')
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->selectRaw('COALESCE(SUM(CASE WHEN unit_hpp IS NOT NULL THEN unit_hpp * qty END), 0) AS modal')
            ->selectRaw('COALESCE(SUM(CASE WHEN unit_hpp IS NULL THEN qty END), 0) AS tanpa')
            ->first();

        return [
            'modal' => (int) ($baris->modal ?? 0),
            'tanpaHpp' => (int) ($baris->tanpa ?? 0),
        ];
    }

    /**
     * Performa per station — PRD §27 "Session: utilization + station
     * performance".
     *
     * ## Dasarnya item, bukan sesi, dan itu penting
     *
     * Versi pertama menjumlahkan sesi yang **selesai** di dalam rentang.
     * Hasilnya: total kolom Omzet tidak sama dengan angka Rental + F&B di
     * atasnya — selisih Rp 48.000 pada data uji pertama.
     *
     * Penyebabnya dua dasar yang berbeda. Angka besar di kepala halaman
     * menghitung **item yang dibuat** dalam rentang; tabel ini menghitung
     * **sesi yang berakhir** dalam rentang. Keduanya sah sebagai definisi,
     * tapi laporan yang baris-barisnya tidak menjumlah ke totalnya sendiri
     * akan selalu dicurigai rusak — dan orang yang mengeceknya tidak punya
     * cara tahu mana yang benar.
     *
     * Sekarang keduanya memakai dasar yang sama: item yang dibuat dalam
     * rentang. Kolom Omzet dijamin menjumlah ke Rental + F&B.
     *
     * Konsekuensinya `sesi` berarti "sesi yang menghasilkan transaksi pada
     * rentang ini" — termasuk yang masih berjalan. Itu memang yang ingin
     * diketahui dari halaman laporan.
     *
     * @return list<array{code: string, name: string, sesi: int, menit: int, omzet: int}>
     */
    private static function perStation(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $uang = DB::table('session_items')
            ->join('sessions', 'sessions.id', '=', 'session_items.session_id')
            ->join('stations', 'stations.id', '=', 'sessions.station_id')
            ->where('session_items.created_at', '>=', $from)->where('session_items.created_at', '<', $to)
            ->groupBy('stations.id', 'stations.code', 'stations.name')
            ->selectRaw('stations.id AS station_id, stations.code, stations.name')
            ->selectRaw('COUNT(DISTINCT sessions.id) AS sesi')
            ->selectRaw('COALESCE(SUM(session_items.subtotal), 0) AS omzet')
            ->orderByDesc('omzet')
            ->get();

        /*
         * Durasi dihitung di query TERPISAH, bukan ikut di atas.
         *
         * Dalam query itu satu sesi muncul sekali per baris item, jadi
         * `SUM(billable_duration_minutes)` akan menghitung durasi sesi yang
         * punya tiga item sebanyak tiga kali — dan "jam main" bisa melebihi
         * jumlah jam yang ada dalam sehari tanpa ada yang terlihat salah.
         * `COUNT(DISTINCT)` menyelamatkan kolom jumlah sesi, tapi tidak
         * menyelamatkan SUM.
         */
        $menit = DB::table('sessions')
            ->whereIn('sessions.id', DB::table('session_items')
                ->where('created_at', '>=', $from)->where('created_at', '<', $to)
                ->select('session_id'))
            ->groupBy('station_id')
            ->selectRaw('station_id, COALESCE(SUM(billable_duration_minutes), 0) AS menit')
            ->pluck('menit', 'station_id');

        return $uang
            ->map(fn ($r) => [
                'code' => $r->code,
                'name' => $r->name,
                'sesi' => (int) $r->sesi,
                'menit' => (int) ($menit[$r->station_id] ?? 0),
                'omzet' => (int) $r->omzet,
            ])
            ->all();
    }

    /**
     * Uang diterima per hari — untuk grafik batang.
     *
     * Dibuat dari rentang tanggalnya DULU, baru diisi dari database. Kalau
     * dibalik, hari tanpa transaksi hilang dari grafik dan tren naik-turun
     * jadi berbohong: dua hari sepi berturut-turut akan terlihat seperti
     * satu hari.
     *
     * @return list<array{label: string, tanggal: string, total: int}>
     */
    private static function perHari(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tz = BusinessDay::timezone();
        $mulai = $from->setTimezone($tz)->startOfDay();
        $selesai = $to->setTimezone($tz)->subSecond()->startOfDay();

        // Grafik 90 batang tidak terbaca di layar mana pun; di atas itu
        // angkanya tetap benar, hanya grafiknya yang tidak ditampilkan.
        if ($mulai->diffInDays($selesai) > 90) {
            return [];
        }

        $perTanggal = Payment::query()
            ->where('status', 'CONFIRMED')
            ->where('confirmed_at', '>=', $from)->where('confirmed_at', '<', $to)
            ->get()
            ->groupBy(fn (Payment $p) => $p->confirmed_at->timezone($tz)->toDateString())
            ->map(fn ($g) => (int) $g->sum('amount'));

        $out = [];

        for ($d = $mulai; $d->lessThanOrEqualTo($selesai); $d = $d->addDay()) {
            $key = $d->toDateString();

            $out[] = [
                'label' => $d->translatedFormat('d/m'),
                'tanggal' => $key,
                'total' => $perTanggal[$key] ?? 0,
            ];
        }

        return $out;
    }

    /**
     * Uang diterima per jam, untuk rentang satu hari.
     *
     * 24 batang tetap, termasuk jam yang kosong. Jam sepi yang dihilangkan
     * akan membuat jam 02:00 berdiri tepat di sebelah jam 14:00 dan
     * grafiknya berbohong tentang kapan ramainya.
     *
     * @return list<array{label: string, tanggal: string, total: int}>
     */
    private static function perJam(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tz = BusinessDay::timezone();

        $perJam = Payment::query()
            ->where('status', 'CONFIRMED')
            ->where('confirmed_at', '>=', $from)->where('confirmed_at', '<', $to)
            ->get()
            ->groupBy(fn (Payment $p) => (int) $p->confirmed_at->timezone($tz)->format('G'))
            ->map(fn ($g) => (int) $g->sum('amount'));

        $hari = $from->setTimezone($tz)->toDateString();
        $out = [];

        for ($j = 0; $j < 24; $j++) {
            $out[] = [
                // Label tiap 3 jam saja; 24 angka berdempetan tidak terbaca
                // di lebar mana pun.
                'label' => $j % 3 === 0 ? str_pad((string) $j, 2, '0', STR_PAD_LEFT) : '',
                'tanggal' => $hari.' '.str_pad((string) $j, 2, '0', STR_PAD_LEFT).':00',
                'total' => $perJam[$j] ?? 0,
            ];
        }

        return $out;
    }

    /**
     * Menu terlaris — dari `fnb_order_items`, bukan dari produk master.
     *
     * Nama dibekukan ke baris order, jadi menu yang sudah diganti namanya
     * tetap tampil dengan nama saat ia terjual.
     *
     * @return list<array{name: string, qty: int, total: int}>
     */
    private static function menuTeratas(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('fnb_order_items')
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->groupBy('name')
            ->selectRaw('name, SUM(qty) AS qty, SUM(subtotal) AS total')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'qty' => (int) $r->qty, 'total' => (int) $r->total])
            ->all();
    }

    /**
     * Uang yang masuk lewat siapa — PRD §27 "Operator: shift + transaction
     * attribution".
     *
     * Dari `payments.actor_id`, bukan dari shift: payment yang masuk saat
     * tidak ada shift terbuka tetap punya penerima, dan membuangnya membuat
     * total per-operator tidak sama dengan total keseluruhan.
     *
     * @return list<array{name: string, jumlah: int, total: int}>
     */
    private static function perOperator(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('payments')
            ->leftJoin('users', 'users.id', '=', 'payments.actor_id')
            ->where('payments.status', 'CONFIRMED')
            ->where('payments.confirmed_at', '>=', $from)->where('payments.confirmed_at', '<', $to)
            ->groupBy('users.id', 'users.name')
            ->selectRaw('users.name')
            ->selectRaw('COUNT(*) AS jumlah')
            ->selectRaw('SUM(payments.amount) AS total')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name ?? 'Tidak tercatat',
                'jumlah' => (int) $r->jumlah,
                'total' => (int) $r->total,
            ])
            ->all();
    }
}
