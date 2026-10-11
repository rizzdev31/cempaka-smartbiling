@props(['name', 'label' => null, 'options' => [], 'selected' => null, 'placeholder' => null, 'required' => false])

{{--
    Dropdown. Tinggi dan gayanya disamakan dengan `<x-field>` supaya form
    yang mencampur keduanya tetap lurus — input 38px di sebelah select 42px
    terlihat seperti kesalahan cetak, dan itu yang paling cepat membuat
    halaman terasa tidak dirancang.
--}}

<label class="block">
    @if ($label)
        <span class="block text-[11px] font-semibold text-ink-soft mb-1">{{ $label }}</span>
    @endif
    <select name="{{ $name }}" @if ($required) required @endif
            {{ $attributes->merge(['class' => 'w-full px-3 py-2 bg-inset border border-transparent rounded-chip
                                               text-xs text-ink
                                               focus:outline-none focus:bg-card focus:border-brand-600 transition-all']) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $label)
            <option value="{{ $value }}" @selected((string) old($name, $selected) === (string) $value)>{{ $label }}</option>
        @endforeach
    </select>
</label>
