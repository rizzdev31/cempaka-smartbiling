@props(['title', 'subtitle' => null])

{{--
    Judul halaman + aksi.

    `flex-col` di ponsel, `md:flex-row` di atasnya: tombol aksi yang dipaksa
    sebaris dengan judul di lebar 375px akan meremas keduanya.
--}}

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 md:gap-4 mb-6">
    <div class="min-w-0">
        <h1 class="text-xl sm:text-2xl font-extrabold text-ink tracking-tight">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-xs text-ink-soft mt-1">{{ $subtitle }}</p>
        @endif
    </div>

    @if (isset($actions))
        <div class="flex items-center gap-2 flex-wrap">{{ $actions }}</div>
    @endif
</div>
