@props(['title' => 'Admin'])

<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} — {{ config('app.name') }}</title>

    {{--
        `@fonts` WAJIB ada, dan mudah terlupakan.

        Plugin font di `vite.config.js` mengunduh serta men-self-host Plus
        Jakarta Sans, tapi `@vite` TIDAK ikut menautkan CSS-nya — berkas itu
        punya manifest sendiri (`fonts-manifest.json`) dan hanya keluar lewat
        direktif ini.

        Tanpanya halaman tetap tampil normal, hanya memakai font sistem —
        kegagalan yang nyaris tidak terlihat kecuali dibandingkan
        berdampingan. Ditangkap `WebDesignTokensTest`.
    --}}
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen p-3 sm:p-4 md:p-8">

{{--
    Bungkus luar menyusut bersama layar: padding 12px di ponsel, 32px di
    desktop. Referensi memakai `p-8` tetap — di lebar 375px itu membuang 64px
    dari 375, seperempat layar hanya untuk ruang kosong.
--}}
<div class="w-full max-w-[1240px] mx-auto bg-shell rounded-2xl sm:rounded-shell
            p-4 sm:p-6 md:p-8 shadow-card border border-card">

    @php
        /*
         * Satu daftar navigasi untuk seluruh aplikasi.
         *
         * `route` null = halamannya belum ada. Ditampilkan redup dan tidak
         * bisa diklik, BUKAN disembunyikan: owner perlu melihat apa yang akan
         * datang. Tapi tautan yang mati lebih membingungkan daripada tombol
         * yang jelas belum aktif.
         */
        $nav = [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard'],
            ['label' => 'Sesi', 'route' => 'admin.sessions'],
            ['label' => 'Station & TV', 'route' => 'admin.stations'],
            ['label' => 'F&B', 'route' => 'admin.fnb'],
            ['label' => 'Harga & Paket', 'route' => 'admin.pricing'],
            ['label' => 'Customer', 'route' => 'admin.customers'],
            ['label' => 'Shift', 'route' => 'admin.shifts'],
            ['label' => 'Audit', 'route' => 'admin.audit'],
            ['label' => 'Laporan', 'route' => null],
        ];

        $me = auth()->user();
        // Dua huruf dari nama, bukan dari username: yang dikenali orang
        // adalah namanya.
        $inisial = collect(explode(' ', trim($me?->name ?? '?')))
            ->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    @endphp

    {{-- ── Baris merek + akun ──────────────────────────────────────── --}}
    <header class="flex items-center justify-between gap-3 mb-4">

        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 shrink-0 min-w-0">
            <div class="w-10 h-10 rounded-full bg-brand-600 flex items-center justify-center text-card shadow-brand shrink-0">
                <x-icon name="bolt" class="w-5 h-5" />
            </div>
            {{-- Nama rental disembunyikan di bawah `sm`: ikon sudah cukup
                 mengenali, dan 180px itu ruang yang dibutuhkan nav. --}}
            <span class="text-lg font-bold text-ink tracking-tight hidden sm:block truncate">
                {{ config('app.name') }}
            </span>
        </a>

        <div class="flex items-center gap-2 shrink-0">
            <button type="button" aria-label="Notifikasi"
                    class="hidden sm:flex w-10 h-10 rounded-full border border-line items-center justify-center text-ink-soft hover:bg-inset transition-colors">
                <x-icon name="bell" />
            </button>

            <div class="w-10 h-10 rounded-full bg-inset border border-line flex items-center justify-center text-sm font-bold text-ink-soft shrink-0">
                {{ $inisial }}
            </div>
            <div class="hidden lg:block leading-tight mr-1">
                <div class="text-xs font-bold text-ink">{{ $me?->name }}</div>
                <div class="text-[11px] text-ink-soft">{{ $me?->role->value }}</div>
            </div>

            {{-- POST, bukan tautan. Logout lewat GET bisa dipicu prefetch
                 browser atau <img src> tanpa user menekan apa pun. --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" aria-label="Keluar"
                        class="w-10 h-10 rounded-full border border-line flex items-center justify-center text-ink-soft hover:bg-danger-surface hover:text-danger hover:border-danger/30 transition-colors">
                    <x-icon name="logout" />
                </button>
            </form>
        </div>
    </header>

    {{--
        ── Navigasi ─────────────────────────────────────────────────

        Menggeser mendatar di layar sempit, BUKAN disembunyikan.

        Versi sebelumnya memakai `hidden lg:flex`: di bawah 1024px tidak ada
        navigasi sama sekali, dan satu-satunya jalan antar halaman adalah
        tombol Back. Tablet operator justru hidup di lebar itu.

        Geser mendatar dipilih daripada menu hamburger karena tidak butuh
        JavaScript, tidak punya keadaan terbuka/tertutup yang bisa macet, dan
        halaman aktifnya tetap terlihat tanpa membuka apa pun.
    --}}
    <nav aria-label="Navigasi utama"
         class="flex items-center gap-1 overflow-x-auto pb-4 mb-6 border-b border-line-soft
                [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        @foreach ($nav as $item)
            @php $aktif = $item['route'] && request()->routeIs($item['route']); @endphp

            @if ($item['route'] === null)
                <span aria-disabled="true" title="Belum dibuat"
                      class="px-4 py-2 text-sm font-medium text-ink-faint cursor-not-allowed select-none whitespace-nowrap">
                    {{ $item['label'] }}
                </span>
            @else
                <a href="{{ route($item['route']) }}"
                   @if ($aktif) aria-current="page" @endif
                   class="px-4 py-2 text-sm rounded-full transition-colors whitespace-nowrap
                          {{ $aktif
                              ? 'font-semibold bg-pitch text-card'
                              : 'font-medium text-ink-soft hover:text-ink hover:bg-inset' }}">
                    {{ $item['label'] }}
                </a>
            @endif
        @endforeach
    </nav>

    {{ $slot }}
</div>

</body>
</html>
