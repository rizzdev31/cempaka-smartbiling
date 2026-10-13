@props(['icon' => 'clock', 'title', 'colspan' => null])

{{--
    Keadaan kosong.

    Harus terlihat DISENGAJA. Daftar kosong tanpa penjelasan terbaca sebagai
    rusak, dan orang akan mencari bug yang tidak ada — padahal di instalasi
    baru inilah keadaan yang benar.
--}}

@if ($colspan)
    <tr>
        <td colspan="{{ $colspan }}" class="py-10 text-center">
            <div class="flex flex-col items-center gap-2">
                <span class="text-ink-faint"><x-icon :name="$icon" class="w-6 h-6" /></span>
                <p class="text-xs font-semibold text-ink">{{ $title }}</p>
                @if (trim($slot) !== '')
                    <p class="text-[11px] text-ink-soft max-w-xs">{{ $slot }}</p>
                @endif
            </div>
        </td>
    </tr>
@else
    <div class="flex flex-col items-center gap-2 py-10 text-center">
        <span class="text-ink-faint"><x-icon :name="$icon" class="w-6 h-6" /></span>
        <p class="text-xs font-semibold text-ink">{{ $title }}</p>
        @if (trim($slot) !== '')
            <p class="text-[11px] text-ink-soft max-w-xs">{{ $slot }}</p>
        @endif
    </div>
@endif
