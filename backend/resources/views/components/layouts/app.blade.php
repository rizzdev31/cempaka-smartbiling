<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Admin' }} — {{ config('app.name') }}</title>

    {{--
        Vite, bukan CDN Tailwind seperti referensi. Referensi memakai
        `cdn.tailwindcss.com` yang mengkompilasi di browser — itu wajar untuk
        satu berkas contoh, tapi di admin nyata berarti halaman tidak bisa
        dibuka tanpa internet, dan DEC-002 menyatakan tempat ini bisa
        beroperasi tanpa internet.
    --}}
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
<body class="min-h-screen p-4 md:p-8">

<div class="w-full max-w-[1240px] mx-auto bg-shell rounded-shell p-6 md:p-8 shadow-card border border-card">

    {{-- ── Navigasi atas ──────────────────────────────────────────── --}}
    <header class="flex items-center justify-between gap-4 pb-6 border-b border-line-soft mb-8">

        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 shrink-0">
            <div class="w-10 h-10 rounded-full bg-brand-600 flex items-center justify-center text-card shadow-brand">
                <x-icon name="bolt" class="w-5 h-5" />
            </div>
            <span class="text-xl font-bold text-ink tracking-tight hidden sm:block">
                {{ config('app.name') }}
            </span>
        </a>

        {{--
            Nav pill, aktif = pill pekat. Diambil dari referensi.

            Hanya Dashboard yang punya halaman. Sisanya ditandai `aria-disabled`
            dan diredupkan, BUKAN disembunyikan: owner perlu melihat apa yang
            akan ada. Tapi juga tidak boleh bisa diklik ke halaman kosong —
            tautan yang mati lebih membingungkan daripada tombol yang jelas
            belum aktif.
        --}}
        <nav aria-label="Navigasi utama" class="hidden lg:flex items-center gap-1">
            <a href="{{ route('admin.dashboard') }}"
               aria-current="page"
               class="px-5 py-2 text-sm font-semibold rounded-full bg-pitch text-card transition-colors">
                Dashboard
            </a>
            @foreach (['Sesi', 'F&B', 'Harga & Paket', 'Laporan'] as $soon)
                <span aria-disabled="true" title="Belum dibuat"
                      class="px-4 py-2 text-sm font-medium text-ink-faint cursor-not-allowed select-none">
                    {{ $soon }}
                </span>
            @endforeach
        </nav>

        <div class="flex items-center gap-3">
            <button type="button" aria-label="Notifikasi"
                    class="w-10 h-10 rounded-full border border-line flex items-center justify-center text-ink-soft hover:bg-inset transition-colors">
                <x-icon name="bell" />
            </button>
            <button type="button" aria-label="Pengaturan"
                    class="w-10 h-10 rounded-full border border-line flex items-center justify-center text-ink-soft hover:bg-inset transition-colors">
                <x-icon name="cog" />
            </button>

            <div class="flex items-center gap-2 pl-1">
                {{--
                    Avatar inisial, bukan foto. Referensi memuat foto dari
                    googleusercontent — alamat luar yang tidak akan terjangkau
                    di jaringan lokal, dan menghasilkan ikon rusak.
                --}}
                <div class="w-10 h-10 rounded-full bg-inset border border-line flex items-center justify-center text-sm font-bold text-ink-soft">
                    OW
                </div>
                <div class="hidden xl:block leading-tight">
                    <div class="text-xs font-bold text-ink">Owner</div>
                    <div class="text-[11px] text-ink-soft">belum login</div>
                </div>
            </div>
        </div>
    </header>

    {{ $slot }}
</div>

</body>
</html>
