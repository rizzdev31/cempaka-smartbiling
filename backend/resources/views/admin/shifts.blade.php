@php
    use App\Support\Time\BusinessDay;
    $tz = BusinessDay::timezone();
@endphp

<x-layouts.app title="Shift">

    <x-page-header title="Shift Kasir" subtitle="Serah terima laci kas. 30 shift terakhir yang sudah ditutup." />

    {{-- ── Shift yang sedang berjalan ──────────────────────────────── --}}
    @if ($current)
        @php $summary = app(\App\Services\ShiftService::class)->summary($current); @endphp

        <x-card class="mb-6" padding="p-4 sm:p-6">
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

            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <x-stat label="Modal awal" :value="'Rp '.number_format($current->opening_cash, 0, ',', '.')" />
                <x-stat label="Rental" :value="'Rp '.number_format($summary['rental'], 0, ',', '.')" />
                <x-stat label="F&B" :value="'Rp '.number_format($summary['fnb'], 0, ',', '.')" />
                <x-stat label="Tunai masuk" :value="'Rp '.number_format($summary['cash'], 0, ',', '.')" />
                <x-stat label="QRIS masuk" :value="'Rp '.number_format($summary['qris'], 0, ',', '.')" />
            </div>

            {{--
                Angka yang paling penting di halaman ini, dan satu-satunya
                yang dibandingkan dengan uang fisik nanti.
            --}}
            <div class="mt-4 p-4 rounded-block bg-brand-50 border border-brand-100">
                <div class="flex items-baseline justify-between gap-3 flex-wrap">
                    <span class="text-xs font-semibold text-brand-600">Kas seharusnya di laci sekarang</span>
                    <span class="text-xl font-extrabold text-brand-600 tabular">
                        Rp {{ number_format($current->opening_cash + $summary['cash'], 0, ',', '.') }}
                    </span>
                </div>
                <p class="text-[11px] text-brand-600 mt-2 leading-relaxed">
                    Modal awal + tunai masuk. QRIS tidak ikut — uangnya tidak ada di laci.
                </p>
                {{--
                    ⚠️ OD-029. Rumus yang benar menurut user adalah
                    `Modal + Cash − Pengeluaran`, dan suku terakhirnya belum
                    pernah dibangun. Disebutkan di sini, bukan disembunyikan:
                    kasir yang membandingkan angka ini dengan laci harus tahu
                    kenapa selisihnya bisa muncul tanpa ada yang mencuri.
                --}}
                <p class="text-[11px] text-warning mt-2 leading-relaxed font-medium">
                    Belum dikurangi pengeluaran kasir — pencatatannya belum ada (OD-029).
                    Kalau ada uang yang diambil dari laci untuk belanja, selisihnya akan muncul di sini.
                </p>
            </div>
        </x-card>
    @else
        <x-card class="mb-6">
            <x-empty title="Tidak ada shift yang terbuka" icon="clock">
                Operator membuka shift dari tablet sebelum mulai melayani.
            </x-empty>
        </x-card>
    @endif

    {{-- ── Riwayat ─────────────────────────────────────────────────── --}}
    <x-card padding="p-4 sm:p-6">
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
