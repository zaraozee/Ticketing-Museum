/// <reference lib="webworker" />

/**
 * Service worker aplikasi (FR-PWA-02, SYSTEM_DESIGN §15.2).
 *
 * Strategi cache:
 * - App shell (JS/CSS hasil build ber-hash)   → precache
 * - Gambar museum                             → CacheFirst + batas umur/jumlah
 * - Halaman publik (navigasi HTML)            → StaleWhileRevalidate
 * - Checkout/pembayaran/status/scan/auth      → NetworkOnly (tidak pernah dari cache)
 * - Halaman fallback offline                  → dicache saat install
 *
 * Pembaruan versi memakai prompt: `skipWaiting()` **tidak** dipanggil otomatis
 * agar alur checkout yang sedang berjalan tidak terputus (AC-4).
 */
import { clientsClaim } from 'workbox-core';
import { ExpirationPlugin } from 'workbox-expiration';
import {
    cleanupOutdatedCaches,
    matchPrecache,
    precacheAndRoute,
} from 'workbox-precaching';
import type { PrecacheEntry } from 'workbox-precaching';
import { registerRoute } from 'workbox-routing';
import { CacheFirst, NetworkOnly, StaleWhileRevalidate } from 'workbox-strategies';

declare const self: ServiceWorkerGlobalScope & {
    __WB_MANIFEST: Array<PrecacheEntry | string>;
};

const OFFLINE_CACHE = 'musentix-offline-v1';
const OFFLINE_URL = '/offline';
const OFFLINE_TICKETS_URL = '/offline/tickets';

/**
 * Aset build disajikan dari `/build/`, sedangkan service worker ini disajikan
 * dari root (`/sw.js`) agar `scope` mencakup seluruh aplikasi. Entri manifest
 * Workbox berisi path relatif (`assets/app-xxx.js`), jadi prefix `/build/`
 * harus ditambahkan eksplisit — kalau tidak, precache akan 404.
 */
const BUILD_PREFIX = '/build/';

const precacheEntries = self.__WB_MANIFEST.map((entry) => {
    const url = typeof entry === 'string' ? entry : entry.url;

    return {
        ...(typeof entry === 'string' ? { revision: null } : entry),
        url: new URL(url, self.location.origin + BUILD_PREFIX).pathname,
    };
});

precacheAndRoute(precacheEntries);
cleanupOutdatedCaches();
clientsClaim();

/**
 * Jalur yang TIDAK BOLEH pernah dilayani dari cache (SYSTEM_DESIGN §15.2).
 * Daftar ini dijaga tetap sinkron dengan `config('ticketing.pwa.network_only_paths')`
 * oleh `PwaTest` (kontrak build).
 */
const NETWORK_ONLY_PREFIXES = [
    '/bookings',
    '/tickets',
    '/organizer',
    '/admin',
    '/api/',
    '/webhooks/',
    '/login',
    '/register',
    '/password',
    '/profile',
    '/notifications',
];

function isNetworkOnly(pathname: string): boolean {
    return NETWORK_ONLY_PREFIXES.some(
        (prefix) => pathname === prefix || pathname.startsWith(prefix.endsWith('/') ? prefix : `${prefix}/`),
    );
}

/**
 * Halaman fallback yang tepat untuk sebuah navigasi yang gagal.
 *
 * Urutan pemeriksaan penting: `/offline/tickets` sendiri harus diperiksa
 * sebelum prefiks `/tickets`, karena keduanya berbagi akhiran yang sama.
 */
async function offlineFallback(pathname: string): Promise<Response> {
    const wantsTickets = pathname === OFFLINE_TICKETS_URL || pathname.startsWith('/tickets');
    const preferred = wantsTickets ? OFFLINE_TICKETS_URL : OFFLINE_URL;

    const cached =
        (await matchPrecache(preferred)) ??
        (await caches.match(preferred)) ??
        (await matchPrecache(OFFLINE_URL)) ??
        (await caches.match(OFFLINE_URL));

    if (cached) {
        return cached;
    }

    return new Response(
        '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
            '<title>Offline</title>' +
            '<body style="font-family:system-ui;padding:2rem;text-align:center">' +
            '<h1>Anda sedang offline</h1>' +
            '<p>Tiket yang pernah dibuka masih tersimpan di perangkat Anda.</p>' +
            '</body>',
        { status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } },
    );
}

