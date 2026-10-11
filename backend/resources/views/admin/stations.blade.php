<x-layouts.app title="Station & TV" :poll="10">

    <x-page-header
        title="Station & TV"
        :subtitle="'Status TV dihitung dari heartbeat terakhir. Lewat '.$offlineThreshold.' detik tanpa kabar = offline.'" />

    {{--
        Kartu, bukan tabel.

        Yang dicari di halaman ini adalah "mana yang bermasalah", bukan
        membandingkan nilai antar baris. Grid kartu menyusut mulus dari empat
        kolom ke satu tanpa perlu dipecah seperti tabel.
    --}}
    <div class="grid grid-cols-[repeat(auto-fit,minmax(19rem,1fr))] gap-4">
        @forelse ($stations as $station)
            @php
                $device = $station->device;
                $sesi = $aktif[$station->id] ?? null;

                $detik = $device?->last_seen_at?->diffInSeconds(now());
                $online = $detik !== null && $detik <= $offlineThreshold;

                /*
                 * TIGA keadaan, bukan dua. "Belum pernah terhubung" berbeda
                 * dari "pernah, sekarang mati": yang pertama berarti TV-nya
                 * belum pernah didaftarkan, yang kedua berarti ada yang rusak.
                 * Menggabungkan keduanya jadi "offline" membuat teknisi
                 * mencari kabel pada TV yang memang belum dipasang.
                 */
                /*
                 * Kelasnya ditulis UTUH, bukan dirangkai dari nama nada.
                 * Tailwind v4 memindai kelas literal di berkas source;
                 * `bg-{$nada}-surface` tidak pernah muncul sebagai teks, jadi
                 * CSS-nya tidak pernah dibuat — dan hasilnya badge tanpa
                 * warna sama sekali, hanya di produksi.
                 */
                $kondisi = match (true) {
                    $device === null => ['Belum didaftarkan', 'bg-idle-surface text-idle', 'bg-idle'],
                    $online => ['Online', 'bg-playing-surface text-playing', 'bg-playing'],
                    default => ['Offline', 'bg-danger-surface text-danger', 'bg-danger'],
                };
            @endphp

            <x-card padding="p-5" class="flex flex-col gap-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-lg font-extrabold text-ink tracking-tight">{{ $station->code }}</span>
                            <x-badge tone="neutral">{{ $station->stationType?->name ?? '—' }}</x-badge>
                        </div>
                        <p class="text-xs text-ink-soft mt-0.5 truncate">{{ $station->name }}</p>
                    </div>

                    {{-- Warna + titik + teks — `color-not-only`. --}}
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold shrink-0 {{ $kondisi[1] }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $kondisi[2] }}"></span>
                        {{ $kondisi[0] }}
                    </span>
                </div>

                @php
                    /*
                     * Sisa waktu dihitung di sini, bukan dikirim server tiap
                     * detik — aturan yang sama dengan tablet dan TV
                     * (CLAUDE.md §4). Halaman web tidak berdetak; angkanya
                     * benar saat halaman dimuat, dan itu memang cukup untuk
                     * owner yang sedang melihat ringkasan.
                     *
                     * `end_at` null = Open Tab (DEC-034), bukan data hilang.
                     */
                    $sisa = match (true) {
                        $sesi === null => '—',
                        $sesi->end_at === null => 'Open Tab',
                        default => (function () use ($sesi) {
                            $menit = (int) round(now()->diffInSeconds($sesi->end_at, false) / 60);

                            return $menit < 0 ? 'Lewat '.abs($menit).'m' : $menit.'m';
                        })(),
                    };
                @endphp

                <div class="grid grid-cols-2 gap-3">
                    <x-stat label="Sisa waktu" :value="$sisa">
                        @if ($sesi)
                            <x-status-pill :status="$sesi->status->value" />
                        @endif
                    </x-stat>
                    <x-stat
                        label="Heartbeat terakhir"
                        :value="$detik === null ? '—' : ($detik < 60 ? $detik.'d' : round($detik / 60).'m')" />
                </div>

                @if ($sesi)
                    <div class="text-xs text-ink-soft">
                        <span class="font-medium text-ink">{{ $sesi->customerLabel() }}</span>
                        · {{ $sesi->package_name }}
                        @if ($sesi->end_at)
                            · selesai {{ $sesi->end_at->timezone(\App\Support\Time\BusinessDay::timezone())->format('H:i') }}
                        @else
                            {{-- DEC-034: Postpaid tidak punya end_at. "—" bukan
                                 kelalaian; waktunya memang belum ditentukan. --}}
                            · Open Tab, tanpa batas waktu
                        @endif
                    </div>
                @else
                    <p class="text-xs text-ink-soft">
                        {{ $station->status->value === 'ACTIVE' ? 'Kosong, siap dipakai' : 'Station '.$station->status->value }}
                    </p>
                @endif

                {{-- ── Aksi per station — DEC-045 ─────────────────── --}}
                @if ($sesi)
                    <a href="{{ route('admin.session', $sesi) }}"
                       class="w-full px-4 py-2 text-xs font-semibold rounded-full text-card bg-brand-600
                              hover:bg-brand-700 transition-colors text-center">
                        Buka sesi {{ $sesi->code }}
                    </a>
                @elseif ($station->status === \App\Enums\StationStatus::ACTIVE && isset($paket[$station->station_type_id]))
                    <x-action-panel title="Mulai sesi baru">
                        <form method="POST" action="{{ route('admin.sessions.store') }}" class="space-y-3 mt-3">
                            @csrf
                            <input type="hidden" name="station_id" value="{{ $station->id }}">

                            <x-select-field name="package_id" label="Paket"
                                            :options="$paket[$station->station_type_id]" required />

                            {{--
                                Prepaid vs Postpaid, dan bedanya disebut.
                                Nama modenya saja tidak memberi tahu apa pun
                                kepada orang yang baru memakai sistem ini.
                            --}}
                            <x-select-field name="mode" label="Mode"
                                            :options="['PREPAID' => 'Prepaid — bayar di awal, timer mundur', 'POSTPAID' => 'Open Tab — bayar di akhir, timer maju']" />

                            {{-- DEC-008: satu sesi satu customer. Pilih member
                                 ATAU ketik nama; kalau member dipilih, nama
                                 ketikan diabaikan, bukan digabung. --}}
                            @if ($member !== [])
                                <x-select-field name="customer_id" label="Member (opsional)"
                                                :options="$member" placeholder="Bukan member / walk-in" />
                            @endif

                            <x-field name="customer_name" label="Nama customer" placeholder="Andi" />

                            <x-action-submit>Mulai sesi</x-action-submit>
                        </form>
                    </x-action-panel>
                @endif

                @if ($device)
                    <div class="pt-3 border-t border-line-soft text-[11px] text-ink-soft flex flex-wrap gap-x-3 gap-y-1">
                        <span class="tabular">{{ $device->device_uid }}</span>
                        @if ($device->model)<span>{{ $device->model }}</span>@endif
                        @if ($device->app_version)<span>v{{ $device->app_version }}</span>@endif
                        @if ($device->revoked_at)<span class="text-danger font-semibold">token dicabut</span>@endif
                    </div>
                @endif
            </x-card>
        @empty
            <div class="col-span-full">
                <x-card>
                    <x-empty title="Belum ada station" icon="tv">
                        Jalankan seeder, atau tambahkan station dari database.
                    </x-empty>
                </x-card>
            </div>
        @endforelse
    </div>

</x-layouts.app>
