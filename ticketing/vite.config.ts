import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
        VitePWA({
            /*
             * Strategi `injectManifest`: service worker ditulis sendiri di
             * `resources/js/sw.ts` agar strategi cache SYSTEM_DESIGN §15.2 dapat
             * dinyatakan eksplisit (termasuk NetworkOnly untuk checkout).
             *
             * Manifest **tidak** dibuat di sini karena `name`/`short_name` harus
             * mengikuti `APP_NAME` saat runtime; lihat `ManifestController`.
             */
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.ts',
            manifest: false,
            injectRegister: false,
            registerType: 'prompt',
            injectManifest: {
                // Bundle sebagai IIFE agar dapat didaftarkan sebagai service
                // worker klasik (kompatibel dengan lebih banyak perangkat).
                rollupFormat: 'iife',
                globDirectory: 'public/build',
                globPatterns: ['**/*.{js,css,woff,woff2,ttf}'],
            },
            devOptions: {
                // Service worker hanya didaftarkan pada build produksi; dev
                // server tetap bebas cache agar perubahan langsung terlihat.
                enabled: false,
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, './resources/js'),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