// 1) Mutasi apa pun (POST/PATCH/DELETE) tidak pernah dilayani dari cache.
registerRoute(({ request }) => request.method !== 'GET', new NetworkOnly());

/**
 * 2) Navigasi ke jalur sensitif (checkout, tiket, area organizer/admin, auth).
 *
 * Respons **tidak** di-cache: permintaan selalu diteruskan ke jaringan. Yang
 * di-cache hanyalah halaman fallback statis, sehingga saat offline pengguna
 * melihat pemberitahuan (atau daftar e-ticket tersimpan) alih-alih error
 * browser. Ini menjaga AC-2 tanpa mengorbankan AC-3.
 */
registerRoute(
    ({ request, url }) => request.mode === 'navigate' && request.method === 'GET' && isNetworkOnly(url.pathname),
    async ({ request, url }) => {
        try {
            return await fetch(request);
        } catch {
            return offlineFallback(url.pathname);
        }
    },
);

// 3) Permintaan API/XHR ke jalur sensitif: NetworkOnly murni (tanpa fallback).
registerRoute(({ request, url }) => request.method === 'GET' && isNetworkOnly(url.pathname), new NetworkOnly());

// 4) Gambar museum dari storage: CacheFirst dengan batas jumlah dan umur.
registerRoute(
    ({ url, request }) => request.destination === 'image' && !url.pathname.startsWith('/icons/'),
    new CacheFirst({
        cacheName: 'musentix-museum-images-v1',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 80,
                maxAgeSeconds: 60 * 60 * 24 * 30,
                purgeOnQuotaError: true,
            }),
        ],
    }),
);

// 5) Navigasi halaman publik: StaleWhileRevalidate (data boleh sedikit usang).
//    Hanya permintaan navigasi (dokumen HTML penuh) yang di-cache; respons
//    Inertia (XHR dengan header X-Inertia) tidak pernah disimpan.
const publicPageStrategy = new StaleWhileRevalidate({
    cacheName: 'musentix-public-pages-v1',
    plugins: [
        new ExpirationPlugin({
            maxEntries: 30,
            maxAgeSeconds: 60 * 60 * 24 * 3,
        }),
    ],
});

registerRoute(
    ({ request, url }) =>
        request.mode === 'navigate' &&
        request.method === 'GET' &&
        !isNetworkOnly(url.pathname) &&
        url.pathname !== OFFLINE_URL &&
        url.pathname !== OFFLINE_TICKETS_URL,
    async (options) => {
        try {
            return await publicPageStrategy.handle(options);
        } catch {
            // Halaman publik yang belum pernah dikunjungi memang tidak ada di
            // cache; tampilkan halaman offline alih-alih error browser.
            return offlineFallback(options.url.pathname);
        }
    },
);

// 6) Fallback offline untuk navigasi lain yang gagal (FR-PWA-03).
registerRoute(
    ({ request }) => request.mode === 'navigate',
    async ({ request, url }) => {
        try {
            return await fetch(request);
        } catch {
            return offlineFallback(url.pathname);
        }
    },
);

/**
 * Cache halaman fallback saat install. Kedua halaman dibutuhkan agar pengguna
 * tetap dapat membuka e-ticket dari IndexedDB tanpa jaringan (§15.3).
 */
self.addEventListener('install', (event: ExtendableEvent) => {
    event.waitUntil(
        (async () => {
            const cache = await caches.open(OFFLINE_CACHE);

            await Promise.all(
                [OFFLINE_URL, OFFLINE_TICKETS_URL].map(async (url) => {
                    try {
                        await cache.add(new Request(url, { cache: 'reload' }));
                    } catch {
                        // Halaman offline tidak tersedia saat install; fallback
                        // inline di atas tetap dipakai bila navigasi gagal.
                    }
                }),
            );
        })(),
    );
});

self.addEventListener('activate', (event: ExtendableEvent) => {
    event.waitUntil(
        (async () => {
            for (const key of await caches.keys()) {
                // Jangan hapus cache runtime yang dikelola Workbox.
                if (key.startsWith('workbox-precache') || key.startsWith('musentix-')) {
                    continue;
                }

                await caches.delete(key);
            }
        })(),
    );
});

// Pesan dari halaman untuk menerapkan versi baru setelah pengguna setuju (AC-4).
self.addEventListener('message', (event: ExtendableMessageEvent) => {
    if (event.data?.type === 'SKIP_WAITING') {
        void self.skipWaiting();
    }
});
