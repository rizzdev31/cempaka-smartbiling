import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            /*
             * PENGAMBILAN FONT DIMATIKAN — 15 Nov 2026.
             *
             * Sebelumnya di sini ada `fonts: [bunny('Plus Jakarta Sans', ...)]`,
             * yang mengunduh font dari fonts.bunny.net SAAT BUILD lalu
             * men-self-host hasilnya. Niatnya benar: Admin dibuka dari
             * jaringan lokal yang bisa tanpa internet (DEC-002), jadi font
             * tidak boleh diambil dari CDN saat halaman dibuka.
             *
             * Yang terlewat: BUILD-nya sendiri jadi butuh internet. Dan
             * `public/build` ada di `.gitignore`, jadi setiap mesin — laptop
             * baru, LXC di lokasi, server mana pun — wajib menjalankan
             * `npm run build` sendiri. Di jaringan yang tidak bisa menjangkau
             * fonts.bunny.net, Admin Web tidak bisa di-build sama sekali:
             *
             *     TypeError: fetch failed ... AggregateError [ETIMEDOUT]
             *     -> ViteManifestNotFoundException, setiap halaman /admin 500
             *
             * Itu bukan kemungkinan teoretis. Pada 15 Nov 2026, TCP 443 ke
             * fonts.bunny.net gagal di IPv4 maupun IPv6 dari jaringan ini,
             * dan raw.githubusercontent.com juga. DEC-051 menuntut lokasi
             * tetap jalan tanpa internet — build yang menuntut internet
             * bertentangan langsung dengan itu.
             *
             * Sekarang: build tidak pernah menyentuh jaringan. Teksnya jatuh
             * ke `ui-sans-serif, system-ui` lewat rantai `--font-sans` di
             * `resources/css/app.css`.
             *
             * CARA MENGEMBALIKAN Plus Jakarta Sans — tanpa mengorbankan build
             * offline: taruh berkas `.woff2`-nya di `resources/fonts/`,
             * commit ke repo (OFL, boleh didistribusikan), lalu tulis
             * `@font-face` di `app.css` yang menunjuk ke berkas itu. Vite
             * akan ikut menyalin dan memberi fingerprint. Jangan kembali ke
             * pengambilan saat build.
             */
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
