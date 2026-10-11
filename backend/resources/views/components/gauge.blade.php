@props(['percent', 'label'])

{{--
    Meter setengah lingkaran.

    ## Dua hal yang disengaja berbeda dari referensi

    **1. Geometrinya nyata, bukan dipotong.** Referensi menggambar lingkaran
    PENUH lalu memutarnya 180° dan memotong separuhnya dengan container
    `overflow-hidden` yang lebih pendek dari SVG-nya. Cara itu rapuh: begitu
    tinggi container berubah sedikit saja, puncak busur terpotong dan yang
    tersisa hanya dua ujungnya. Di sini `viewBox` dipaskan ke setengah
    lingkaran, jadi tidak ada yang perlu dipotong.

    **2. Nilainya benar-benar menggerakkan meter.** Referensi memakai
    `clipPath` berisi path yang di-hardcode, sehingga jarumnya menunjuk 70,8%
    berapa pun angkanya. Di sini panjang terisi dihitung dari panjang busur.

    Busur: pusat (120, 116), r = 96, tebal 16 → melebar 8 ke luar, jadi
    kotaknya 240 × 132 dengan sedikit ruang bawah. Panjang = π × 96 ≈ 301,59.

    Track putus-putus, bagian terisi solid. Dua `stroke-dasharray` tidak bisa
    ditumpuk pada satu garis — satu untuk pola titik, satu untuk panjang
    terisi — dan yang wajib benar adalah panjangnya.
--}}

@php
    $pct = max(0, min(100, (float) $percent));
    $arc = 301.59;
    $filled = round($arc * $pct / 100, 2);
@endphp

{{--
    Angkanya di ALIRAN normal dengan margin atas negatif, bukan absolut.

    Absolut membuatnya tidak punya tinggi, sehingga ia menimpa blok di
    bawahnya — dan itu baru terlihat setelah kartu diisi, bukan saat
    komponennya ditulis sendirian.
--}}
<div class="w-full max-w-[260px] mx-auto mb-2">
    <svg viewBox="0 0 240 132" class="w-full h-auto"
         role="img" aria-label="{{ $label }}: {{ number_format($pct, 1, ',', '.') }} persen">
        <defs>
            <linearGradient id="gaugeBrand" x1="0%" y1="100%" x2="100%" y2="0%">
                <stop offset="0%" stop-color="var(--color-brand-100)" />
                <stop offset="45%" stop-color="var(--color-brand-500)" />
                <stop offset="100%" stop-color="var(--color-brand-700)" />
            </linearGradient>
        </defs>

        <path d="M 24 116 A 96 96 0 0 1 216 116" fill="none"
              stroke="var(--color-track)" stroke-width="16"
              stroke-linecap="round" stroke-dasharray="6 9" />

        <path d="M 24 116 A 96 96 0 0 1 216 116" fill="none"
              stroke="url(#gaugeBrand)" stroke-width="16" stroke-linecap="round"
              stroke-dasharray="{{ $filled }} {{ $arc }}" />
    </svg>

    <div class="-mt-10 flex flex-col items-center">
        <span class="text-3xl font-extrabold text-ink tracking-tight tabular">
            {{ number_format($pct, 1, ',', '.') }}%
        </span>
        <span class="text-[11px] font-semibold text-ink-soft mt-0.5">{{ $label }}</span>
    </div>
</div>
