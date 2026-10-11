@php
    use App\Enums\SessionMode;
    use App\Support\Time\BusinessDay;

    $tz = BusinessDay::timezone();
    $jam = fn ($t) => $t?->timezone($tz)->format('d M Y, H:i') ?? '—';
@endphp

<x-layouts.app :title="'Sesi '.$session->code" :poll="$session->status->isRunning() ? 10 : 0">

    <x-page-header :title="$session->code" :subtitle="$session->station?->code.' · '.$session->customerLabel()">
        <x-slot:actions>
            <x-status-pill :status="$session->status->value" />
            <a href="{{ route('admin.sessions') }}"
               class="px-4 py-2 text-xs font-semibold rounded-full text-ink bg-card border border-line hover:bg-inset transition-colors">
                Kembali ke daftar
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 xl:gap-6">

        {{-- ── Rincian tagihan ─────────────────────────────────────── --}}
        <x-card class="xl:col-span-7" padding="p-4 sm:p-6">
            <h2 class="text-base font-bold text-ink mb-4">Rincian Tagihan</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs table-stacked">
                    <thead>
                        <tr class="text-ink-soft border-b border-line-soft">
                            <th scope="col" class="py-2 px-2 font-medium">Jenis</th>
                            <th scope="col" class="py-2 px-2 font-medium">Nama</th>
                            <th scope="col" class="py-2 px-2 font-medium md:text-right">Qty</th>
                            <th scope="col" class="py-2 px-2 font-medium md:text-right">Satuan</th>
                            <th scope="col" class="py-2 px-2 font-medium md:text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        @forelse ($session->items as $item)
                            <tr>
                                <td data-label="Jenis" class="py-2.5 px-2">
                                    <span class="px-2 py-0.5 rounded-chip text-[10px] font-semibold bg-inset text-ink-soft">
                                        {{ $item->type->value }}
                                    </span>
                                </td>
                                <td data-label="Nama" class="py-2.5 px-2 text-ink font-medium">{{ $item->name }}</td>
                                <td data-label="Qty" class="py-2.5 px-2 md:text-right text-ink-soft tabular">{{ $item->qty }}</td>
                                <td data-label="Satuan" class="py-2.5 px-2 md:text-right text-ink-soft">
                                    <x-rupiah :value="$item->unit_price" />
                                </td>
                                <td data-label="Subtotal" class="py-2.5 px-2 md:text-right font-bold text-ink">
                                    <x-rupiah :value="$item->subtotal" />
                                </td>
                            </tr>
                        @empty
                            {{-- Postpaid belum punya baris rental sampai
                                 checkout (DEC-034). Kosong di sini wajar. --}}
                            <x-empty title="Belum ada item" icon="money" colspan="5">
                                Open Tab baru mendapat baris rental saat checkout.
                            </x-empty>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4 pt-4 border-t border-line-soft space-y-1.5">
                @foreach ([
                    'Rental' => $totals->rental,
                    'Extend' => $totals->extend,
                    'F&B' => $totals->fnb,
                    'Diskon' => $totals->discount,
                    'Penyesuaian' => $totals->adjustment,
                ] as $label => $nilai)
                    {{-- Baris nol disembunyikan: lima baris "Rp 0" membuat
                         satu angka yang penting jadi sulit ditemukan. --}}
                    @continue($nilai === 0)
                    <div class="flex items-baseline justify-between gap-3 text-xs">
                        <span class="text-ink-soft">{{ $label }}</span>
                        <x-rupiah :value="$nilai" class="text-ink" />
                    </div>
                @endforeach

                <div class="flex items-baseline justify-between gap-3 pt-2 border-t border-line-soft">
                    <span class="text-sm font-bold text-ink">Total</span>
                    <span class="text-lg font-extrabold text-ink"><x-rupiah :value="$totals->grandTotal()" /></span>
                </div>

                <div class="flex items-baseline justify-between gap-3 text-xs">
                    <span class="text-ink-soft">Sudah dibayar</span>
                    <x-rupiah :value="$totals->paid" class="text-ink" />
                </div>

                @if ($totals->balanceDue() > 0)
                    <div class="flex items-baseline justify-between gap-3 text-xs">
                        <span class="font-semibold text-danger">Kurang bayar</span>
                        <span class="font-bold text-danger"><x-rupiah :value="$totals->balanceDue()" /></span>
                    </div>
                @endif

                @php
                    /*
                     * `balanceDue()` dijaga tidak negatif — kelebihan bayar
                     * tidak boleh tampil sebagai tagihan minus di tablet
                     * (API.md §7). Tapi di halaman audit seperti ini,
                     * kelebihan itu JUSTRU yang perlu terlihat: ia berarti
                     * ada yang salah di tempat lain.
                     */
                    $lebih = $totals->paid - $totals->grandTotal();
                @endphp

                @if ($lebih > 0)
                    <div class="flex items-baseline justify-between gap-3 text-xs">
                        <span class="font-semibold text-warning">Kelebihan bayar</span>
                        <span class="font-bold text-warning"><x-rupiah :value="$lebih" /></span>
                    </div>
                @endif
            </div>
        </x-card>

        {{-- ── Kolom kanan ─────────────────────────────────────────── --}}
        <div class="xl:col-span-5 flex flex-col gap-4">

            {{-- Waktu --}}
            <x-card padding="p-4 sm:p-6">
                <h2 class="text-base font-bold text-ink mb-4">Waktu</h2>

                <dl class="space-y-2 text-xs">
                    @foreach ([
                        'Dibuka' => $jam($session->created_at),
                        'Mulai' => $jam($session->started_at),
                        /*
                         * `end_at` null punya DUA arti yang berbeda, dan
                         * menyamakannya menyesatkan:
                         *
                         * - Postpaid: memang tidak punya batas (DEC-034).
                         * - Prepaid yang belum dibayar: timernya belum
                         *   mulai, jadi batasnya belum ada — bukan tidak ada.
                         *
                         * Versi pertama menulis "Open Tab — tanpa batas"
                         * untuk keduanya, dan sesi Prepaid yang menunggu
                         * bayar terbaca seolah boleh main sepuasnya.
                         */
                        'Selesai dijadwalkan' => match (true) {
                            $session->end_at !== null => $jam($session->end_at),
                            $session->mode === SessionMode::POSTPAID => 'Open Tab — tanpa batas',
                            default => 'Belum mulai — menunggu pembayaran',
                        },
                        'Berakhir' => $jam($session->ended_at),
                    ] as $label => $nilai)
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="text-ink-soft shrink-0">{{ $label }}</dt>
                            <dd class="text-ink font-medium tabular text-right">{{ $nilai }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($session->actual_duration_minutes !== null)
                    <div class="grid grid-cols-2 gap-3 mt-4">
                        <x-stat label="Durasi sebenarnya" :value="$session->actual_duration_minutes.'m'" />
                        {{-- Dua angka ini SENGAJA ditampilkan berdampingan —
                             supaya "kenapa 63 menit ditagih 60" bisa dijawab
                             tanpa membuka dokumen (DEC-009). --}}
                        <x-stat label="Durasi ditagih" :value="($session->billable_duration_minutes ?? 0).'m'" />
                    </div>
                @endif

                <dl class="mt-4 pt-4 border-t border-line-soft space-y-2 text-xs">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-ink-soft">Mode</dt>
                        <dd class="text-ink font-medium">{{ $session->mode->value }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-ink-soft">Paket</dt>
                        <dd class="text-ink font-medium text-right">{{ $session->package_name ?? '—' }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-ink-soft">Tarif dibekukan</dt>
                        {{-- DEC-040: harga disalin saat sesi dibuat. Angka ini
                             yang menjelaskan kenapa tagihan tidak ikut berubah
                             setelah owner menaikkan harga. --}}
                        <dd class="text-ink font-medium"><x-rupiah :value="$session->hourly_rate" /> / jam</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-ink-soft">Dibuka oleh</dt>
                        <dd class="text-ink font-medium">{{ $session->openedBy?->name ?? '—' }}</dd>
                    </div>
                </dl>
            </x-card>

            {{-- Pembayaran --}}
            <x-card padding="p-4 sm:p-6">
                <h2 class="text-base font-bold text-ink mb-4">Pembayaran</h2>

                @forelse ($session->payments as $p)
                    <div class="flex items-start justify-between gap-3 py-2 {{ ! $loop->last ? 'border-b border-line-soft' : '' }}">
                        <div class="min-w-0">
                            <span class="text-xs font-semibold text-ink">{{ $p->method->value }}</span>
                            @if ($p->status !== 'CONFIRMED')
                                <x-badge tone="warn">{{ $p->status }}</x-badge>
                            @endif
                            <p class="text-[11px] text-ink-soft tabular">
                                {{ $jam($p->confirmed_at) }}
                                @if ($p->actor) · {{ $p->actor->name }} @endif
                            </p>
                            @if ($p->reference)
                                <p class="text-[11px] text-ink-soft tabular">Ref {{ $p->reference }}</p>
                            @endif
                        </div>
                        <span class="text-sm font-bold text-ink shrink-0"><x-rupiah :value="$p->amount" /></span>
                    </div>
                @empty
                    <x-empty title="Belum ada pembayaran" icon="money" />
                @endforelse
            </x-card>

            {{-- Struk --}}
            @if ($session->receipt_number)
                <x-card padding="p-4 sm:p-6">
                    <h2 class="text-base font-bold text-ink mb-3">Struk</h2>

                    <div class="flex items-baseline justify-between gap-3 text-xs mb-1">
                        <span class="text-ink-soft">Nomor</span>
                        <span class="text-ink font-bold tabular">{{ $session->receipt_number }}</span>
                    </div>
                    <div class="flex items-baseline justify-between gap-3 text-xs">
                        <span class="text-ink-soft">Diterbitkan</span>
                        <span class="text-ink font-medium tabular">{{ $jam($session->receipt_issued_at) }}</span>
                    </div>

                    @if ($waPhone)
                        @php
                            $pesan = "Struk {$session->receipt_number} — ".config('app.name')."\n"
                                ."Total: Rp ".number_format($totals->grandTotal(), 0, ',', '.');
                        @endphp

                        {{--
                            Tautan `wa.me`, BUKAN pengiriman otomatis
                            (DEC-035). Yang terbuka adalah WhatsApp milik
                            operator dengan pesan sudah terisi; dia yang
                            menekan kirim.

                            Gratis, tidak butuh akun bisnis, dan tidak pernah
                            mengirim apa pun tanpa ada manusia yang melihat
                            isinya lebih dulu.
                        --}}
                        <a href="https://wa.me/{{ $waPhone }}?text={{ rawurlencode($pesan) }}"
                           target="_blank" rel="noopener noreferrer"
                           class="mt-4 w-full px-4 py-2 text-xs font-semibold rounded-full
                                  text-card bg-playing hover:opacity-90 transition-opacity
                                  flex items-center justify-center gap-2">
                            Kirim struk lewat WhatsApp
                        </a>
                        <p class="text-[11px] text-ink-soft mt-2 text-center">
                            Membuka WhatsApp Anda dengan pesan sudah terisi. Anda yang menekan kirim.
                        </p>
                    @else
                        <p class="text-[11px] text-ink-soft mt-3">
                            {{ $session->customer
                                ? 'Customer ini belum punya nomor HP yang bisa dipakai WhatsApp.'
                                : 'Sesi walk-in — tidak ada nomor tujuan.' }}
                        </p>
                    @endif
                </x-card>
            @endif
        </div>
    </div>

    {{-- ── Aksi sesi — DEC-045 ─────────────────────────────────────
         Laravel adalah owner dari semua fitur dan harus punya setiap akses,
         walaupun bukan dia yang berhadapan dengan customer.

         Panel tertutup secara bawaan: enam form terbuka sekaligus membuat
         yang dicari tenggelam, dan di tablet mendorong rincian tagihan
         sampai di luar pandangan. --}}
    @unless ($session->status->isFinal())
        <x-card class="mt-4" padding="p-4 sm:p-6">
            <div class="flex items-center justify-between gap-3 mb-4 flex-wrap">
                <h2 class="text-base font-bold text-ink">Aksi</h2>
                <p class="text-[11px] text-ink-soft">
                    Aturannya sama persis dengan yang dipakai tablet — satu Service, satu hasil.
                </p>
            </div>

            <div class="grid grid-cols-[repeat(auto-fit,minmax(17rem,1fr))] gap-3 items-start">

                <x-action-panel title="Catat pembayaran">
                    <form method="POST" action="{{ route('admin.sessions.pay', $session) }}" class="space-y-3 mt-3">
                        @csrf
                        <x-select-field name="method" label="Metode" :options="$metodeBayar" />
                        <x-money-input name="amount" label="Jumlah" required />
                        <x-field name="reference" label="Referensi (QRIS)" placeholder="opsional" />
                        <x-action-submit>Catat pembayaran</x-action-submit>
                    </form>
                </x-action-panel>

                @if ($session->isExtendable())
                    <x-action-panel title="Tambah waktu">
                        <form method="POST" action="{{ route('admin.sessions.extend', $session) }}" class="space-y-3 mt-3">
                            @csrf
                            {{-- Kelipatan 30 menit saja (DEC-007). Ditawarkan
                                 sebagai pilihan, bukan kolom bebas — kolom
                                 bebas mengundang "45" yang pasti ditolak. --}}
                            <x-select-field name="duration_minutes" label="Durasi"
                                            :options="[30 => '30 menit', 60 => '1 jam', 90 => '1,5 jam', 120 => '2 jam', 180 => '3 jam']"
                                            :selected="60" />
                            <p class="text-[11px] text-ink-soft">
                                Kelipatan 30 menit. Harga memakai tarif yang dibekukan saat sesi dibuka.
                            </p>
                            <x-action-submit>Tambah waktu</x-action-submit>
                        </form>
                    </x-action-panel>
                @endif

                @if ($stationTujuan !== [])
                    <x-action-panel title="Pindah station">
                        <form method="POST" action="{{ route('admin.sessions.swap', $session) }}" class="space-y-3 mt-3">
                            @csrf
                            <x-select-field name="target_station_id" label="Station tujuan"
                                            :options="$stationTujuan" placeholder="Pilih station" required />
                            <x-field name="reason" label="Alasan" placeholder="stik rusak" />
                            {{-- DEC-021: hanya dalam tipe konsol yang sama.
                                 Disebutkan supaya tidak dicari-cari kenapa
                                 PS4 tidak muncul saat sesinya ada di PS3. --}}
                            <p class="text-[11px] text-ink-soft">
                                Hanya station {{ $session->station?->stationType?->name }} yang kosong. Sisa waktu ikut pindah.
                            </p>
                            <x-action-submit>Pindahkan</x-action-submit>
                        </form>
                    </x-action-panel>
                @endif

                @if ($menu->isNotEmpty())
                    <x-action-panel title="Tambah pesanan F&B">
                        <form method="POST" action="{{ route('admin.sessions.fnb', $session) }}" class="space-y-3 mt-3">
                            @csrf
                            <div class="max-h-56 overflow-y-auto -mx-1 px-1 space-y-1.5">
                                @foreach ($menu as $i => $m)
                                    <div class="flex items-center gap-2">
                                        <input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $m->id }}">
                                        <span class="flex-1 min-w-0 text-xs text-ink truncate">{{ $m->name }}</span>
                                        <span class="text-[11px] text-ink-soft shrink-0">
                                            <x-rupiah :value="$m->price" />
                                        </span>
                                        {{-- Harga TIDAK dikirim — selalu dari
                                             server (CLAUDE.md §4). Yang
                                             dikirim hanya id dan jumlah. --}}
                                        <input type="text" inputmode="numeric" pattern="[0-9]*"
                                               name="items[{{ $i }}][qty]" value="0"
                                               aria-label="Jumlah {{ $m->name }}"
                                               class="w-12 shrink-0 px-2 py-1 bg-inset border border-transparent rounded-chip
                                                      text-xs text-ink tabular text-center
                                                      focus:outline-none focus:bg-card focus:border-brand-600 transition-all">
                                    </div>
                                @endforeach
                            </div>
                            <x-field name="note" label="Catatan dapur" placeholder="telur diceplok, cabai 5" />
                            <x-action-submit>Buat pesanan</x-action-submit>
                        </form>
                    </x-action-panel>
                @endif

                <x-action-panel title="Checkout" tone="warning">
                    <form method="POST" action="{{ route('admin.sessions.checkout', $session) }}" class="space-y-3 mt-3">
                        @csrf
                        <div class="p-3 rounded-chip bg-inset flex items-baseline justify-between gap-2 text-xs">
                            <span class="text-ink-soft">Kurang bayar</span>
                            <span class="font-bold text-ink"><x-rupiah :value="$totals->balanceDue()" /></span>
                        </div>
                        <x-select-field name="method" label="Metode" :options="$metodeBayar" />
                        <x-money-input name="amount" label="Jumlah bayar" :value="$totals->balanceDue() ?: null" />
                        <x-field name="reference" label="Referensi (QRIS)" placeholder="opsional" />

                        @if ($saldoMember > 0)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="use_credit" value="1" class="w-4 h-4 rounded-chip accent-brand-600">
                                <span class="text-xs text-ink">
                                    Potong saldo member (<x-rupiah :value="$saldoMember" />)
                                </span>
                            </label>
                        @endif

                        <p class="text-[11px] text-ink-soft">
                            Kosongkan jumlah kalau tagihannya sudah lunas dari pembayaran sebelumnya.
                        </p>
                        <x-action-submit tone="warning">Selesaikan sesi</x-action-submit>
                    </form>
                </x-action-panel>

                <x-action-panel title="Batalkan sesi" tone="danger">
                    <form method="POST" action="{{ route('admin.sessions.cancel', $session) }}" class="space-y-3 mt-3">
                        @csrf
                        <x-field name="reason" label="Alasan" placeholder="salah input station" required />
                        {{-- Alasan WAJIB di web, walau API membolehkannya
                             kosong. Membatalkan sesi dari belakang meja tanpa
                             alasan adalah persis yang perlu dijelaskan saat
                             laporan tidak cocok. --}}
                        <p class="text-[11px] text-ink-soft">
                            Alasannya masuk audit log dan akan dibaca saat laporan diperiksa.
                        </p>
                        <x-action-submit tone="danger">Batalkan</x-action-submit>
                    </form>
                </x-action-panel>
            </div>
        </x-card>
    @endunless

    {{-- ── Jejak audit ─────────────────────────────────────────────── --}}
    <x-card class="mt-4" padding="p-4 sm:p-6">
        <h2 class="text-base font-bold text-ink mb-4">Jejak</h2>

        @forelse ($logs as $log)
            <div class="flex items-start gap-3 py-2 {{ ! $loop->last ? 'border-b border-line-soft' : '' }}">
                <span class="text-[11px] text-ink-soft tabular shrink-0 w-28">
                    {{ $log->created_at->timezone($tz)->format('d/m H:i:s') }}
                </span>
                <span class="px-2 py-0.5 rounded-chip text-[10px] font-semibold bg-inset text-ink tabular shrink-0">
                    {{ $log->action }}
                </span>
                <span class="text-xs text-ink-soft">{{ $log->actor_name ?? 'Sistem' }}</span>
            </div>
        @empty
            <x-empty title="Belum ada jejak" icon="search">
                Extend, swap, checkout, dan pembatalan akan tercatat di sini.
            </x-empty>
        @endforelse
    </x-card>

</x-layouts.app>
