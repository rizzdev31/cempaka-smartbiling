@props(['padding' => 'p-6'])

{{--
    Kartu putih — unit dasar tata letak referensi.

    Garis tepi DAN shadow, berbeda dari tablet yang hanya memakai garis.
    Di referensi shadow-nya nyaris tidak terlihat (alpha 0,04) dan tugasnya
    hanya mengangkat kartu sedikit dari shell, bukan membuatnya melayang.
--}}

<div {{ $attributes->merge(['class' => "bg-card rounded-card border border-line shadow-card {$padding}"]) }}>
    {{ $slot }}
</div>
