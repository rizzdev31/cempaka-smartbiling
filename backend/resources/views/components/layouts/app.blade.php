@props(['title' => 'Admin', 'poll' => 0])

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

{{--
    Penuh dari ujung ke ujung.

    Versi sebelumnya memakai `max-w-[1240px] mx-auto` — pola "kartu melayang"
    dari referensi. Itu wajar untuk satu halaman contoh, tapi salah untuk
    alat kerja: di monitor 1920px, 680px dibiarkan kosong di kiri-kanan
    sementara tabel di tengah tetap harus digeser.

    Lebarnya sekarang mengikuti layar; yang mengatur kenyamanan baca adalah
    padding yang ikut membesar, dan grid yang MENAMBAH KOLOM alih-alih
    merentangkan isinya.
--}}
<body class="min-h-screen bg-canvas flex flex-col">

@php
    /*
     * Satu daftar navigasi untuk seluruh aplikasi.
     *
     * `route` null = halamannya belum ada. Ditampilkan redup dan tidak bisa
     * diklik, BUKAN disembunyikan: owner perlu melihat apa yang akan datang.
     * Tapi tautan yang mati lebih membingungkan daripada tombol yang jelas
     * belum aktif.
     */
    $nav = [
        ['label' => 'Dashboard', 'route' => 'admin.dashboard'],
        ['label' => 'Sesi', 'route' => 'admin.sessions'],
        ['label' => 'Station & TV', 'route' => 'admin.stations'],
        ['label' => 'F&B', 'route' => 'admin.fnb'],
        ['label' => 'Harga & Paket', 'route' => 'admin.pricing'],
        ['label' => 'Pembayaran', 'route' => 'admin.payments'],
        ['label' => 'Customer', 'route' => 'admin.customers'],
        ['label' => 'Shift', 'route' => 'admin.shifts'],
        ['label' => 'Audit', 'route' => 'admin.audit'],
        ['label' => 'Laporan', 'route' => 'admin.reports'],
    ];

    $me = auth()->user();
    // Dua huruf dari nama, bukan dari username: yang dikenali orang adalah
    // namanya.
    $inisial = collect(explode(' ', trim($me?->name ?? '?')))
        ->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp

