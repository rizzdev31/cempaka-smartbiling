@props(['title', 'open' => false, 'tone' => 'brand'])

{{--
    Panel aksi yang bisa dibuka-tutup.

    `<details>`, bukan JavaScript: tidak ada keadaan yang bisa macet, tetap
    bekerja sebelum skrip dimuat, dan browser yang mengurus keyboard serta
    pembaca layar.

    Tertutup secara bawaan. Enam form terbuka sekaligus di satu halaman
    membuat yang dicari tenggelam — dan di layar tablet, mendorong isi
    halaman turun sampai di luar pandangan.
--}}

@php
    $nada = match ($tone) {
        'danger' => 'text-danger hover:bg-danger-surface',
        'warning' => 'text-warning hover:bg-warning-surface',
        default => 'text-brand-600 hover:bg-brand-50',
    };
@endphp

<details @if ($open) open @endif
         class="group border border-line rounded-block overflow-hidden bg-card">
    <summary class="flex items-center gap-2 px-4 py-3 cursor-pointer select-none list-none
                    text-xs font-semibold transition-colors {{ $nada }}">
        <x-icon name="chevron-down" class="w-3.5 h-3.5 shrink-0 transition-transform group-open:rotate-180" />
        {{ $title }}
    </summary>

    <div class="px-4 pb-4 pt-1 border-t border-line-soft">
        {{ $slot }}
    </div>
</details>
