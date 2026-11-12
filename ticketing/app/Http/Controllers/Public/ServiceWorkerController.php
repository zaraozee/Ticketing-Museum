<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menyajikan service worker hasil build dari root domain (FR-PWA-02).
 *
 * `vite-plugin-pwa` menulis service worker ke `public/build/sw.js`. Bila
 * didaftarkan langsung dari path itu, `scope` service worker hanya `/build/`
 * sehingga tidak dapat menangani navigasi aplikasi (PWA tidak installable).
 * Rute ini menyajikan berkas yang sama di `/sw.js` dan mengirim header
 * `Service-Worker-Allowed: /` agar scope mencakup seluruh aplikasi.
 */
class ServiceWorkerController extends Controller
{
    public function __invoke(): Response
    {
        $path = public_path('build/sw.js');

        $headers = [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (! is_file($path)) {
            // Build belum dijalankan (mis. `npm run dev`). Kembalikan service
            // worker minimal agar registrasi tidak gagal dengan error jaringan.
            return response(
                "self.addEventListener('install', () => self.skipWaiting());\n".
                "self.addEventListener('activate', () => self.clients.claim());\n",
                200,
                $headers,
            );
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            abort(500, 'Service worker tidak dapat dibaca.');
        }

        return response($contents, 200, $headers);
    }
}
