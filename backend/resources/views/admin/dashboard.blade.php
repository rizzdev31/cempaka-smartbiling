@php
    $maxDay = max(array_column($revenue_by_day, 'value')) ?: 1;
    $adaData = $maxDay > 1 || array_sum(array_column($revenue_by_day, 'value')) > 0;
@endphp

<x-layouts.app title="Dashboard">

    {{-- ── Judul + kontrol ─────────────────────────────────────────── --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl font-extrabold text-ink tracking-tight">Ringkasan Hari Ini</h1>
            <p class="text-xs text-ink-soft mt-1">
                Pendapatan, sesi berjalan, dan aktivitas station ·
                {{ $generated_at->timezone(\App\Support\Time\BusinessDay::timezone())->format('d M Y, H:i') }} WIB
            </p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            <x-pill variant="secondary">
                Bulan ini
                <x-icon name="chevron-down" class="w-3.5 h-3.5 text-ink-faint" />
            </x-pill>
            <x-pill variant="secondary">
                <x-icon name="download" class="w-3.5 h-3.5 text-ink-soft" />
                Ekspor
            </x-pill>
            <x-pill variant="primary">
                <x-icon name="filter" class="w-3.5 h-3.5" />
                Filter
            </x-pill>
        </div>
    </div>


    {{-- ── Kartu metrik ────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

        {{-- Satu kartu disorot, pola dari referensi. Yang disorot adalah
             angka yang paling sering dicari owner saat membuka halaman. --}}
        <x-metric
            highlight
            label="Pendapatan hari ini"
            icon="money"
            :value="'Rp '.number_format($revenue_today, 0, ',', '.')"
            :footnote="'Kemarin: Rp '.number_format($revenue_yesterday, 0, ',', '.')"
        >
            <x-slot:badge>
                <x-delta :now="$revenue_today" :before="$revenue_yesterday" tone="onBrand" />
            </x-slot:badge>
        </x-metric>

        <x-metric
            label="Sesi berjalan"
            icon="play"
            :value="$sessions_active"
            :footnote="$sessions_done_today.' sesi selesai hari ini'"
        >
            <x-slot:badge>
                <x-delta :now="$sessions_done_today" :before="$sessions_done_yesterday" unit=" sesi" />
            </x-slot:badge>
        </x-metric>

        <x-metric
            label="Station terpakai"
            icon="tv"
            chip="soft"
            :value="$sessions_active.' / '.$stations_total"
            :footnote="$avg_minutes > 0 ? 'Rata-rata sesi '.$avg_minutes.' menit' : 'Belum ada sesi selesai hari ini'"
        >
            {{-- Okupansi BUKAN perbandingan terhadap kemarin, jadi ia badge
                 biasa, bukan `x-delta`. --}}
            <x-slot:badge>
                <x-badge tone="brand">{{ number_format($occupancy_percent, 1, ',', '.') }}%</x-badge>
            </x-slot:badge>
        </x-metric>

        <x-metric
            label="Pesanan F&B terbuka"
            icon="cup"
            :value="$fnb_open"
            :footnote="$members_new > 0
                ? $members_new.' member baru hari ini'
                : 'Belum diantar ke station'"
        />
    </div>

    {{-- ── Grafik ──────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-8">

        {{-- Bar chart pendapatan 7 hari --}}
        <x-card class="lg:col-span-8 flex flex-col justify-between">
            <div class="flex items-center justify-between gap-3 mb-6">
                <h2 class="text-base font-bold text-ink">Pendapatan 7 Hari</h2>
                <x-pill variant="tertiary" class="px-3 py-1.5">
                    Minggu ini
                    <x-icon name="chevron-down" class="w-3 h-3 text-ink-faint" />
                </x-pill>
            </div>

            <div class="relative h-64 w-full flex flex-col justify-between pt-2">

                {{--
                    Gridline. Angkanya dihitung dari nilai tertinggi, bukan
                    "40k/30k/20k" yang di-hardcode seperti referensi — sumbu
                    yang tidak mengikuti data akan berbohong begitu angkanya
                    berubah.
                --}}
                <div class="absolute inset-0 flex flex-col justify-between pointer-events-none pr-2">
                    @foreach ([1, 0.75, 0.5, 0.25, 0] as $frac)
                        <div class="flex items-center w-full">
                            {{-- Tanpa "Rp" dan tanpa spasi: judul kartunya sudah
                                 menyebut pendapatan, dan label yang membungkus
                                 dua baris merusak jarak gridline. --}}
                            <span class="text-[11px] font-medium text-ink-faint w-12 shrink-0 tabular whitespace-nowrap">
                                @php
                                    $t = (int) round($maxDay * $frac);
                                    /*
                                     * Saat belum ada transaksi, seluruh tick
                                     * membulat ke "0" dan sumbunya tampil
                                     * sebagai lima angka nol bertumpuk —
                                     * terbaca seperti rusak. Yang ditampilkan
                                     * cukup garis dasarnya saja.
                                     */
                                    $kosong = $adaData === false && $frac > 0;
                                @endphp
                                {{ $kosong ? '' : ($t >= 1_000_000
                                    ? rtrim(rtrim(number_format($t / 1_000_000, 1, ',', '.'), '0'), ',').'jt'
                                    : ($t >= 1_000 ? round($t / 1_000).'rb' : '0')) }}
                            </span>
                            <div class="flex-1 ml-2 border-b {{ $frac == 0 ? 'border-line' : 'border-dashed border-line-soft' }}"></div>
                        </div>
                    @endforeach
                </div>

                <div class="relative z-10 h-[calc(100%-20px)] ml-16 flex items-end justify-between gap-2 px-2">
                    @foreach ($revenue_by_day as $day)
                        @php
                            $h = max(4, round($day['value'] / $maxDay * 100));
                            $active = $day['active'] ?? false;
                        @endphp
                        <div class="relative flex-1 flex flex-col items-center h-full justify-end group">
                            @if ($active)
                                {{-- Tooltip melayang, pola dari referensi. --}}
                                <div class="absolute -top-4 right-full mr-2 w-40 bg-card rounded-block p-3
                                            shadow-tooltip border border-line z-20 pointer-events-none">
                                    <div class="text-[11px] font-bold text-ink mb-1.5">Hari ini</div>
                                    <div class="flex items-center justify-between text-[10px] text-ink-soft mb-1">
                                        <span class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-brand-600"></span>Pendapatan
                                        </span>
                                        <span class="font-bold text-ink"><x-rupiah :value="$day['value']" short /></span>
                                    </div>
                                    <div class="flex items-center justify-between text-[10px] text-ink-soft">
                                        <span class="flex items-center gap-1.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-track"></span>Sesi
                                        </span>
                                        <span class="font-bold text-ink tabular">{{ $sessions_done_today }}</span>
                                    </div>
                                </div>
                                <div class="w-2.5 h-2.5 rounded-full bg-card border-2 border-brand-600 absolute z-20"
                                     style="bottom: calc({{ $h }}% - 5px)"></div>
                            @endif

                            <div class="w-full rounded-block transition-colors
                                        {{ $active ? 'bar-active shadow-brand' : 'bg-inset hover:bg-track' }}"
                                 style="height: {{ $h }}%"></div>
                        </div>
                    @endforeach
                </div>

                <div class="ml-16 flex justify-between gap-2 px-2 pt-2 text-[11px] font-medium">
                    @foreach ($revenue_by_day as $day)
                        <span class="flex-1 text-center {{ ($day['active'] ?? false) ? 'font-bold text-ink' : 'text-ink-soft' }}">
                            {{ $day['label'] }}
                        </span>
                    @endforeach
                </div>
            </div>
        </x-card>

        {{-- Gauge pemakaian station --}}
        <x-card class="lg:col-span-4 flex flex-col justify-between">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h2 class="text-base font-bold text-ink">Pemakaian Station</h2>
                <button type="button" aria-label="Opsi lain"
                        class="w-8 h-8 rounded-full flex items-center justify-center text-ink-faint hover:bg-inset transition-colors">
                    <x-icon name="dots" class="w-5 h-5" />
                </button>
            </div>

            <x-gauge :percent="$occupancy_percent" label="Rata-rata terpakai" />

            <div class="grid grid-cols-2 gap-3 mt-4 pt-3 border-t border-line-soft">
                <div class="bg-inset rounded-block p-3">
                    <span class="text-[11px] font-medium text-ink-soft block">Sesi selesai</span>
                    <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                        <span class="text-lg font-bold text-ink tabular">{{ $sessions_done_today }}</span>
                        <x-delta :now="$sessions_done_today" :before="$sessions_done_yesterday" />
                    </div>
                </div>
                <div class="bg-inset rounded-block p-3">
                    <span class="text-[11px] font-medium text-ink-soft block">Rata-rata durasi</span>
                    <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                        <span class="text-lg font-bold text-ink tabular">{{ $avg_minutes }}m</span>
                        <x-delta :now="$avg_minutes" :before="$avg_minutes_yesterday" unit="m" />
                    </div>
                </div>
            </div>
        </x-card>
    </div>

    {{-- ── Sesi terakhir ───────────────────────────────────────────── --}}
    <x-card>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h2 class="text-base font-bold text-ink">Sesi Terakhir</h2>

            <div class="flex items-center gap-3 flex-wrap">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-ink-faint">
                        <x-icon name="search" class="w-3.5 h-3.5" />
                    </span>
                    <input type="text" placeholder="Cari customer atau station..."
                           class="w-60 pl-9 pr-4 py-1.5 bg-inset border border-transparent rounded-full
                                  text-xs text-ink placeholder-ink-soft
                                  focus:outline-none focus:bg-card focus:border-brand-600 transition-all">
                </div>
                <x-pill variant="tertiary" class="px-3.5 py-1.5 font-medium">
                    <x-icon name="sort" class="w-3.5 h-3.5 text-ink-soft" />
                    Urutkan
                    <x-icon name="chevron-down" class="w-3 h-3 text-ink-faint" />
                </x-pill>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-ink-soft border-b border-line-soft">
                        <th scope="col" class="py-3 px-3 font-medium">Station</th>
                        <th scope="col" class="py-3 px-3 font-medium">Customer</th>
                        <th scope="col" class="py-3 px-3 font-medium">Paket</th>
                        <th scope="col" class="py-3 px-3 font-medium">Mulai</th>
                        <th scope="col" class="py-3 px-3 font-medium">Status</th>
                        <th scope="col" class="py-3 px-3 font-medium text-right">Durasi</th>
                        <th scope="col" class="py-3 px-3 font-medium text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    {{--
                        Keadaan kosong harus terlihat DISENGAJA.

                        Tabel yang kosong tanpa penjelasan terbaca sebagai
                        rusak, dan orang akan mencari bug yang tidak ada.
                        Di instalasi baru ini justru keadaan yang benar.
                    --}}
                    @forelse ($recent_sessions as $s)
                        <tr class="hover:bg-inset/60 transition-colors">
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-chip bg-brand-50 text-brand-600 flex items-center justify-center font-bold text-[10px] shrink-0">
                                        {{ $s['console'] === 'PS3' ? '3' : '4' }}
                                    </div>
                                    <span class="font-semibold text-ink">{{ $s['station'] }}</span>
                                </div>
                            </td>
                            <td class="py-3 px-3 text-ink font-medium">{{ $s['customer'] }}</td>
                            <td class="py-3 px-3 text-ink-soft">{{ $s['package'] }}</td>
                            <td class="py-3 px-3 text-ink-soft tabular">{{ $s['started'] }}</td>
                            <td class="py-3 px-3"><x-status-pill :status="$s['status']" /></td>
                            <td class="py-3 px-3 text-right text-ink-soft tabular">
                                {{-- Open Tab belum punya durasi: `end_at` null sampai
                                     checkout (DEC-034). Ditulis "—", bukan "0 menit",
                                     karena nol akan terbaca sebagai fakta. --}}
                                {{ $s['minutes'] > 0 ? $s['minutes'].'m' : '—' }}
                            </td>
                            <td class="py-3 px-3 text-right font-bold text-ink">
                                <x-rupiah :value="$s['total']" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <span class="text-ink-faint"><x-icon name="clock" class="w-6 h-6" /></span>
                                    <p class="text-xs font-semibold text-ink">Belum ada sesi</p>
                                    <p class="text-[11px] text-ink-soft max-w-xs">
                                        Sesi yang dibuka dari tablet operator akan muncul di sini.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

</x-layouts.app>
