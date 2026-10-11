<x-layouts.app title="Harga & Paket">

    <x-page-header title="Harga &amp; Paket" subtitle="Tarif rental per tipe konsol dan harga F&amp;B.">
        <x-slot:actions>
            @if ($bolehUbah)
                <x-badge tone="brand">Anda boleh mengubah harga</x-badge>
            @else
                <x-badge tone="neutral">Hanya lihat</x-badge>
            @endif
        </x-slot:actions>
    </x-page-header>

    @unless ($bolehUbah)
        {{--
            DEC-020: hanya owner yang boleh mengubah tarif.

            Alasannya disebutkan, bukan sekadar "tidak boleh". Admin yang
            tidak tahu kenapa aksesnya ditolak akan mengira sistemnya rusak
            dan mencari jalan memutar.
        --}}
        <div class="flex items-start gap-3 p-4 mb-6 rounded-block bg-inset border border-line">
            <span class="text-ink-soft shrink-0 mt-0.5"><x-icon name="warning" class="w-4 h-4" /></span>
            <p class="text-xs text-ink-soft leading-relaxed">
                <span class="font-bold text-ink">Perubahan harga dikunci untuk owner</span> (DEC-020).
                Tarif menentukan seluruh pendapatan, jadi hanya satu orang yang boleh menggesernya.
                Hubungi owner kalau ada tarif yang perlu diperbarui.
            </p>
        </div>
    @endunless

    {{--
        Harga yang diubah TIDAK mengubah sesi yang sedang berjalan.

        Ini pertanyaan yang paling sering muncul (DEC-040), dan jawabannya
        ditaruh di halaman tempat orang bertanya — bukan hanya di dokumen.
    --}}
    <div class="flex items-start gap-3 p-4 mb-6 rounded-block bg-brand-50 border border-brand-100">
        <span class="text-brand-600 shrink-0 mt-0.5"><x-icon name="clock" class="w-4 h-4" /></span>
        <p class="text-xs text-brand-600 leading-relaxed">
            Harga baru berlaku untuk <span class="font-bold">sesi berikutnya</span>.
            Sesi yang sedang berjalan tetap memakai harga saat dibuka — termasuk saat di-extend.
            Customer sudah disebutkan harganya di depan, dan harga itu tidak boleh bergerak setelah dia duduk.
        </p>
    </div>

    {{-- ── Paket rental ────────────────────────────────────────────── --}}
    @forelse ($packages as $tipe => $daftar)
        <x-card class="mb-4" padding="p-4 sm:p-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-base font-bold text-ink">{{ $tipe }}</h2>
                <x-badge tone="neutral">{{ $daftar->count() }} paket</x-badge>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs table-stacked">
                    <thead>
                        <tr class="text-ink-soft border-b border-line-soft">
                            <th scope="col" class="py-2 px-2 font-medium">Paket</th>
                            <th scope="col" class="py-2 px-2 font-medium md:text-right">Durasi</th>
                            <th scope="col" class="py-2 px-2 font-medium md:text-right">Harga</th>
                            <th scope="col" class="py-2 px-2 font-medium md:text-right">Setara / jam</th>
                            <th scope="col" class="py-2 px-2 font-medium md:text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-soft">
                        @foreach ($daftar as $p)
                            @php
                                /*
                                 * Tarif per jam DIHITUNG, tidak disimpan —
                                 * rumus yang sama dengan `ExtendPolicy`
                                 * (DEC-007). Kolom ini yang membuat paket
                                 * bisa dibandingkan: "3 jam 35.000" dan
                                 * "1 jam 10.000" baru terlihat bedanya
                                 * setelah jadi angka per jam.
                                 */
                                $perJam = $p->duration_minutes > 0
                                    ? (int) round($p->price / ($p->duration_minutes / 60))
                                    : 0;
                            @endphp
                            <tr class="hover:bg-inset/60 transition-colors">
                                <td data-label="Paket" class="py-2.5 px-2 font-semibold text-ink">{{ $p->name }}</td>
                                <td data-label="Durasi" class="py-2.5 px-2 md:text-right text-ink-soft tabular">
                                    {{ $p->duration_minutes }} menit
                                </td>
                                <td data-label="Harga" class="py-2.5 px-2 md:text-right font-bold text-ink">
                                    <x-rupiah :value="$p->price" />
                                </td>
                                <td data-label="Setara / jam" class="py-2.5 px-2 md:text-right text-ink-soft">
                                    <x-rupiah :value="$perJam" />
                                </td>
                                <td data-label="Status" class="py-2.5 px-2 md:text-right">
                                    @if ($p->is_active)
                                        <x-badge tone="up">Aktif</x-badge>
                                    @else
                                        <x-badge tone="neutral">Nonaktif</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @empty
        <x-card class="mb-4">
            <x-empty title="Belum ada paket" icon="money">
                Jalankan seeder untuk memasukkan tarif awal.
            </x-empty>
        </x-card>
    @endforelse

    {{-- ── Harga F&B ───────────────────────────────────────────────── --}}
    <x-card padding="p-4 sm:p-6">
        <h2 class="text-base font-bold text-ink mb-4">Harga F&amp;B</h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs table-stacked">
                <thead>
                    <tr class="text-ink-soft border-b border-line-soft">
                        <th scope="col" class="py-2 px-2 font-medium">Nama</th>
                        <th scope="col" class="py-2 px-2 font-medium">Kategori</th>
                        <th scope="col" class="py-2 px-2 font-medium md:text-right">Harga</th>
                        <th scope="col" class="py-2 px-2 font-medium md:text-right">HPP</th>
                        <th scope="col" class="py-2 px-2 font-medium md:text-right">Margin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-soft">
                    @forelse ($products as $p)
                        @php
                            $margin = ($p->hpp !== null && $p->price > 0)
                                ? round(($p->price - $p->hpp) / $p->price * 100)
                                : null;
                        @endphp
                        <tr class="hover:bg-inset/60 transition-colors">
                            <td data-label="Nama" class="py-2.5 px-2 font-semibold text-ink">{{ $p->name }}</td>
                            <td data-label="Kategori" class="py-2.5 px-2 text-ink-soft">{{ $p->category }}</td>
                            <td data-label="Harga" class="py-2.5 px-2 md:text-right font-bold text-ink">
                                <x-rupiah :value="$p->price" />
                            </td>
                            <td data-label="HPP" class="py-2.5 px-2 md:text-right text-ink-soft">
                                @if ($p->hpp !== null)<x-rupiah :value="$p->hpp" />@else — @endif
                            </td>
                            <td data-label="Margin" class="py-2.5 px-2 md:text-right">
                                {{-- Tanpa HPP, margin tidak bisa dihitung. "0%"
                                     akan terbaca sebagai rugi. --}}
                                @if ($margin === null)
                                    <span class="text-ink-soft">—</span>
                                @else
                                    <x-badge tone="{{ $margin >= 50 ? 'up' : ($margin >= 20 ? 'warn' : 'down') }}">
                                        {{ $margin }}%
                                    </x-badge>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty title="Belum ada produk F&amp;B" icon="cup" colspan="5" />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

</x-layouts.app>
