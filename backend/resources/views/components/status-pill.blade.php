@props(['status'])

{{--
    Status sesi.

    Dua aturan yang mengikat, keduanya dari UI-UX-SPEC §2:

    1. `color-not-only` — selalu titik + teks, tidak pernah warna saja. Yang
       tidak bisa membedakan merah dan hijau tetap bisa membaca barisnya.
    2. Makna warnanya WAJIB sama dengan tablet dan TV. Operator yang belajar
       "hijau = sedang bermain" di tablet tidak boleh menemukan arti lain di
       sini.

    Status yang tidak dikenal TIDAK diterjemahkan jadi "netral" diam-diam —
    ia tampil apa adanya dengan nada waspada, supaya status baru di backend
    terlihat dan tidak lolos tanpa diperhatikan.
--}}

@php
    $map = [
        'ACTIVE'          => ['Bermain',        'bg-playing-surface',  'text-playing',  'bg-playing'],
        'WARNING'         => ['Hampir habis',   'bg-warning-surface',  'text-warning',  'bg-warning'],
        'EXPIRED'         => ['Waktu habis',    'bg-danger-surface',   'text-danger',   'bg-danger'],
        'PENDING_PAYMENT' => ['Menunggu bayar', 'bg-awaiting-surface', 'text-awaiting', 'bg-awaiting'],
        'CHECKOUT'        => ['Checkout',       'bg-checkout-surface', 'text-checkout', 'bg-checkout'],
        'COMPLETED'       => ['Selesai',        'bg-inset',            'text-ink-soft', 'bg-idle'],
        'CANCELLED'       => ['Dibatalkan',     'bg-idle-surface',     'text-idle',     'bg-idle'],
    ];

    [$label, $surface, $text, $dot] = $map[$status]
        ?? [$status, 'bg-warning-surface', 'text-warning', 'bg-warning'];
@endphp

<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $surface }} {{ $text }}">
    <span class="w-1.5 h-1.5 rounded-full {{ $dot }}"></span>
    {{ $label }}
</span>
