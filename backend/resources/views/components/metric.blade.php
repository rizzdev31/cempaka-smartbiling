@props([
    'label',
    'value',
    'icon',
    'delta' => null,
    'deltaTone' => 'up',
    'footnote' => null,
    'highlight' => false,
])

{{--
    Kartu metrik.

    Referensi menandai SATU kartu dengan gradasi sebagai yang terpenting.
    Pola itu dipertahankan dan dipakai untuk pendapatan hari ini — angka yang
    paling sering dicari owner saat membuka halaman ini.

    Gradasi di sini adalah penyimpangan sadar dari UI-UX-SPEC §1, yang
    melarangnya di tablet. Lihat DEC-042: larangan itu lahir dari tablet yang
    padat informasi, sementara di web satu kartu sorot justru yang memberi
    halaman ini titik masuk.
--}}

@if ($highlight)
    <div class="relative rounded-card p-5 flex flex-col justify-between overflow-hidden
                bg-gradient-to-br from-brand-600 via-brand-600 to-brand-700
                text-card shadow-brand">
        <div class="flex items-start justify-between gap-3">
            <span class="text-xs font-medium text-brand-100">{{ $label }}</span>
            <x-icon-chip tone="onBrand" :icon="$icon" />
        </div>
        <div class="mt-4">
            <div class="flex items-baseline gap-2 flex-wrap">
                <span class="text-3xl font-extrabold tracking-tight tabular">{{ $value }}</span>
                @if ($delta)
                    <x-badge tone="onBrand">{{ $delta }}</x-badge>
                @endif
            </div>
            @if ($footnote)
                <p class="text-[11px] text-brand-100 mt-2 font-medium">{{ $footnote }}</p>
            @endif
        </div>
    </div>
@else
    <x-card padding="p-5" class="flex flex-col justify-between">
        <div class="flex items-start justify-between gap-3">
            <span class="text-xs font-medium text-ink-soft">{{ $label }}</span>
            <x-icon-chip :tone="$deltaTone === 'down' ? 'soft' : 'pitch'" :icon="$icon" />
        </div>
        <div class="mt-4">
            <div class="flex items-baseline gap-2 flex-wrap">
                <span class="text-3xl font-extrabold text-ink tracking-tight tabular">{{ $value }}</span>
                @if ($delta)
                    <x-badge :tone="$deltaTone">{{ $delta }}</x-badge>
                @endif
            </div>
            @if ($footnote)
                <p class="text-[11px] text-ink-soft mt-2">{{ $footnote }}</p>
            @endif
        </div>
    </x-card>
@endif
