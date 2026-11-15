<?php

/*
 * Identitas merek yang tampil ke manusia — DEC-017, persiapan DEC-018.
 *
 * Cermin dari `operator-app/lib/core/brand.dart`. Alasan file ini ada sama
 * dengan alasan file itu ada: tidak boleh ada nama merek yang tertulis di
 * dalam Blade. Saat aplikasi ini dipasang untuk rental lain (OD-012),
 * yang berubah satu berkas, bukan berburu string di belasan halaman.
 *
 * JANGAN memakai `config('app.name')` untuk merek. Nilai itu milik
 * instalasi Laravel — ia ikut muncul di log, di antrian, dan di field
 * `instance` pada `/api/v1/health`. Merek rental adalah hal yang berbeda,
 * dan menyamakan keduanya membuat mengganti satu berarti mengganti yang
 * lain tanpa sengaja.
 */

return [

    // Nama rental. Inilah yang dilihat owner dan operator.
    'name' => env('BRAND_NAME', 'Amor Gaming Space'),

    /*
     * Dua baris pada lencana merek. Dipisah karena logonya memang
     * menuliskannya dua baris — "AMOR" besar, "GAMING SPACE" kecil
     * berjarak lebar di bawahnya.
     */
    'mark_title' => env('BRAND_MARK_TITLE', 'Amor'),
    'mark_subtitle' => env('BRAND_MARK_SUBTITLE', 'Gaming Space'),

    // Atribusi pembuat sistem. Bukan nama rental.
    'powered_by' => 'Cempaka Smart Billing',

    'tagline' => 'Billing & Operasional Rental',

    /*
     * Berkas logo ada di `public/images/`, bukan di `resources/` yang
     * diproses Vite.
     *
     * Disengaja: aset Vite menuntut `npm run build` berhasil lebih dulu,
     * dan build itu pernah gagal total di mesin tanpa internet (lihat
     * komentar di `vite.config.js`). Logo yang hilang karena build gagal
     * membuat halaman terlihat rusak, padahal yang salah cuma asetnya.
     * Dari `public/` ia selalu ada, build atau tidak.
     *
     * `mark` dipakai di mana-mana, bukan `full`. Tulisan "AMOR GAMING
     * SPACE" pada logo penuh berwarna putih-perak — di atas latar terang
     * Admin Web ia nyaris tidak terbaca. Lencananya memakai gambar
     * gamepad saja, lalu namanya ditulis dengan CSS supaya kontrasnya
     * mengikuti token tema.
     */
    'logo' => [
        'mark' => 'images/logo-amor-mark.png',
        'full' => 'images/logo-amor-full.png',
        'icon' => 'images/icon-amor-512.png',
        'icon_small' => 'images/icon-amor-32.png',
        'apple_touch' => 'images/apple-touch-icon.png',
    ],

];
