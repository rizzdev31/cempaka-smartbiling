@props(['variant' => 'secondary', 'as' => 'button'])

{{--
    Tombol pill. Referensi punya tiga tingkat: aksi utama berwarna, sekunder
    putih bergaris, dan tersier di bidang cekung tanpa garis.
--}}

@php
    $variants = [
        'primary'   => 'text-card bg-brand-600 hover:bg-brand-700 shadow-brand',
        'secondary' => 'text-ink bg-card border border-line hover:bg-inset shadow-card',
        'tertiary'  => 'text-ink-soft bg-inset hover:bg-track',
    ];
    $base = 'px-4 py-2 text-xs font-semibold rounded-full inline-flex items-center gap-2 transition-colors';
@endphp

<{{ $as }} @if($as === 'button') type="button" @endif
    {{ $attributes->merge(['class' => $base.' '.($variants[$variant] ?? $variants['secondary'])]) }}>
    {{ $slot }}
</{{ $as }}>
