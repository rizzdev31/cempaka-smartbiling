@php
    use App\Support\Web\ReportData;

    $d = $data;

    /*
     * Dua angka yang paling mudah tertukar, jadi namanya dibedakan tegas di
     * seluruh halaman:
     *
     * - "Terjual"  = nilai transaksi (item yang dibuat), termasuk yang
     *                belum dibayar.
     * - "Diterima" = uang yang benar-benar masuk (payment CONFIRMED).
     *
     * Selisihnya nyata dan sehat pada sesi Open Tab yang masih berjalan.
     */
    $terjual = $d['rental'] + $d['fnb'];
    $labaKotorFnb = $d['fnb'] - $d['hpp']['modal'];
    $bersih = $d['diterima'] - $d['pengeluaranTotal'];

    $puncak = (int) (collect($d["perHari"])->max("total") ?? 0);
    $skala  = max(1, $puncak);
    $maxStation = collect($d['perStation'])->max('omzet') ?: 1;
@endphp

<x-layouts.app title="Laporan" :poll="30">

    <x-page-header title="Laporan"
                   :subtitle="$rentang['label'].' · '.$rentang['hari'].' hari · batas hari tengah malam WIB'" />

    {{--
        ── Pemilih periode ──────────────────────────────────────────────
        GET, bukan JavaScript: rentang yang dipilih ikut di URL, jadi bisa
        disimpan sebagai bookmark dan dikirim ke orang lain apa adanya.
    --}}
    <x-card class="mb-4">
        <div class="flex flex-col xl:flex-row xl:items-end gap-4">

            <div class="min-w-0">
                <span class="block text-[11px] font-semibold text-ink-soft mb-2">Periode</span>
                <div class="flex flex-wrap gap-2">
                    @foreach (ReportData::PERIODE as $kunci => $label)
                        @php $aktif = $dari === '' && $sampai === '' && $periode === $kunci; @endphp
                        <a href="{{ route('admin.reports', ['periode' => $kunci]) }}"
                           @if ($aktif) aria-current="page" @endif
                           class="px-3.5 py-2 text-xs rounded-full transition-colors whitespace-nowrap
                                  {{ $aktif
                                      ? 'font-semibold bg-pitch text-card'
                                      : 'font-medium text-ink-soft bg-inset hover:text-ink hover:bg-line-soft' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Pemisah vertikal hanya saat benar-benar sebaris. Garis
                 horizontal di antara dua blok yang ditumpuk justru memotong
                 kartunya jadi dua. --}}
            <div class="hidden xl:block w-px self-stretch bg-line-soft shrink-0"></div>

            <form method="GET" action="{{ route('admin.reports') }}"
                  class="grid grid-cols-[repeat(auto-fit,minmax(9rem,1fr))] gap-3 items-end flex-1 min-w-0">
                <x-field name="dari" label="Dari tanggal" type="date" :value="$dari" />
                <x-field name="sampai" label="Sampai tanggal" type="date" :value="$sampai" />
                <button type="submit"
                        class="px-4 py-2 text-xs font-semibold rounded-full text-card bg-brand-600
                               hover:bg-brand-700 shadow-brand transition-colors">
                    Tampilkan
                </button>
            </form>
        </div>
    </x-card>

    {{-- ── Baris angka utama ───────────────────────────────────────────
         Empat angka yang dibaca lebih dulu daripada apa pun di halaman
         ini. Grid 1/2/4 kolom: tidak pernah ada kartu menggantung sendiri
         di baris terakhir. --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">

        <x-card>
            <span class="text-[11px] font-semibold text-ink-soft block">Uang diterima</span>
            <span class="text-2xl font-extrabold text-ink tabular block mt-1">
                Rp {{ number_format($d['diterima'], 0, ',', '.') }}
            </span>
            <p class="text-[11px] text-ink-soft mt-2 leading-relaxed">
                {{ $d['jumlahBayar'] }} pembayaran. Hanya yang sudah dikonfirmasi —
                tagihan Open Tab yang masih jalan belum ikut.
            </p>
        </x-card>

        <x-card>
            <span class="text-[11px] font-semibold text-ink-soft block">Rental</span>
            <span class="text-2xl font-extrabold text-ink tabular block mt-1">
                Rp {{ number_format($d['rental'], 0, ',', '.') }}
            </span>
            <p class="text-[11px] text-ink-soft mt-2 leading-relaxed">
                {{--
                    Jumlah sesi dan jam main diambil dari tabel Performa
                    Station di bawah, bukan dari query tersendiri.

                    Dua query untuk satu angka berarti dua definisi yang bisa
                    melenceng, dan pembaca yang menjumlahkan kolom di bawah
                    lalu menemukan hasilnya berbeda dengan baris ini tidak
                    punya cara tahu mana yang benar.
                --}}
                Nilai terjual, termasuk extend.
                {{ collect($d['perStation'])->sum('sesi') }} sesi ·
                {{ number_format(collect($d['perStation'])->sum('menit') / 60, 1, ',', '.') }} jam main.
            </p>
        </x-card>

        <x-card>
            <span class="text-[11px] font-semibold text-ink-soft block">F&amp;B</span>
            <span class="text-2xl font-extrabold text-ink tabular block mt-1">
                Rp {{ number_format($d['fnb'], 0, ',', '.') }}
            </span>
            <p class="text-[11px] text-ink-soft mt-2 leading-relaxed">
                Modal Rp {{ number_format($d['hpp']['modal'], 0, ',', '.') }} ·
                laba kotor <span class="font-semibold text-ink">Rp {{ number_format($labaKotorFnb, 0, ',', '.') }}</span>
            </p>
        </x-card>

        {{-- Satu-satunya kartu yang bisa bernilai negatif, dan warnanya
             ikut nilainya — bukan selalu hijau. --}}
        <x-card class="{{ $bersih < 0 ? 'border-danger/40' : '' }}">
            <span class="text-[11px] font-semibold text-ink-soft block">Kas bersih</span>
            <span class="text-2xl font-extrabold tabular block mt-1 {{ $bersih < 0 ? 'text-danger' : 'text-brand-600' }}">
                Rp {{ number_format($bersih, 0, ',', '.') }}
            </span>
            <p class="text-[11px] text-ink-soft mt-2 leading-relaxed">
                Diterima − pengeluaran kasir Rp {{ number_format($d['pengeluaranTotal'], 0, ',', '.') }}.
                Belum termasuk biaya bulanan.
            </p>
        </x-card>
    </div>

    {{-- ── Peringatan HPP ──────────────────────────────────────────────
         Hanya muncul kalau memang ada barisnya. Peringatan permanen
         berhenti dibaca setelah hari kedua. --}}
    @if ($d['hpp']['tanpaHpp'] > 0)
        <div class="flex items-start gap-3 p-4 mb-4 rounded-block bg-warning-surface border border-warning/20">
            <span class="text-warning shrink-0 mt-0.5"><x-icon name="warning" class="w-4 h-4" /></span>
            <p class="text-xs text-warning leading-relaxed">
                <span class="font-bold">{{ $d['hpp']['tanpaHpp'] }} porsi terjual tanpa HPP tercatat.</span>
                Laba kotor F&amp;B di atas dihitung tanpa modalnya, jadi angkanya
                <span class="font-semibold">lebih besar dari yang sebenarnya</span>.
                Isi HPP-nya di <a href="{{ route('admin.pricing') }}" class="underline font-semibold">Harga &amp; Paket</a> —
                berlaku untuk penjualan berikutnya, bukan yang sudah lewat.
            </p>
        </div>
    @endif

    {{-- ── Grafik harian + metode bayar ────────────────────────────── --}}
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 mb-4">

        <x-card class="xl:col-span-8">
            <div class="flex items-baseline justify-between gap-3 flex-wrap mb-5">
                <h2 class="text-base font-bold text-ink">Uang Diterima per {{ $d["satuanWaktu"] }}</h2>
                <span class="text-[11px] text-ink-soft tabular">
                    Tertinggi Rp {{ number_format($puncak, 0, ',', '.') }}
                </span>
            </div>

            @if ($d['perHari'] === [])
                <x-empty title="Rentang terlalu panjang untuk grafik" icon="chart">
                    Lebih dari 90 hari. Angkanya tetap benar di kartu-kartu di atas —
                    hanya batangnya yang tidak akan terbaca.
                </x-empty>
            @else
                {{--
                    Batang CSS, bukan pustaka grafik.

                    Satu halaman tidak sebanding dengan 70 KB JavaScript, dan
                    batang yang digambar browser tetap tampil saat skrip gagal
                    dimuat. Tingginya persentase, jadi ikut melar di layar
                    mana pun.
                --}}
                {{--
                    `items-stretch`, BUKAN `items-end`.

                    Dengan `items-end` tiap kolom hanya setinggi isinya, jadi
                    `flex-1` pada jalur batang tidak punya apa-apa untuk
                    diisi — dan `height: 60%` dihitung terhadap nol. Hasilnya
                    kotak kosong dengan label di bawahnya: grafik yang
                    terlihat "tidak ada datanya" padahal datanya ada.
                --}}
                <div class="flex items-stretch gap-1 sm:gap-1.5 h-44 overflow-x-auto pb-1">
                    @foreach ($d['perHari'] as $hari)
                        @php $tinggi = max(2, (int) round($hari['total'] / $skala * 100)); @endphp
                        <div class="flex-1 min-w-[0.75rem] flex flex-col items-center gap-1.5 group">
                            <div class="w-full flex-1 flex items-end">
                                <div class="w-full rounded-t-chip transition-colors
                                            {{ $hari['total'] > 0 ? 'bg-brand-600 group-hover:bg-brand-700' : 'bg-inset' }}"
                                     style="height: {{ $tinggi }}%"
                                     title="{{ $hari['tanggal'] }} — Rp {{ number_format($hari['total'], 0, ',', '.') }}"></div>
                            </div>
                            <span class="text-[9px] text-ink-faint tabular whitespace-nowrap">{{ $hari['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        <x-card class="xl:col-span-4">
            <h2 class="text-base font-bold text-ink mb-4">Metode Bayar</h2>

            @php
                $totalBayar = max(1, $d['diterima']);
                $metode = [
                    ['Tunai', $d['tunai'], 'bg-brand-600'],
                    ['QRIS', $d['qris'], 'bg-awaiting'],
                ];
            @endphp

            <div class="space-y-4">
                @foreach ($metode as [$label, $nilai, $warna])
                    <div>
                        <div class="flex items-baseline justify-between gap-3 mb-1.5">
                            <span class="text-xs font-semibold text-ink">{{ $label }}</span>
                            <x-rupiah :value="$nilai" class="text-xs font-bold text-ink" />
                        </div>
                        <div class="h-2 rounded-full bg-inset overflow-hidden">
                            <div class="h-full {{ $warna }} rounded-full"
                                 style="width: {{ round($nilai / $totalBayar * 100) }}%"></div>
                        </div>
                        <span class="text-[11px] text-ink-soft tabular mt-1 block">
                            {{ $d['diterima'] > 0 ? round($nilai / $totalBayar * 100) : 0 }}%
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 pt-4 border-t border-line-soft">
                <div class="flex items-baseline justify-between gap-3">
                    <span class="text-xs font-semibold text-ink-soft">Terjual (nilai transaksi)</span>
                    <x-rupiah :value="$terjual" class="text-xs font-bold text-ink" />
                </div>
                <p class="text-[11px] text-ink-soft mt-2 leading-relaxed">
                    @if ($terjual > $d['diterima'])
                        Lebih besar Rp {{ number_format($terjual - $d['diterima'], 0, ',', '.') }}
                        dari uang diterima — selisihnya tagihan yang belum dibayar,
                        biasanya Open Tab yang masih berjalan. Bukan kesalahan.
                    @else
                        Sama dengan uang diterima: semua yang terjual sudah dibayar.
                    @endif
                </p>
            </div>
        </x-card>
    </div>

    {{-- ── Station & pengeluaran ───────────────────────────────────── --}}
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 mb-4">

        <x-card class="xl:col-span-7">
            <h2 class="text-base font-bold text-ink mb-4">Performa Station</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs table-fixed table-stacked">
                    <colgroup>
                        <col class="w-[34%]"><col class="w-[16%]"><col class="w-[20%]"><col class="w-[30%]">
                    </colgroup>
                    <thead>
                        <tr class="text-ink-soft border-b border-line-soft">
                            <th scope="col" class="py-3 px-2 font-medium">Station</th>
                            <th scope="col" class="py-3 px-2 font-medium md:text-right">Sesi</th>
                            <th scope="col" class="py-3 px-2 font-medium md:text-right">Jam main</th>
                            <th scope="col" class="py-3 px-2 font-medium md:text-right">Omzet</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        @forelse ($d['perStation'] as $s)
                            <tr class="hover:bg-inset/60 transition-colors">
                                <td data-label="Station" class="py-3 px-2">
                                    <span class="font-bold text-ink">{{ $s['code'] }}</span>
                                    <span class="text-ink-soft ml-1 truncate">{{ $s['name'] }}</span>
                                </td>
                                <td data-label="Sesi" class="py-3 px-2 md:text-right text-ink tabular">{{ $s['sesi'] }}</td>
                                <td data-label="Jam main" class="py-3 px-2 md:text-right text-ink-soft tabular">
                                    {{ number_format($s['menit'] / 60, 1, ',', '.') }} jam
                                </td>
                                <td data-label="Omzet" class="py-3 px-2 md:text-right">
                                    {{-- Batang di belakang angka: perbandingan
                                         antar station terbaca tanpa membandingkan
                                         digit satu per satu. --}}
                                    <div class="flex items-center gap-2 md:justify-end">
                                        <span class="hidden md:block h-1.5 rounded-full bg-brand-600/70"
                                              style="width: {{ max(4, round($s['omzet'] / $maxStation * 56)) }}px"></span>
                                        <x-rupiah :value="$s['omzet']" class="font-bold text-ink" />
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <x-empty title="Belum ada sesi selesai" icon="tv" colspan="4">
                                Sesi yang masih berjalan belum masuk laporan — nilainya belum final.
                            </x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card class="xl:col-span-5">
            <div class="flex items-baseline justify-between gap-3 mb-4">
                <h2 class="text-base font-bold text-ink">Pengeluaran Kasir</h2>
                <span class="text-xs font-bold text-danger tabular">
                    − <x-rupiah :value="$d['pengeluaranTotal']" />
                </span>
            </div>

            @forelse ($d['pengeluaran'] as $kategori => $total)
                <div class="flex items-baseline justify-between gap-3 py-2
                            {{ ! $loop->last ? 'border-b border-line-soft' : '' }}">
                    <span class="text-xs text-ink-soft truncate">{{ $kategori }}</span>
                    <x-rupiah :value="$total" class="text-xs font-semibold text-ink shrink-0" />
                </div>
            @empty
                <p class="text-[11px] text-ink-soft leading-relaxed">
                    Tidak ada uang yang keluar dari laci pada rentang ini.
                </p>
            @endforelse

            <p class="text-[11px] text-ink-soft mt-4 leading-relaxed">
                Hanya uang yang diambil dari laci (DEC-046). Biaya bulanan —
                listrik, sewa, gaji — belum ada tempatnya, jadi
                <span class="font-semibold text-ink">ini bukan laba bersih</span>.
            </p>
        </x-card>
    </div>

    {{-- ── Menu terlaris & per operator ────────────────────────────── --}}
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">

        <x-card>
            <h2 class="text-base font-bold text-ink mb-4">Menu Terlaris</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs table-fixed table-stacked">
                    <colgroup><col class="w-[52%]"><col class="w-[18%]"><col class="w-[30%]"></colgroup>
                    <thead>
                        <tr class="text-ink-soft border-b border-line-soft">
                            <th scope="col" class="py-3 px-2 font-medium">Menu</th>
                            <th scope="col" class="py-3 px-2 font-medium md:text-right">Porsi</th>
                            <th scope="col" class="py-3 px-2 font-medium md:text-right">Omzet</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        @forelse ($d['menuTeratas'] as $m)
                            <tr class="hover:bg-inset/60 transition-colors">
                                <td data-label="Menu" class="py-3 px-2 font-semibold text-ink truncate">{{ $m['name'] }}</td>
                                <td data-label="Porsi" class="py-3 px-2 md:text-right text-ink tabular">{{ $m['qty'] }}</td>
                                <td data-label="Omzet" class="py-3 px-2 md:text-right font-bold text-ink">
                                    <x-rupiah :value="$m['total']" />
                                </td>
                            </tr>
                        @empty
                            <x-empty title="Belum ada F&B terjual" icon="cup" colspan="3">
                                Pesanan yang masuk pada rentang ini akan muncul di sini.
                            </x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card>
            <h2 class="text-base font-bold text-ink mb-4">Uang Masuk per Operator</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs table-fixed table-stacked">
                    <colgroup><col class="w-[52%]"><col class="w-[18%]"><col class="w-[30%]"></colgroup>
                    <thead>
                        <tr class="text-ink-soft border-b border-line-soft">
                            <th scope="col" class="py-3 px-2 font-medium">Operator</th>
                            <th scope="col" class="py-3 px-2 font-medium md:text-right">Transaksi</th>
                            <th scope="col" class="py-3 px-2 font-medium md:text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        @forelse ($d['perOperator'] as $o)
                            <tr class="hover:bg-inset/60 transition-colors">
                                <td data-label="Operator" class="py-3 px-2 font-semibold text-ink truncate">{{ $o['name'] }}</td>
                                <td data-label="Transaksi" class="py-3 px-2 md:text-right text-ink tabular">{{ $o['jumlah'] }}</td>
                                <td data-label="Total" class="py-3 px-2 md:text-right font-bold text-ink">
                                    <x-rupiah :value="$o['total']" />
                                </td>
                            </tr>
                        @empty
                            <x-empty title="Belum ada pembayaran" icon="money" colspan="3">
                                Belum ada uang masuk pada rentang ini.
                            </x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <p class="text-[11px] text-ink-soft mt-4 leading-relaxed">
                Siapa yang mengonfirmasi pembayarannya, bukan siapa yang membuka shift.
                Rekonsiliasi laci per shift ada di
                <a href="{{ route('admin.shifts') }}" class="font-semibold text-brand-600 hover:underline">Shift</a>.
            </p>
        </x-card>
    </div>

</x-layouts.app>
