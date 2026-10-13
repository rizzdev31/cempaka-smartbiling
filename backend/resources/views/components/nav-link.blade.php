@props(['label', 'route' => null])

{{--
    Satu menu nav. Dipakai DUA KALI oleh layout — baris atas di ≥1280px,
    baris kedua di bawahnya.

    Dijadikan komponen supaya keadaan aktif, warna, dan perlakuan menu yang
    belum ada hanya ditulis sekali. Dua salinan akan melenceng pada
    perubahan pertama, dan yang melenceng adalah yang jarang dilihat.
--}}

@php $aktif = $route && request()->routeIs($route); @endphp

@if ($route === null)
    <span aria-disabled="true" title="Belum dibuat"
          class="px-3.5 py-2 text-sm font-medium text-ink-faint cursor-not-allowed select-none whitespace-nowrap">
        {{ $label }}
    </span>
@else
    <a href="{{ route($route) }}"
       @if ($aktif) aria-current="page" @endif
       class="px-3.5 py-2 text-sm rounded-full transition-colors whitespace-nowrap
              {{ $aktif
                  ? 'font-semibold bg-pitch text-card'
                  : 'font-medium text-ink-soft hover:text-ink hover:bg-inset' }}">
        {{ $label }}
    </a>
@endif
