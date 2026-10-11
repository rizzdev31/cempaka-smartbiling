@props(['name', 'label' => null, 'value' => null, 'type' => 'text', 'required' => false])

<label class="block">
    @if ($label)
        <span class="block text-[11px] font-semibold text-ink-soft mb-1">{{ $label }}</span>
    @endif
    <input type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}"
           @if ($required) required @endif
           {{ $attributes->merge(['class' => 'w-full px-3 py-2 bg-inset border border-transparent rounded-chip
                                             text-xs text-ink placeholder-ink-soft
                                             focus:outline-none focus:bg-card focus:border-brand-600 transition-all']) }}>
</label>
