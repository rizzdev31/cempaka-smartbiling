@props([
    'size' => 36,
    'showName' => true,
    'stacked' => false,
])

{{--
    Lencana merek — logo Amor Gaming Space.

    Cermin dari `BrandMark` di Flutter (`operator-app/lib/ui/widgets/
    brand_mark.dart`): gambar gamepad, lalu nama ditulis DUA BARIS dengan
    CSS — "Amor" besar, "GAMING SPACE" kecil berjarak lebar di bawahnya.

    Kenapa namanya tidak ikut gambar, padahal ada `logo-amor-full.png`:
    tulisan pada logo penuh berwarna putih-perak, dan Admin Web berlatar
    terang. Di sana namanya nyaris hilang. Ditulis dengan CSS, kontrasnya
    mengikuti token tema dan ikut benar kalau temanya berubah.

    Tingginya ditentukan, lebarnya mengikuti bentuk asli logo. Logo merek
    jarang persegi; memaksanya ke kotak membuatnya tampil lebih kecil dari
    ruang yang sebenarnya tersedia.

    `stacked` menumpuk gambar di atas teks dan memusatkannya — untuk layar
    login. Default-nya berdampingan, untuk navbar.
--}}

@php
    $logo = asset(config('brand.logo.mark'));
    $nama = config('brand.name');
@endphp

<span {{ $attributes->merge([
    'class' => $stacked
        ? 'flex flex-col items-center gap-3 min-w-0'
        : 'flex items-center gap-2.5 min-w-0',
]) }}>
    {{--
        `alt` sengaja kosong saat nama ikut tampil: pembaca layar akan
        membacakan nama yang sama dua kali kalau gambarnya juga diberi
        teks alternatif. Saat logonya berdiri sendiri, barulah ia yang
        membawa nama.
    --}}
    <img src="{{ $logo }}"
         alt="{{ $showName ? '' : $nama }}"
         @if ($showName) aria-hidden="true" @endif
         height="{{ $size }}"
         style="height: {{ $size }}px"
         class="w-auto shrink-0 select-none"
         draggable="false">

    @if ($showName)
        <span class="flex flex-col min-w-0 {{ $stacked ? 'items-center' : '' }}">
            <span class="text-base font-bold text-ink tracking-tight leading-none truncate">
                {{ config('brand.mark_title') }}
            </span>
            {{--
                Jarak huruf lebar meniru kunci pada logo aslinya, dan
                membuat baris kedua terbaca sebagai bagian dari merek —
                bukan sebagai keterangan yang menggantung di bawahnya.
            --}}
            <span class="text-[10px] font-semibold text-brand-600 uppercase tracking-[0.18em] leading-tight mt-0.5 truncate">
                {{ config('brand.mark_subtitle') }}
            </span>
        </span>
    @endif
</span>
