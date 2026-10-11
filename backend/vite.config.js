import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            /*
             * Di-self-host, bukan dari CDN Google seperti referensi.
             * Admin ini dibuka dari jaringan lokal yang bisa tanpa internet
             * (DEC-002); font yang gagal diunduh membuat seluruh tata letak
             * bergeser, dan penyebabnya tidak terlihat.
             *
             * Bobot 800 ikut diambil — angka metrik besar di referensi
             * memakai `font-extrabold`, dan tanpa file-nya browser akan
             * memalsukannya dengan menebalkan 700 secara sintetis.
             */
            fonts: [
                bunny('Plus Jakarta Sans', {
                    weights: [400, 500, 600, 700, 800],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
