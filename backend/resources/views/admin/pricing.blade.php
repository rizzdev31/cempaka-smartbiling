<x-layouts.app title="Harga & Paket">

    <x-page-header title="Harga & Paket" subtitle="Tarif rental per tipe konsol dan harga F&B.">
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
        <div class="flex items-start gap-3 p-4 mb-4 rounded-block bg-inset border border-line">
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
    <div class="flex items-start gap-3 p-4 mb-4 rounded-block bg-brand-50 border border-brand-100">
        <span class="text-brand-600 shrink-0 mt-0.5"><x-icon name="clock" class="w-4 h-4" /></span>
        <p class="text-xs text-brand-600 leading-relaxed">
            Harga baru berlaku untuk <span class="font-bold">sesi berikutnya</span>.
            Sesi yang sedang berjalan tetap memakai harga saat dibuka — termasuk saat di-extend.
            Customer sudah disebutkan harganya di depan, dan harga itu tidak boleh bergerak setelah dia duduk.
        </p>
    </div>

    {{-- ── Paket rental ────────────────────────────────────────────── --}}
    @forelse ($packages as $tipe => $daftar)
        <x-card class="mb-4">
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
                            <th scope="col" class="py-2 px-2 font-medium md:text-right">Aktif</th>
                            @if ($bolehUbah)
                                <th scope="col" class="py-2 px-2 font-medium md:text-right"><span class="sr-only">Simpan</span></th>
                            @endif
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
                                @if ($bolehUbah)
                                    {{--
                                        Satu form per baris. `form` ditaruh di
                                        LUAR tabel lewat atribut `form=` —
                                        <form> tidak boleh jadi anak langsung
                                        <tr>, dan browser akan memindahkannya
                                        keluar tabel diam-diam kalau dicoba.
                                    --}}
                                    <form id="paket-{{ $p->id }}" method="POST"
                                          action="{{ route('admin.packages.update', $p) }}" class="hidden">
                                        @csrf @method('PATCH')
                                    </form>
                                @endif

                                <td data-label="Paket" class="py-2.5 px-2">
                                    @if ($bolehUbah)
                                        <x-field name="name" :value="$p->name" form="paket-{{ $p->id }}" required
                                                 class="min-w-[9rem] font-semibold" />
                                    @else
                                        <span class="font-semibold text-ink">{{ $p->name }}</span>
                                    @endif
                                </td>

                                <td data-label="Durasi" class="py-2.5 px-2 md:text-right">
                                    @if ($bolehUbah)
                                        <x-field name="duration_minutes" :value="$p->duration_minutes"
                                                 form="paket-{{ $p->id }}" required
                                                 class="w-24 text-right tabular ml-auto" />
                                    @else
                                        <span class="text-ink-soft tabular">{{ $p->duration_minutes }} menit</span>
                                    @endif
                                </td>

                                <td data-label="Harga" class="py-2.5 px-2 md:text-right">
                                    @if ($bolehUbah)
                                        <x-money-input name="price" :value="$p->price"
                                                       form="paket-{{ $p->id }}" required class="w-32 ml-auto" />
                                    @else
                                        <span class="font-bold text-ink"><x-rupiah :value="$p->price" /></span>
                                    @endif
                                </td>

                                <td data-label="Setara / jam" class="py-2.5 px-2 md:text-right text-ink-soft">
                                    <x-rupiah :value="$perJam" />
                                </td>

                                <td data-label="Aktif" class="py-2.5 px-2 md:text-right">
                                    @if ($bolehUbah)
                                        <input type="checkbox" name="is_active" value="1" form="paket-{{ $p->id }}"
                                               @checked($p->is_active)
                                               class="w-4 h-4 rounded-chip accent-brand-600">
                                    @elseif ($p->is_active)
                                        <x-badge tone="up">Aktif</x-badge>
                                    @else
                                        <x-badge tone="neutral">Nonaktif</x-badge>
                                    @endif
                                </td>

                                @if ($bolehUbah)
                                    <td data-label="Simpan" class="py-2.5 px-2 md:text-right">
                                        <button type="submit" form="paket-{{ $p->id }}"
                                                class="px-3 py-1.5 text-[11px] font-semibold rounded-full
                                                       text-card bg-brand-600 hover:bg-brand-700 transition-colors">
                                            Simpan
                                        </button>
                                    </td>
                                @endif
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

    {{-- ── Tambah paket ────────────────────────────────────────────── --}}
    @if ($bolehUbah && $stationTypes->isNotEmpty())
        <x-card class="mb-4">
            <h2 class="text-base font-bold text-ink mb-4">Tambah Paket</h2>

            <form method="POST" action="{{ route('admin.packages.store') }}"
                  class="grid grid-cols-[repeat(auto-fit,minmax(10rem,1fr))] gap-3 items-end">
                @csrf

                <label class="block">
                    <span class="block text-[11px] font-semibold text-ink-soft mb-1">Tipe konsol</span>
                    <select name="station_type_id" required
                            class="w-full px-3 py-2 bg-inset border border-transparent rounded-chip text-xs text-ink
                                   focus:outline-none focus:bg-card focus:border-brand-600 transition-all">
                        @foreach ($stationTypes as $t)
                            <option value="{{ $t->id }}" @selected(old('station_type_id') === $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </label>

                <x-field name="name" label="Nama paket" placeholder="2 Jam" required />
                <x-field name="duration_minutes" label="Durasi (menit)" placeholder="120" required class="tabular" />
                <x-money-input name="price" label="Harga" required />

                <button type="submit"
                        class="px-4 py-2 text-xs font-semibold rounded-full text-card bg-brand-600 hover:bg-brand-700 shadow-brand transition-colors">
                    Tambah
                </button>
            </form>

            {{-- `station_type_id` tidak bisa diubah setelah paket dibuat
                 (DEC-040): memindahkan paket ke tipe lain membuat sesi lama
                 seolah dijalankan di konsol berbeda. --}}
            <p class="text-[11px] text-ink-soft mt-3">
                Tipe konsol tidak bisa diubah setelah paket dibuat. Kalau salah, nonaktifkan dan buat yang baru.
            </p>
        </x-card>
    @endif

    {{-- ── Harga F&B ───────────────────────────────────────────────── --}}
    <x-card>
        <div class="flex items-start justify-between gap-3 mb-4 flex-wrap">
            <h2 class="text-base font-bold text-ink">Harga F&B</h2>
            {{--
                Pembagian izin yang berbeda dari paket, dan perlu disebut:
                "Mie Goreng habis" kejadian harian yang harus bisa ditangani
                operator sendiri, sementara harga tetap milik owner.
            --}}
            <p class="text-[11px] text-ink-soft max-w-md">
                Stok dan ketersediaan boleh diubah admin. <span class="font-semibold text-ink">Harga hanya owner</span> (DEC-020).
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs table-stacked">
                <thead>
                    <tr class="text-ink-soft border-b border-line-soft">
                        <th scope="col" class="py-2 px-2 font-medium">Nama</th>
                        <th scope="col" class="py-2 px-2 font-medium">Kategori</th>
                        <th scope="col" class="py-2 px-2 font-medium md:text-right">Harga</th>
                        <th scope="col" class="py-2 px-2 font-medium md:text-right">HPP</th>
                        <th scope="col" class="py-2 px-2 font-medium md:text-right">Stok</th>
                        <th scope="col" class="py-2 px-2 font-medium md:text-right">Dijual</th>
                        <th scope="col" class="py-2 px-2 font-medium md:text-right">Margin</th>
                        <th scope="col" class="py-2 px-2 font-medium md:text-right"><span class="sr-only">Simpan</span></th>
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
                            <form id="fnb-{{ $p->id }}" method="POST"
                                  action="{{ route('admin.fnb-products.update', $p) }}" class="hidden">
                                @csrf @method('PATCH')
                            </form>

                            <td data-label="Nama" class="py-2.5 px-2 font-semibold text-ink">{{ $p->name }}</td>
                            <td data-label="Kategori" class="py-2.5 px-2 text-ink-soft">{{ $p->category }}</td>

                            <td data-label="Harga" class="py-2.5 px-2 md:text-right">
                                <x-money-input name="price" :value="$p->price" form="fnb-{{ $p->id }}" required
                                               :disabled="! $bolehUbah" class="w-28 ml-auto disabled:opacity-60" />
                            </td>

                            <td data-label="HPP" class="py-2.5 px-2 md:text-right">
                                {{-- Boleh kosong: harga modal sering baru
                                     diketahui setelah belanja pertama. --}}
                                <x-money-input name="hpp" :value="$p->hpp" form="fnb-{{ $p->id }}"
                                               :disabled="! $bolehUbah" class="w-28 ml-auto disabled:opacity-60" />
                            </td>

                            <td data-label="Stok" class="py-2.5 px-2 md:text-right">
                                {{-- Kosong = tidak dilacak, BERBEDA dari 0
                                     yang berarti habis (API.md §8). --}}
                                <x-field name="stock" :value="$p->stock" form="fnb-{{ $p->id }}"
                                         placeholder="tidak dilacak"
                                         class="w-28 text-right tabular ml-auto" />
                            </td>

                            <td data-label="Dijual" class="py-2.5 px-2 md:text-right">
                                <input type="checkbox" name="is_available" value="1" form="fnb-{{ $p->id }}"
                                       @checked($p->is_available)
                                       class="w-4 h-4 rounded-chip accent-brand-600">
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

                            <td data-label="Simpan" class="py-2.5 px-2 md:text-right">
                                <button type="submit" form="fnb-{{ $p->id }}"
                                        class="px-3 py-1.5 text-[11px] font-semibold rounded-full
                                               text-card bg-brand-600 hover:bg-brand-700 transition-colors">
                                    Simpan
                                </button>
                            </td>
                        </tr>
                    @empty
                        <x-empty title="Belum ada produk F&B" icon="cup" colspan="8" />
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-[11px] text-ink-soft mt-4">
            HPP ikut dikunci untuk owner. Ia bukan harga yang dibayar customer, tapi ia menentukan
            margin di setiap laporan laba — dan angka yang mengubah laporan laba adalah angka yang
            paling menggoda untuk dirapikan (OD-033).
        </p>

        {{-- ── Tambah menu ─────────────────────────────────────────
             Owner saja: menambah menu berarti menetapkan harganya, dan
             membolehkan admin akan membuka jalan memutar yang persis sama
             dengan mengubah harga menu lama (DEC-020). --}}
        @if ($bolehUbah)
            <div class="mt-6 pt-6 border-t border-line-soft">
                <h3 class="text-sm font-bold text-ink mb-3">Tambah Menu</h3>

                <form method="POST" action="{{ route('admin.fnb-products.store') }}"
                      class="grid grid-cols-[repeat(auto-fit,minmax(9rem,1fr))] gap-3 items-end">
                    @csrf

                    <label class="block">
                        <span class="block text-[11px] font-semibold text-ink-soft mb-1">Kategori</span>
                        <select name="category" required
                                class="w-full px-3 py-2 bg-inset border border-transparent rounded-chip text-xs text-ink
                                       focus:outline-none focus:bg-card focus:border-brand-600 transition-all">
                            {{-- Pilihannya dibaca dari database lewat Request
                                 yang sama dengan validatornya — dropdown dan
                                 validator yang punya daftar sendiri-sendiri
                                 akan berbeda pada perubahan pertama. --}}
                            @foreach (\App\Http\Requests\Api\V1\StoreFnbProductRequest::kategori() as $k)
                                <option value="{{ $k }}" @selected(old('category') === $k)>{{ $k }}</option>
                            @endforeach
                        </select>
                    </label>

                    <x-field name="name" label="Nama menu" placeholder="Es Jeruk" required />
                    <x-money-input name="price" label="Harga jual" required />
                    <x-money-input name="hpp" label="HPP (opsional)" />
                    <x-field name="stock" label="Stok awal" placeholder="kosong = tidak dilacak" class="tabular" />

                    <button type="submit"
                            class="px-4 py-2 text-xs font-semibold rounded-full text-card bg-brand-600 hover:bg-brand-700 shadow-brand transition-colors">
                        Tambah
                    </button>
                </form>

                <p class="text-[11px] text-ink-soft mt-3">
                    Kategori baru belum bisa dibuat dari sini — menambah kategori adalah keputusan tata menu,
                    bukan kejadian harian. Untuk sekarang lewat seeder.
                </p>
            </div>
        @endif
    </x-card>

</x-layouts.app>
