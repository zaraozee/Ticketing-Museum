import '../css/app.css';
import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { registerServiceWorker, setupInstallPrompt } from './pwa';

// Service worker + prompt instal (FR-PWA-02, FR-PWA-04).
registerServiceWorker();
setupInstallPrompt();

const appName = window.document.getElementsByTagName('title')[0]?.innerText || 'Jejak';

type PageModule = { default: DefineComponent };

/*
 * Halaman dimuat secara lazy (code splitting) agar bundel awal kecil dan LCP
 * pada jaringan 4G tetap di bawah 2,5 s (NFR-02, AC-5 Fase 8). Halaman yang
 * sudah dikunjungi akan tersimpan di cache service worker.
 */
const lazyPages = import.meta.glob<PageModule>('./Pages/**/*.vue');

/*
 * Beranda dimuat statis (`eager`): halaman dengan trafik terbesar tidak boleh
 * menunggu satu perjalanan jaringan tambahan sebelum chunk-nya diunduh
 * (AC-5 Fase 8). Vite ikut meng-emit `modulepreload` untuk chunk statis ini.
 * Halaman lain tetap lazy agar bundel awal kecil.
 */
const eagerPages = import.meta.glob<PageModule>('./Pages/Welcome.vue', { eager: true });

const pages: Record<string, PageModule | (() => Promise<PageModule>)> = {
    ...lazyPages,
    ...eagerPages,
};

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const entry = pages[`./Pages/${name}.vue`];

        if (!entry) {
            throw new Error(`Page not found: ./Pages/${name}.vue`);
        }

        if (typeof entry === 'function') {
            const module = await entry();

            return module.default;
        }

        return entry.default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#2563eb',
    },
});
