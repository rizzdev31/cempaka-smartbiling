@php
    use App\Support\Time\BusinessDay;
    $tz = BusinessDay::timezone();
@endphp

<x-layouts.app title="Shift" :poll="15">

    <x-page-header title="Shift Kasir" subtitle="Serah terima laci kas. 30 shift terakhir yang sudah ditutup." />

    {{-- ── Shift yang sedang berjalan ──────────────────────────────── --}}
    @if ($current)
        @php $summary = app(\App\Services\ShiftService::class)->summary($current); @endphp

        <x-card class="mb-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-base font-bold text-ink">Sedang berjalan</h2>
                        <x-badge tone="up">{{ $current->operator?->name ?? '—' }}</x-badge>
                    </div>
                    <p class="text-xs text-ink-soft mt-1 tabular">
                        Dibuka {{ $current->opened_at->timezone($tz)->format('d M Y, H:i') }} WIB
                    </p>
                </div>
            </div>

            {{--
                Enam angka, dan jumlahnya memang enam di setiap lebar: 2
                kolom × 3 baris di ponsel, 3 × 2 di tablet, 6 × 1 di laptop.
                Grid yang membagi enam jadi lima lalu satu akan meninggalkan
                satu blok menggantung sendiri di baris kedua.
            --}}
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <x-stat label="Modal awal" :value="'Rp '.number_format($current->opening_cash, 0, ',', '.')" />
                <x-stat label="Rental" :value="'Rp '.number_format($summary['rental'], 0, ',', '.')" />
                <x-stat label="F&B" :value="'Rp '.number_format($summary['fnb'], 0, ',', '.')" />
                <x-stat label="Tunai masuk" :value="'Rp '.number_format($summary['cash'], 0, ',', '.')" />
                <x-stat label="QRIS masuk" :value="'Rp '.number_format($summary['qris'], 0, ',', '.')" />

                {{-- Satu-satunya angka yang MENGURANGI laci. Diberi tanda
                     minus, bukan hanya warna: warna hilang saat dicetak dan
                     tidak terbaca oleh sebagian orang. --}}
                <x-stat label="Pengeluaran kasir"
                        :tone="$summary['expense'] > 0 ? 'danger' : 'ink'"
                        :value="($summary['expense'] > 0 ? '− Rp ' : 'Rp ').number_format($summary['expense'], 0, ',', '.')" />
            </div>

            {{--
                Angka yang paling penting di halaman ini, dan satu-satunya
                yang dibandingkan dengan uang fisik nanti.
            --}}
            <div class="mt-4 p-4 rounded-block bg-brand-50 border border-brand-100">
                <div class="flex items-baseline justify-between gap-3 flex-wrap">
                    <span class="text-xs font-semibold text-brand-600">Kas seharusnya di laci sekarang</span>
                    <span class="text-xl font-extrabold text-brand-600 tabular">
                        Rp {{ number_format(\App\Services\ShiftService::expectedCash($current, $summary), 0, ',', '.') }}
                    </span>
                </div>

                {{--
                    Rumusnya ditulis lengkap dengan angkanya, bukan hanya
                    namanya.

                    Angka ini yang dibandingkan dengan uang fisik, dan saat
                    selisihnya muncul pertanyaan pertamanya selalu "dari mana
                    angka ini". Kasir yang bisa mengulang hitungannya di
                    kertas tidak perlu memanggil siapa pun.

                    Sukunya DISALIN dari `ShiftService::expectedCash()`, dan
                    totalnya diambil dari fungsi itu — bukan dijumlahkan
                    ulang di sini. Dua penjumlahan untuk satu angka berarti
                    layar bisa menampilkan rumus yang tidak menghasilkan
                    totalnya sendiri.
                --}}
                <p class="text-[11px] text-brand-600/90 mt-2 leading-relaxed tabular">
                    Rp {{ number_format($current->opening_cash, 0, ',', '.') }} modal awal
                    + Rp {{ number_format($summary['cash'], 0, ',', '.') }} tunai masuk
                    − Rp {{ number_format($summary['expense'], 0, ',', '.') }} pengeluaran.
                </p>
                <p class="text-[11px] text-brand-600 mt-1 leading-relaxed">
                    QRIS tidak ikut — uangnya tidak pernah masuk laci.
                </p>
            </div>

            {{-- ── Pengeluaran kasir ───────────────────────────── DEC-046 ──
                 Uang yang keluar dari laci saat shift berjalan: es batu,
                 galon, iuran sampah. Sebelum ini tidak ada tempatnya, dan
                 kasir yang mengambil Rp 20.000 untuk belanja terlihat
                 kekurangan Rp 20.000 saat tutup shift. --}}
            <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">

                <x-action-panel title="Catat pengeluaran dari laci" tone="warning"
                                :open="$errors->hasAny(['amount', 'category', 'note'])">
                    <p class="text-[11px] text-ink-soft mb-3 leading-relaxed">
                        Catat <span class="font-semibold text-ink">saat uangnya diambil</span>,
                        bukan saat tutup shift. Pengeluaran yang diingat belakangan
                        selalu lebih kecil dari yang sebenarnya.
                    </p>

                    <form method="POST" action="{{ route('admin.shifts.expense', $current) }}" class="space-y-3">
                        @csrf

                        {{-- Nominal dan kategori sejajar di satu baris: keduanya
                             pendek, dan menumpuknya membuat form terasa panjang
                             tanpa alasan. Turun sendiri di bawah 22rem. --}}
                        <div class="grid grid-cols-[repeat(auto-fit,minmax(9rem,1fr))] gap-3">
                            <x-money-input name="amount" label="Nominal" required />
                            <x-select-field name="category" label="Kategori" required
                                            :options="array_combine(\App\Models\CashExpense::kategori(), \App\Models\CashExpense::kategori())" />
                        </div>

                        <x-field name="note" label="Keterangan" required
                                 placeholder="beli es batu 2 balok" />

                        {{-- Warna tombolnya mengikuti kepala panelnya. Tombol
                             teal di bawah judul kuning membuat panelnya terbaca
                             sebagai dua potong yang kebetulan bertumpuk. --}}
                        <x-action-submit tone="warning">Catat pengeluaran</x-action-submit>
                    </form>
                </x-action-panel>

                {{-- Daftarnya di sebelah formnya, bukan tersembunyi di balik
                     panel lain: yang paling sering dicari adalah "sudah
                     tercatat belum", dan jawabannya harus terlihat tanpa
                     mengklik apa pun. --}}
                <div class="rounded-block border border-line p-4">
                    <div class="flex items-baseline justify-between gap-3 mb-3">
                        <h3 class="text-xs font-bold text-ink">Pengeluaran shift ini</h3>
                        <span class="text-[11px] font-semibold text-ink-soft tabular">
                            {{ $current->expenses->count() }} catatan
                        </span>
                    </div>

                    @forelse ($current->expenses as $e)
                        <div class="flex items-start justify-between gap-3 py-2
                                    {{ ! $loop->last ? 'border-b border-line-soft' : '' }}">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-ink truncate">{{ $e->note }}</p>
                                <p class="text-[11px] text-ink-soft mt-0.5">
                                    {{ $e->category }}
                                    · {{ $e->created_at->timezone($tz)->format('H:i') }}
                                    · {{ $e->actor?->name ?? '—' }}
                                </p>
                            </div>
                            <span class="text-xs font-bold text-danger tabular shrink-0">
                                − <x-rupiah :value="$e->amount" />
                            </span>
                        </div>
                    @empty
                        <p class="text-[11px] text-ink-soft leading-relaxed">
                            Belum ada. Kalau ada uang yang diambil dari laci untuk belanja,
                            catat di sini supaya tidak terhitung sebagai kas yang hilang.
                        </p>
                    @endforelse

                    {{-- Tidak ada tombol hapus, dan itu disengaja. Pengeluaran
                         yang bisa dihapus adalah jalan keluar paling mudah dari
                         selisih kas — persis yang ingin dicegah. Salah catat
                         diselesaikan di catatan tutup shift. --}}
                    @if ($current->expenses->isNotEmpty())
                        <p class="text-[11px] text-ink-faint mt-3 leading-relaxed">
                            Catatan yang sudah masuk tidak bisa dihapus. Kalau ada yang salah,
                            tulis koreksinya di catatan saat tutup shift.
                        </p>
                    @endif
                </div>
            </div>

            {{-- ── Tutup shift ────────────────────────────────────────
                 Jalur normalnya tetap dari tablet: orang yang memegang
                 uangnya yang harus menghitungnya. Form ini untuk operator
                 yang terlanjur pulang tanpa menutup. --}}
            <details class="mt-4 group">
                <summary class="text-xs font-semibold text-ink-soft cursor-pointer select-none
                                hover:text-ink transition-colors list-none flex items-center gap-2">
                    <x-icon name="chevron-down" class="w-3 h-3 transition-transform group-open:rotate-180" />
                    Tutup shift ini dari web
                </summary>

                <div class="mt-3 p-4 rounded-block bg-inset">
                    <p class="text-[11px] text-ink-soft mb-3 leading-relaxed">
                        Hitung uang fisik di laci <span class="font-semibold text-ink">lebih dulu</span>,
                        baru isi angkanya. Melihat tebakan sistem sebelum menghitung membuat angka
                        yang ditulis mengikuti tebakan itu — dan selisih yang nyata jadi tidak pernah terlihat.
                    </p>

                    <form method="POST" action="{{ route('admin.shifts.close', $current) }}"
                          class="grid grid-cols-[repeat(auto-fit,minmax(11rem,1fr))] gap-3 items-end">
                        @csrf
                        <x-money-input name="closing_cash" label="Uang fisik di laci" required />
                        <x-field name="note" label="Catatan (opsional)" placeholder="kurang 5rb, kembalian" />

                        <button type="submit"
                                class="px-4 py-2 text-xs font-semibold rounded-full
                                       text-card bg-brand-600 hover:bg-brand-700 shadow-brand transition-colors">
                            Tutup shift
                        </button>
                    </form>
                </div>
            </details>
        </x-card>
    @else
        <x-card class="mb-4">
            <x-empty title="Tidak ada shift yang terbuka" icon="clock">
                Operator biasanya membukanya dari tablet. Bisa juga dari sini.
            </x-empty>

            {{-- DEC-045: Laravel punya semua akses. Jalur normalnya tetap
                 tablet — orang yang memegang uangnya yang menghitung modal
                 awal. --}}
            <div class="max-w-md mx-auto mt-2">
                <x-action-panel title="Buka shift dari sini">
                    <form method="POST" action="{{ route('admin.shifts.open') }}" class="space-y-3 mt-3">
                        @csrf
                        <x-money-input name="opening_cash" label="Modal awal di laci" required />
                        <x-field name="note" label="Catatan (opsional)" placeholder="serah terima dari Budi" />
                        <x-action-submit>Buka shift</x-action-submit>
                    </form>
                </x-action-panel>
            </div>
        </x-card>
    @endif

    {{-- ── Riwayat ─────────────────────────────────────────────────── --}}
    <x-card>
        <h2 class="text-base font-bold text-ink mb-4">Riwayat Shift</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs table-stacked">
                <thead>
                    <tr class="text-ink-soft border-b border-line-soft">
                        <th scope="col" class="py-3 px-3 font-medium">Operator</th>
                        <th scope="col" class="py-3 px-3 font-medium">Dibuka</th>
                        <th scope="col" class="py-3 px-3 font-medium">Ditutup</th>
                        <th scope="col" class="py-3 px-3 font-medium md:text-right">Modal awal</th>
                        <th scope="col" class="py-3 px-3 font-medium md:text-right">Kas akhir</th>
                        <th scope="col" class="py-3 px-3 font-medium">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse ($history as $s)
                        <tr class="hover:bg-inset/60 transition-colors">
                            <td data-label="Operator" class="py-3 px-3 font-semibold text-ink">
                                {{ $s->operator?->name ?? '—' }}
                            </td>
                            <td data-label="Dibuka" class="py-3 px-3 text-ink-soft tabular">
                                {{ $s->opened_at->timezone($tz)->format('d/m H:i') }}
                            </td>
                            <td data-label="Ditutup" class="py-3 px-3 text-ink-soft tabular">
                                {{ $s->closed_at->timezone($tz)->format('d/m H:i') }}
                            </td>
                            <td data-label="Modal awal" class="py-3 px-3 md:text-right text-ink-soft">
                                <x-rupiah :value="$s->opening_cash" />
                            </td>
                            <td data-label="Kas akhir" class="py-3 px-3 md:text-right font-bold text-ink">
                                <x-rupiah :value="$s->closing_cash ?? 0" />
                            </td>
                            <td data-label="Catatan" class="py-3 px-3 text-ink-soft">
                                {{ $s->note ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <x-empty title="Belum ada shift yang ditutup" icon="clock" colspan="6">
                            Shift yang masih berjalan tidak muncul di sini — angka akhirnya belum ada.
                        </x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{--
            Selisih kas TIDAK ditampilkan di tabel ini.

            Menghitungnya butuh ringkasan tiap shift, yaitu satu query per
            baris — 30 query untuk satu halaman. Angkanya sudah tercatat utuh
            di audit log (`shift.closed`, berisi `expected_cash` dan
            `difference`), dan halaman Audit menampilkannya.
        --}}
        <p class="text-[11px] text-ink-soft mt-4">
            Selisih kas tiap shift tercatat di <a href="{{ route('admin.audit', ['action' => 'shift.closed']) }}"
               class="font-semibold text-brand-600 hover:underline">Audit → shift.closed</a>.
        </p>
    </x-card>

</x-layouts.app>
