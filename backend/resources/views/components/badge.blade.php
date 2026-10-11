@props(['tone' => 'neutral'])

{{--
    Badge pill untuk delta ("↑ 4,9%") dan label pendek.

    Arah perubahan TIDAK boleh disampaikan warna saja — aturan
    `color-not-only` di UI-UX-SPEC §2. Karena itu panah selalu ikut, jadi
    masih terbaca oleh yang tidak bisa membedakan merah dan hijau.
--}}

@php
    $tones = [
        'up'      => 'bg-playing-surface text-playing',
        'down'    => 'bg-danger-surface text-danger',
        'warn'    => 'bg-warning-surface text-warning',
        'brand'   => 'bg-brand-50 text-brand-600',
        'neutral' => 'bg-inset text-ink-soft',
        'onBrand' => 'bg-card/20 text-card',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold '.($tones[$tone] ?? $tones['neutral'])]) }}>
    {{ $slot }}
</span>
