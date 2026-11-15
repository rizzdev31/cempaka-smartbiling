<?php

/*
 * CORS — dibutuhkan sejak aplikasi operator bisa dijalankan di browser.
 *
 * Sebelum file ini ada, Laravel memakai bawaannya: `api/*` terbuka untuk
 * semua origin, method, dan header. Itu sudah cukup untuk memanggil
 * endpoint — tapi TIDAK cukup untuk membaca header jawabannya.
 *
 * ## Kenapa `exposed_headers` menentukan
 *
 * Browser hanya memberikan segelintir header response kepada JavaScript
 * (content-type, cache-control, dan beberapa lainnya). Header lain
 * **disembunyikan** kecuali server menyebutnya di
 * `Access-Control-Expose-Headers`.
 *
 * `X-Server-Time` adalah header kustom. Di Android ia terbaca biasa saja,
 * tapi di Chrome `response.headers['x-server-time']` mengembalikan null —
 * sehingga `ServerTime` tidak pernah menghitung offset dan layar
 * Pengaturan menulis "jam belum sinkron" selamanya.
 *
 * Akibatnya bukan kosmetik. DEC-003 dan CLAUDE.md §5 mewajibkan sisa waktu
 * dihitung dari `end_at` dikurangi (jam perangkat + offset). Tanpa offset,
 * client diam-diam jatuh ke jam perangkat mentah — dan laptop yang
 * jamnya meleset dua menit akan menagih customer dua menit lebih cepat.
 */

return [

    /*
     * Hanya `api/*`. Admin Web dibuka dari origin yang sama, jadi ia tidak
     * pernah melewati CORS; memperluas pola ini hanya menambah permukaan
     * tanpa ada yang membutuhkannya.
     */
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
     * Tablet dan TV memakai alamat IP yang berubah-ubah (DHCP), dan di web
     * origin-nya ikut berganti setiap port dev berubah. Membatasi daftar
     * origin di jaringan lokal seperti ini hanya menghasilkan kegagalan
     * yang sulit dibaca.
     *
     * Yang menjaga endpoint-nya tetap token Sanctum, bukan CORS — CORS
     * melindungi browser pengguna lain, bukan server. Tinjau ulang saat
     * server pusat punya domain tetap (DEC-051, Tahap 3A-2).
     */
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    /*
     * INI yang membuat jam bisa sinkron di browser.
     *
     * Kalau header baru ditambahkan ke response API nanti dan client perlu
     * membacanya, header itu HARUS ikut didaftarkan di sini — kalau tidak,
     * ia bekerja di Android dan hilang di web tanpa pesan error apa pun.
     */
    'exposed_headers' => ['X-Server-Time'],

    'max_age' => 0,

    /*
     * Tidak memakai cookie: client membawa Bearer token. `true` akan
     * melarang `allowed_origins: ['*']` dan tidak memberi manfaat apa pun
     * di sini.
     */
    'supports_credentials' => false,

];
