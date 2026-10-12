@props(['name', 'class' => 'w-4 h-4'])

{{--
    Satu set ikon, inline SVG.

    Inline, bukan paket ikon atau font ikon: jumlahnya sedikit, dan admin ini
    dipakai di jaringan lokal yang bisa tanpa internet (DEC-002) — ikon yang
    gagal diunduh membuat tombol jadi kotak kosong tanpa petunjuk apa pun.

    UI-UX-SPEC §1 melarang emoji sebagai ikon. Satu set saja, bergaris
    (stroke) seperti referensi, kecuali yang memang padat.
--}}

@php
    $stroke = 'fill-none stroke-current';
    $paths = [
        'bolt' => ['solid', 'M13 2L3 14h7v8l10-12h-7l0-8z'],
        'bell' => ['stroke', 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
        'cog' => ['stroke', 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z'],
        'money' => ['stroke', 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 9v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'play' => ['stroke', 'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664zM21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'tv' => ['stroke', 'M9 17.25v1.007a3 3 0 01-.879 2.122L7 21h10l-1.121-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25A2.25 2.25 0 015.25 3h13.5A2.25 2.25 0 0121 5.25z'],
        'cup' => ['stroke', 'M3 8h14m0 0a3 3 0 110 6h-1M3 8v9a3 3 0 003 3h8a3 3 0 003-3v-3M7 3v2m4-2v2'],
        'clock' => ['stroke', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        // Halaman Laporan. Ikon yang tidak ada jatuh ke `warning`, dan
        // segitiga peringatan di keadaan kosong yang normal membuat orang
        // mengira ada yang rusak.
        'chart' => ['stroke', 'M3 3v16a2 2 0 002 2h16M7 15V9m5 6V5m5 10v-4'],
        'search' => ['stroke', 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'],
        'sort' => ['stroke', 'M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12'],
        'chevron-down' => ['stroke', 'M19 9l-7 7-7-7'],
        'download' => ['stroke', 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12'],
        'filter' => ['stroke', 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4'],
        'dots' => ['solid', 'M6 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm12 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm-6 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z'],
        'warning' => ['stroke', 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'],
        'logout' => ['stroke', 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1'],
    ];

    [$kind, $d] = $paths[$name] ?? $paths['warning'];
@endphp

@if ($kind === 'solid')
    <svg {{ $attributes->merge(['class' => $class.' fill-current']) }} viewBox="0 0 24 24" aria-hidden="true">
        <path d="{{ $d }}" />
    </svg>
@else
    <svg {{ $attributes->merge(['class' => $class.' '.$stroke]) }} viewBox="0 0 24 24"
         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="{{ $d }}" />
    </svg>
@endif
