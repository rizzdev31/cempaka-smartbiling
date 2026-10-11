@props(['tone' => 'brand', 'icon'])

{{--
    Chip ikon lingkaran 40px — penanda visual paling khas dari referensi.

    Catatan: UI-UX-SPEC §1 MELARANG ini di tablet ("Ikon di dalam lingkaran
    berwarna"). Larangan itu tetap berlaku di sana. Web adalah permukaan
    terpisah dengan spec sendiri (DEC-042) — dan di web, chip inilah yang
    membuat kartu metrik terbaca dalam sekali pandang.
--}}

@php
    $tones = [
        'brand'   => 'bg-brand-600 text-card',
        'pitch'   => 'bg-pitch text-card',
        'soft'    => 'bg-brand-500 text-card',
        'onBrand' => 'bg-card text-brand-600 shadow-sm',
    ];
@endphp

<div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 {{ $tones[$tone] ?? $tones['brand'] }}">
    <x-icon :name="$icon" class="w-4 h-4" />
</div>
