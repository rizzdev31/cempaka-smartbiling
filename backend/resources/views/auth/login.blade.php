<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex items-center justify-center p-4">

{{--
    Halaman login TIDAK memakai `<x-layouts.app>`.

    Layout itu berisi nav, tombol notifikasi, dan avatar — semuanya menuntut
    user yang sudah login. Memaksakannya di sini berarti menaruh chrome yang
    tidak bisa dipakai di depan orang yang belum boleh memakainya.
--}}

<div class="w-full max-w-sm">

    <div class="flex flex-col items-center mb-8">
        <div class="w-14 h-14 rounded-full bg-brand-600 flex items-center justify-center text-card shadow-brand mb-4">
            <x-icon name="bolt" class="w-7 h-7" />
        </div>
        <h1 class="text-xl font-bold text-ink tracking-tight">{{ config('app.name') }}</h1>
        <p class="text-xs text-ink-soft mt-1">Admin Web</p>
    </div>

    <x-card>
        {{--
            Satu kotak error untuk semua kegagalan.

            Username salah, password salah, akun nonaktif, role ditolak, dan
            terlalu banyak percobaan semuanya masuk ke `username` — supaya
            tidak ada bentuk tampilan yang membedakan "username ini ada" dari
            "username ini tidak ada".
        --}}
        @if ($errors->any())
            <div class="flex items-start gap-3 p-3 mb-5 rounded-block bg-danger-surface border border-danger/30"
                 role="alert">
                <span class="text-danger shrink-0 mt-0.5">
                    <x-icon name="warning" class="w-4 h-4" />
                </span>
                <p class="text-xs text-danger leading-relaxed">{{ $errors->first() }}</p>
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <div>
                <label for="username" class="block text-xs font-semibold text-ink mb-1.5">
                    Username
                </label>
                <input id="username" name="username" type="text"
                       value="{{ old('username') }}"
                       required autofocus autocomplete="username"
                       class="w-full px-4 py-2.5 bg-inset border border-transparent rounded-full
                              text-sm text-ink placeholder-ink-soft
                              focus:outline-none focus:bg-card focus:border-brand-600 transition-all"
                       placeholder="owner">
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold text-ink mb-1.5">
                    Password
                </label>
                {{-- `current-password` supaya pengelola kata sandi browser
                     menawarkan yang benar, bukan membuat yang baru. --}}
                <input id="password" name="password" type="password"
                       required autocomplete="current-password"
                       class="w-full px-4 py-2.5 bg-inset border border-transparent rounded-full
                              text-sm text-ink placeholder-ink-soft
                              focus:outline-none focus:bg-card focus:border-brand-600 transition-all"
                       placeholder="••••••••">
            </div>

            <button type="submit"
                    class="w-full px-4 py-2.5 text-sm font-semibold rounded-full
                           text-card bg-brand-600 hover:bg-brand-700 shadow-brand
                           transition-colors">
                Masuk
            </button>
        </form>
    </x-card>

    <p class="text-[11px] text-ink-soft text-center mt-6 leading-relaxed">
        Operator memakai aplikasi tablet, bukan halaman ini.
    </p>
</div>

</body>
</html>
