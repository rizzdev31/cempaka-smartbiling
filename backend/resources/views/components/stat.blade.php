@props(['label', 'value', 'tone' => 'ink'])

{{-- Blok angka kecil di dalam kartu — bidang cekung, bukan kartu di dalam kartu. --}}

<div class="bg-inset rounded-block p-3">
    <span class="text-[11px] font-medium text-ink-soft block">{{ $label }}</span>
    <div class="flex items-center gap-1.5 mt-1 flex-wrap">
        <span class="text-lg font-bold text-{{ $tone }} tabular">{{ $value }}</span>
        {{ $slot }}
    </div>
</div>
