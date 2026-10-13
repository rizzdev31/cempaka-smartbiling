@props(['tone' => 'brand'])

{{--
    Tombol kirim di dalam panel aksi.

    Selebar panel (`w-full`) dan selalu di baris terakhir. Tombol yang
    melayang di kanan form sempit mudah terlewat di tablet, dan posisi yang
    berbeda-beda antar panel membuat mata harus mencarinya tiap kali.
--}}

@php
    $nada = match ($tone) {
        'danger' => 'text-card bg-danger hover:opacity-90',
        'warning' => 'text-card bg-warning hover:opacity-90',
        default => 'text-card bg-brand-600 hover:bg-brand-700',
    };
@endphp

<button type="submit"
        {{ $attributes->merge(['class' => 'w-full px-4 py-2 text-xs font-semibold rounded-full transition-colors '.$nada]) }}>
    {{ $slot }}
</button>
