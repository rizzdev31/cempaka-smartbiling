{{--
    Menu & stok — SATU tabel, kategori sebagai baris pemisah.

    Versi sebelumnya membuat satu `<table>` per kategori. Setiap tabel
    menghitung lebar kolomnya sendiri dari isinya, jadi "Harga" di kelompok
    MAKANAN tidak sejajar dengan "Harga" di MINUMAN — tiga header berulang
    di tiga posisi berbeda.

    Satu tabel dengan `table-fixed` + `<colgroup>` membuat lebarnya
    ditentukan sekali, dan seluruh kolom lurus dari atas sampai bawah.
    Header juga cukup ditulis sekali.
--}}

<div class="overflow-x-auto">
    <table class="w-full text-left text-xs table-fixed table-stacked">
        <colgroup>
            <col class="w-[34%]">
            <col class="w-[16%]">
            <col class="w-[16%]">
            <col class="w-[12%]">
            <col class="w-[22%]">
        </colgroup>

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
            @foreach ($products as $kategori => $daftar)
                {{-- Baris kategori. `colspan` penuh supaya ia tidak ikut
                     memaksa lebar kolom mana pun. --}}
                <tr class="bg-inset/50">
                    <td colspan="5" class="py-1.5 px-2 text-[11px] font-bold text-ink-soft uppercase tracking-wide">
                        {{ $kategori }}
                        <span class="font-medium normal-case tracking-normal text-ink-faint">
                            · {{ $daftar->count() }} menu
                        </span>
                    </td>
                </tr>

                @foreach ($daftar as $p)
                    @php
                        /*
                         * `stock` null berarti TIDAK DILACAK (mis. es batu),
                         * bukan nol. Menampilkan "0" akan membuat orang
                         * mengira barangnya habis.
                         */
                        $stok = match (true) {
                            $p->stock === null => ['Tidak dilacak', 'bg-inset text-ink-soft'],
                            $p->stock === 0 => ['Habis', 'bg-danger-surface text-danger'],
                            $p->stock <= 3 => ['Sisa sedikit', 'bg-warning-surface text-warning'],
                            default => ['Aman', 'bg-playing-surface text-playing'],
                        };
                    @endphp

                    <tr class="hover:bg-inset/60 transition-colors">
                        <td data-label="Nama" class="py-2.5 px-2 font-semibold text-ink truncate">
                            {{ $p->name }}
                        </td>
                        <td data-label="Harga" class="py-2.5 px-2 md:text-right text-ink">
                            <x-rupiah :value="$p->price" />
                        </td>
                        <td data-label="HPP" class="py-2.5 px-2 md:text-right text-ink-soft">
                            {{-- Boleh kosong: produk lama belum tentu punya
                                 angkanya, dan "Rp 0" akan terbaca sebagai
                                 modal nol. --}}
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
                            {{-- Dua badge bisa muncul bersamaan ("Habis" +
                                 "Dinonaktifkan"). `justify-end` + `flex-wrap`
                                 menjaganya tetap rata kanan dan turun rapi
                                 kalau kolomnya sempit. --}}
                            <div class="flex flex-wrap gap-1 md:justify-end">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold whitespace-nowrap {{ $stok[1] }}">
                                    {{ $stok[0] }}
                                </span>
                                @unless ($p->is_available)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold whitespace-nowrap bg-idle-surface text-idle">
                                        Dinonaktifkan
                                    </span>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</div>
