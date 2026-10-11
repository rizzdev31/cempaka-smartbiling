<x-layouts.app title="F&B">

    <x-page-header title="F&B" subtitle="Menu, stok, dan pesanan yang belum sampai ke meja." />

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 xl:gap-6">

        {{-- ── Antrean pesanan ─────────────────────────────────────── --}}
        <x-card class="xl:col-span-5 order-1" padding="p-4 sm:p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-base font-bold text-ink">Antrean Pesanan</h2>
                <x-badge tone="{{ $orders->isEmpty() ? 'neutral' : 'warn' }}">{{ $orders->count() }} terbuka</x-badge>
            </div>

            @forelse ($orders as $order)
                @php
                    $nada = match ($order->status->value) {
                        'PENDING' => ['Menunggu', 'bg-warning-surface text-warning'],
                        'PROCESSING' => ['Dibuat', 'bg-awaiting-surface text-awaiting'],
                        default => ['Siap antar', 'bg-playing-surface text-playing'],
                    };
                @endphp

                <div class="py-3 {{ ! $loop->last ? 'border-b border-line-soft' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <span class="text-sm font-bold text-ink">
                                {{ $order->session?->station?->code ?? 'Tanpa station' }}
                            </span>
                            <span class="text-[11px] text-ink-soft ml-1 tabular">
                                {{ $order->created_at->timezone(\App\Support\Time\BusinessDay::timezone())->format('H:i') }}
                            </span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold shrink-0 {{ $nada[1] }}">
                            {{ $nada[0] }}
                        </span>
                    </div>

                    <ul class="mt-2 space-y-1">
                        @foreach ($order->items as $item)
                            <li class="flex items-baseline justify-between gap-3 text-xs">
                                <span class="text-ink-soft">
                                    <span class="font-semibold text-ink tabular">{{ $item->qty }}×</span>
                                    {{ $item->name }}
                                </span>
                                <x-rupiah :value="$item->subtotal" class="text-ink-soft shrink-0" />
                            </li>
                        @endforeach
                    </ul>

                    @if ($order->note)
                        {{-- Catatan dapur ("telur diceplok, cabai 5") adalah
                             alasan utama pesanan salah. Ditampilkan menonjol,
                             bukan disembunyikan di detail. --}}
                        <p class="mt-2 text-[11px] text-warning bg-warning-surface rounded-chip px-2 py-1">
                            {{ $order->note }}
                        </p>
                    @endif
                </div>
            @empty
                <x-empty title="Tidak ada pesanan terbuka" icon="cup">
                    Semua pesanan sudah diantar.
                </x-empty>
            @endforelse
        </x-card>

        {{-- ── Menu & stok ─────────────────────────────────────────── --}}
        <x-card class="xl:col-span-7 order-2" padding="p-4 sm:p-6">
            <h2 class="text-base font-bold text-ink mb-4">Menu & Stok</h2>

            @forelse ($products as $kategori => $daftar)
                <div class="mb-5 last:mb-0">
                    <h3 class="text-[11px] font-bold text-ink-soft uppercase tracking-wide mb-2">
                        {{ $kategori }}
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs table-stacked">
                            <thead>
                                <tr class="text-ink-soft border-b border-line-soft">
                                    <th scope="col" class="py-2 px-2 font-medium">Nama</th>
                                    <th scope="col" class="py-2 px-2 font-medium md:text-right">Harga</th>
                                    <th scope="col" class="py-2 px-2 font-medium md:text-right">HPP</th>
                                    <th scope="col" class="py-2 px-2 font-medium md:text-right">Stok</th>
                                    <th scope="col" class="py-2 px-2 font-medium md:text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line-soft">
                                @foreach ($daftar as $p)
                                    @php
                                        /*
                                         * `stock` null berarti TIDAK DILACAK
                                         * (mis. es batu), bukan nol. Menampilkan
                                         * "0" akan membuat orang mengira
                                         * barangnya habis.
                                         */
                                        $stok = match (true) {
                                            $p->stock === null => ['Tidak dilacak', 'bg-inset text-ink-soft'],
                                            $p->stock === 0 => ['Habis', 'bg-danger-surface text-danger'],
                                            $p->stock <= 3 => ['Sisa sedikit', 'bg-warning-surface text-warning'],
                                            default => ['Aman', 'bg-playing-surface text-playing'],
                                        };
                                    @endphp
                                    <tr class="hover:bg-inset/60 transition-colors">
                                        <td data-label="Nama" class="py-2.5 px-2 font-semibold text-ink">{{ $p->name }}</td>
                                        <td data-label="Harga" class="py-2.5 px-2 md:text-right text-ink">
                                            <x-rupiah :value="$p->price" />
                                        </td>
                                        <td data-label="HPP" class="py-2.5 px-2 md:text-right text-ink-soft">
                                            {{-- HPP boleh kosong: produk lama
                                                 belum tentu punya angkanya. --}}
                                            @if ($p->hpp !== null)
                                                <x-rupiah :value="$p->hpp" />
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td data-label="Stok" class="py-2.5 px-2 md:text-right text-ink tabular">
                                            {{ $p->stock ?? '—' }}
                                        </td>
                                        <td data-label="Status" class="py-2.5 px-2 md:text-right">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $stok[1] }}">
                                                {{ $stok[0] }}
                                            </span>
                                            @unless ($p->is_available)
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-idle-surface text-idle">
                                                    Dinonaktifkan
                                                </span>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <x-empty title="Belum ada menu" icon="cup">
                    Tambahkan produk lewat seeder atau halaman Harga &amp; Paket.
                </x-empty>
            @endforelse
        </x-card>
    </div>

</x-layouts.app>
