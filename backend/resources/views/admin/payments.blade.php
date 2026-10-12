@php
    use App\Support\Time\BusinessDay;
    $tz = BusinessDay::timezone();
@endphp

<x-layouts.app title="Pembayaran" :poll="15">

    <x-page-header title="Pembayaran" subtitle="Semua uang masuk pada satu hari. Hari dipotong tengah malam WIB.">
        <x-slot:actions>
            {{-- Satu hari saja, bukan seluruh riwayat. Daftar panjang dengan
                 total di bawahnya menjawab pertanyaan yang tidak pernah
                 ditanyakan siapa pun. --}}
            <form method="GET" action="{{ route('admin.payments') }}">
                <input type="date" name="tanggal" value="{{ $tanggal }}"
                       onchange="this.form.submit()"
                       class="px-3 py-2 bg-card border border-line rounded-full text-xs text-ink tabular
                              focus:outline-none focus:border-brand-600 transition-all">
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-4 mb-4">
        <x-metric label="Tunai masuk" icon="money" :value="'Rp '.number_format($cash, 0, ',', '.')" chip="soft" />
        <x-metric label="QRIS masuk" icon="money" :value="'Rp '.number_format($qris, 0, ',', '.')" />
        <x-metric
            label="Total diterima"
            icon="money"
            :value="'Rp '.number_format($cash + $qris, 0, ',', '.')"
            :footnote="$payments->count().' transaksi tercatat'" />
    </div>

    {{--
        Hanya yang CONFIRMED yang dijumlahkan di kartu atas, tapi SEMUANYA
        ditampilkan di daftar. Pembayaran yang tertahan harus terlihat ada —
        menyembunyikannya membuat selisih laci jadi misteri.
    --}}
    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs table-stacked">
                <thead>
                    <tr class="text-ink-soft border-b border-line-soft">
                        <th scope="col" class="py-3 px-3 font-medium">Jam</th>
                        <th scope="col" class="py-3 px-3 font-medium">Sesi</th>
                        <th scope="col" class="py-3 px-3 font-medium">Station</th>
                        <th scope="col" class="py-3 px-3 font-medium">Metode</th>
                        <th scope="col" class="py-3 px-3 font-medium">Referensi</th>
                        <th scope="col" class="py-3 px-3 font-medium">Diterima</th>
                        <th scope="col" class="py-3 px-3 font-medium md:text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse ($payments as $p)
                        <tr class="hover:bg-inset/60 transition-colors">
                            <td data-label="Jam" class="py-3 px-3 text-ink-soft tabular">
                                {{ $p->confirmed_at?->timezone($tz)->format('H:i') ?? '—' }}
                            </td>
                            <td data-label="Sesi" class="py-3 px-3">
                                @if ($p->session)
                                    <a href="{{ route('admin.session', $p->session) }}"
                                       class="font-semibold text-brand-600 hover:underline tabular">
                                        {{ $p->session->code }}
                                    </a>
                                @else
                                    <span class="text-ink-soft">—</span>
                                @endif
                            </td>
                            <td data-label="Station" class="py-3 px-3 text-ink-soft">
                                {{ $p->session?->station?->code ?? '—' }}
                            </td>
                            <td data-label="Metode" class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded-chip text-[10px] font-semibold bg-inset text-ink">
                                    {{ $p->method->value }}
                                </span>
                                @if ($p->status !== 'CONFIRMED')
                                    <x-badge tone="warn">{{ $p->status }}</x-badge>
                                @endif
                            </td>
                            <td data-label="Referensi" class="py-3 px-3 text-ink-soft tabular">
                                {{ $p->reference ?: '—' }}
                            </td>
                            <td data-label="Diterima" class="py-3 px-3 text-ink-soft">
                                {{ $p->actor?->name ?? '—' }}
                            </td>
                            <td data-label="Jumlah" class="py-3 px-3 md:text-right font-bold text-ink">
                                <x-rupiah :value="$p->amount" />
                            </td>
                        </tr>
                    @empty
                        <x-empty title="Belum ada pembayaran hari ini" icon="money" colspan="7">
                            Pembayaran yang dikonfirmasi operator di tablet akan muncul di sini.
                        </x-empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

</x-layouts.app>
