@props(['value', 'short' => false])

{{--
    Uang. SELALU lewat komponen ini, jangan `number_format` di view.

    Dua alasan. Pertama, `tabular` wajib ikut — tanpa angka bertabulasi,
    kolom Total di tabel bergoyang kiri-kanan antar baris karena `1` lebih
    sempit dari `8`. Kedua, formatnya Indonesia: titik ribuan, bukan koma.
    Satu baris yang lupa akan menampilkan "Rp 1,285,000" di tengah kolom
    yang lain menulis "Rp 1.285.000".

    Nilainya integer rupiah tanpa desimal (CLAUDE.md §4).
--}}

@php
    $v = (int) $value;

    if ($short && abs($v) >= 1_000_000) {
        $text = 'Rp '.rtrim(rtrim(number_format($v / 1_000_000, 1, ',', '.'), '0'), ',').' jt';
    } elseif ($short && abs($v) >= 1_000) {
        $text = 'Rp '.rtrim(rtrim(number_format($v / 1_000, 0, ',', '.'), '0'), ',').'rb';
    } else {
        $text = 'Rp '.number_format($v, 0, ',', '.');
    }
@endphp

<span {{ $attributes->merge(['class' => 'tabular']) }}>{{ $text }}</span>
