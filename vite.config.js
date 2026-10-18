import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Tiga keluarga font seperti mockup, bobot seperlunya saja (latin).
                // File woff2 diunduh dari fonts.bunny.net SAAT BUILD lalu disajikan dari server kita sendiri
                // (public/build), jadi browser pengguna tidak menghubungi layanan font pihak ketiga.
                // optimizedFallbacks: false -> tidak butuh paket tambahan "fontaine" (lebih ringan).
                bunny('Plus Jakarta Sans', { weights: [800], optimizedFallbacks: false }), // judul
                bunny('Inter', { weights: [400, 600, 700], optimizedFallbacks: false }),   // isi
                bunny('JetBrains Mono', { weights: [600], optimizedFallbacks: false }),    // nomor tiket
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
