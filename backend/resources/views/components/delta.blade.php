@props(['now', 'before', 'unit' => '%', 'invert' => false, 'tone' => null])

{{--
    Badge perubahan terhadap kemarin.

    ## Aturan yang paling penting: diam kalau tidak bisa membandingkan

    Kalau angka kemarin NOL, komponen ini tidak menampilkan apa pun. Bukan
    "↑ 0%", bukan "↑ ∞", bukan "—".

    Persentase perubahan dari nol tidak punya arti, tapi badge-nya tetap
    terlihat meyakinkan — dan itulah masalahnya. Owner yang melihat "↑ 100%"
    pada hari pertama akan mengira ada pertumbuhan, padahal yang terjadi
    hanya "kemarin kosong".

    `unit` boleh diisi apa pun (`%`, `m`, ` sesi`). `invert` dipakai untuk
    angka yang turunnya justru bagus.
--}}

@php
    $now = (float) $now;
    $before = (float) $before;

    $tampil = $before > 0;
    $selisih = $tampil
        ? ($unit === '%' ? round(($now - $before) / $before * 100, 1) : round($now - $before, 1))
        : 0;

    $naik = $selisih > 0;

    // `tone` boleh dipaksa — di atas kartu sorot berlatar teal, hijau dan
    // merah tidak punya kontras yang cukup.
    $tone ??= match (true) {
        abs($selisih) < 0.05 => 'neutral',
        $naik === ! $invert => 'up',
        default => 'down',
    };

    // Panah selalu ikut — `color-not-only` (UI-UX-SPEC §2) berlaku di sini
    // juga: arah perubahan tidak boleh hanya disampaikan lewat warna.
    $panah = abs($selisih) < 0.05 ? '•' : ($naik ? '↑' : '↓');
    $angka = number_format(abs($selisih), $unit === '%' ? 1 : 0, ',', '.');
@endphp

@if ($tampil && abs($selisih) >= 0.05)
    <x-badge :tone="$tone">{{ $panah }} {{ $angka }}{{ $unit }}</x-badge>
@endif
