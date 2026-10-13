@props(['name', 'value' => null, 'label' => null, 'required' => false])

{{--
    Input rupiah.

    `inputmode="numeric"` memunculkan papan angka di tablet — tempat halaman
    ini paling sering dibuka. `type="number"` sengaja TIDAK dipakai: di
    sebagian browser ia menerima `1e5` dan roda mouse mengubah nilainya tanpa
    disadari saat menggulir halaman, dan ini kolom uang.

    Nilainya integer rupiah tanpa desimal (CLAUDE.md §4).
--}}

<label class="block">
    @if ($label)
        <span class="block text-[11px] font-semibold text-ink-soft mb-1">{{ $label }}</span>
    @endif
    <span class="relative block">
        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[11px] text-ink-soft pointer-events-none">Rp</span>
        <input type="text" inputmode="numeric" pattern="[0-9]*"
               name="{{ $name }}" value="{{ old($name, $value) }}"
               @if ($required) required @endif
               {{ $attributes->merge(['class' => 'w-full pl-9 pr-3 py-2 bg-inset border border-transparent rounded-chip
                                                 text-xs text-ink tabular text-right
                                                 focus:outline-none focus:bg-card focus:border-brand-600 transition-all']) }}>
    </span>
</label>
