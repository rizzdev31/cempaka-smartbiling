<x-layouts.app title="F&B" :poll="10">

    <x-page-header title="F&B" subtitle="Menu, stok, dan pesanan yang belum sampai ke meja." />

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">

        {{-- ── Antrean pesanan ─────────────────────────────────────── --}}
        <x-card class="xl:col-span-5 order-1">
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

                    @php
                        /*
                         * Hanya SATU langkah maju yang ditawarkan, bukan
                         * seluruh daftar status. Dropdown berisi lima pilihan
                         * mengundang lompatan yang tidak masuk akal
                         * (PENDING → DELIVERED tanpa pernah dibuat), dan
                         * service memang akan menolaknya — tapi baru setelah
                         * ditekan.
                         */
                        $lanjut = match ($order->status->value) {
                            'PENDING' => ['PROCESSING', 'Mulai buat'],
                            'PROCESSING' => ['READY', 'Tandai siap'],
                            'READY' => ['DELIVERED', 'Sudah diantar'],
                            default => null,
                        };
                    @endphp

                    <div class="flex items-center gap-2 mt-3">
                        @if ($lanjut)
                            <form method="POST" action="{{ route('admin.fnb-orders.status', $order) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $lanjut[0] }}">
                                <button type="submit"
                                        class="px-3 py-1.5 text-[11px] font-semibold rounded-full
                                               text-card bg-brand-600 hover:bg-brand-700 transition-colors">
                                    {{ $lanjut[1] }}
                                </button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('admin.fnb-orders.status', $order) }}">
                            @csrf
                            <input type="hidden" name="status" value="CANCELLED">
                            <button type="submit"
                                    class="px-3 py-1.5 text-[11px] font-semibold rounded-full
                                           text-ink-soft bg-inset hover:bg-danger-surface hover:text-danger transition-colors">
                                Batalkan
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <x-empty title="Tidak ada pesanan terbuka" icon="cup">
                    Semua pesanan sudah diantar.
                </x-empty>
            @endforelse
        </x-card>

        {{-- ── Menu & stok ─────────────────────────────────────────── --}}
        <x-card class="xl:col-span-7 order-2">
            <h2 class="text-base font-bold text-ink mb-4">Menu & Stok</h2>

            @if ($products->isEmpty())
                <x-empty title="Belum ada menu" icon="cup">
                    Tambahkan menu dari halaman Harga & Paket.
                </x-empty>
            @else
                @include("admin.partials.menu-stok")
            @endif
        </x-card>
    </div>

</x-layouts.app>