{{--
    Header menempel di atas saat digulir.

    Tabel di halaman ini bisa 50 baris. Tanpa `sticky`, berpindah halaman
    dari baris terbawah berarti menggulir balik ke puncak lebih dulu — dan
    itu dilakukan berkali-kali sehari.
--}}
<header class="sticky top-0 z-30 bg-shell border-b border-line">
    <div class="px-4 sm:px-6 lg:px-8 2xl:px-12">

        <div class="flex items-center justify-between gap-3 h-16">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 shrink-0 min-w-0">
                <div class="w-9 h-9 rounded-full bg-brand-600 flex items-center justify-center text-card shadow-brand shrink-0">
                    <x-icon name="bolt" class="w-5 h-5" />
                </div>
                {{-- Nama rental disembunyikan di bawah `sm`: ikon sudah cukup
                     mengenali, dan 180px itu ruang yang dibutuhkan nav. --}}
                <span class="text-base font-bold text-ink tracking-tight hidden sm:block truncate">
                    {{ config('app.name') }}
                </span>
            </a>

            {{--
                Nav di baris yang sama mulai `2xl`, pindah ke baris sendiri di
                bawahnya. Memaksanya sebaris di 1024px membuat merek, sembilan
                menu, dan blok akun berebut ruang — dan yang kalah selalu
                menunya.

                Batasnya `2xl` (1536px), bukan `xl`. Diukur, bukan ditebak:
                merek 215px + sembilan menu 896px + blok akun 293px = 1428px,
                jadi di laptop 1366px barisnya meluber dan SELURUH halaman
                bisa digeser ke samping — di setiap halaman, bukan hanya yang
                ini.

                `justify-center-safe` menahan sisa kemungkinannya: kalau suatu
                saat menunya bertambah lagi, nav menggeser di dalam dirinya
                sendiri dan menu pertama tetap bisa dijangkau. `justify-center`
                biasa memotongnya di kiri tanpa ada cara menggulirnya kembali.
            --}}
            <nav aria-label="Navigasi utama"
                 class="hidden 2xl:flex items-center gap-1 flex-1 min-w-0 justify-center-safe
                        overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                @foreach ($nav as $item)
                    <x-nav-link :label="$item['label']" :route="$item['route']" />
                @endforeach
            </nav>

            <div class="flex items-center gap-2 shrink-0">
                <button type="button" aria-label="Notifikasi"
                        class="hidden sm:flex w-9 h-9 rounded-full border border-line items-center justify-center text-ink-soft hover:bg-inset transition-colors">
                    <x-icon name="bell" />
                </button>

                <div class="w-9 h-9 rounded-full bg-inset border border-line flex items-center justify-center text-sm font-bold text-ink-soft shrink-0">
                    {{ $inisial }}
                </div>
                {{--
                    Penanda penyegaran. Satu-satunya cara tahu halaman ini
                    masih hidup — tanpa jam yang maju, data lama terlihat
                    persis seperti data baru.
                --}}
                @if ($poll > 0)
                    <span data-live-status="ok"
                          class="hidden md:inline text-[11px] text-ink-soft tabular whitespace-nowrap mr-1">
                        Tiap {{ $poll }} detik
                    </span>
                @endif

                {{-- Lebarnya dibatasi: nama panjang ("Muhammad Reyhan
                     Pratama") kalau dibiarkan akan mendorong tombol keluar ke
                     luar layar, dan pemiliknya tidak akan pernah melaporkannya
                     sebagai bug tata letak. --}}
                <div class="hidden lg:block leading-tight mr-1 max-w-36 min-w-0">
                    <div class="text-xs font-bold text-ink truncate">{{ $me?->name }}</div>
                    <div class="text-[11px] text-ink-soft">{{ $me?->role->value }}</div>
                </div>

                {{-- POST, bukan tautan. Logout lewat GET bisa dipicu prefetch
                     browser atau <img src> tanpa user menekan apa pun. --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" aria-label="Keluar"
                            class="w-9 h-9 rounded-full border border-line flex items-center justify-center text-ink-soft hover:bg-danger-surface hover:text-danger hover:border-danger/30 transition-colors">
                        <x-icon name="logout" />
                    </button>
                </form>
            </div>
        </div>

        {{--
            Nav baris kedua untuk layar < 1280px.

            Menggeser mendatar, BUKAN disembunyikan. Versi pertama memakai
            `hidden lg:flex`: di bawah 1024px tidak ada navigasi sama sekali,
            dan satu-satunya jalan antar halaman adalah tombol Back. Tablet
            operator justru hidup di lebar itu.

            Geser mendatar dipilih daripada hamburger karena tidak butuh
            JavaScript, tidak punya keadaan buka/tutup yang bisa macet, dan
            halaman aktifnya tetap terlihat tanpa membuka apa pun.
        --}}
        <nav aria-label="Navigasi utama"
             class="2xl:hidden flex items-center gap-1 overflow-x-auto pb-3 -mx-4 px-4 sm:-mx-6 sm:px-6
                    [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            @foreach ($nav as $item)
                <x-nav-link :label="$item['label']" :route="$item['route']" />
            @endforeach
        </nav>
    </div>
</header>

{{--
    Pesan hasil aksi. Di LUAR `#live` supaya tidak ikut terhapus saat
    halaman menyegarkan diri beberapa detik kemudian.
--}}
@if (session('sukses') || $errors->any())
    <div class="px-4 sm:px-6 lg:px-8 2xl:px-12 pt-4">
        @if (session('sukses'))
            <div role="status"
                 class="flex items-start gap-3 p-3 rounded-block bg-playing-surface border border-playing/30">
                <span class="text-playing shrink-0 mt-0.5"><x-icon name="bolt" class="w-4 h-4" /></span>
                <p class="text-xs text-playing font-medium">{{ session('sukses') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div role="alert"
                 class="flex items-start gap-3 p-3 rounded-block bg-danger-surface border border-danger/30">
                <span class="text-danger shrink-0 mt-0.5"><x-icon name="warning" class="w-4 h-4" /></span>
                <div class="text-xs text-danger leading-relaxed">
                    @foreach ($errors->all() as $pesan)
                        <p>{{ $pesan }}</p>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endif

{{--
    `#live` adalah bagian yang ditukar saat penyegaran otomatis.

    Hanya isinya yang diganti, bukan seluruh halaman: header, nav, dan
    pesan hasil aksi tetap di tempat, dan posisi gulir tidak melompat.
--}}
<main id="live" @if ($poll > 0) data-poll="{{ $poll }}" @endif
      class="flex-1 w-full px-4 sm:px-6 lg:px-8 2xl:px-12 py-6 sm:py-8">
    {{ $slot }}
</main>

</body>
</html>
