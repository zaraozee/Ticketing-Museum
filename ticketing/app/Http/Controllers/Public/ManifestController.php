<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Web App Manifest (FR-PWA-01).
 *
 * Manifest dihasilkan server agar `name`/`short_name` selalu mengikuti
 * `APP_NAME` (SYSTEM_DESIGN §10: nama aplikasi tidak boleh di-hardcode).
 * Disajikan pada path root supaya `scope` manifest mencakup seluruh aplikasi.
 */
class ManifestController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $appName = (string) config('app.name', 'Jejak');
        $pwa = (array) config('ticketing.pwa', []);

        $themeColor = (string) ($pwa['theme_color'] ?? '#2563eb');
        $backgroundColor = (string) ($pwa['background_color'] ?? '#f8fafc');

        $icons = [
            [
                'src' => '/icons/icon-192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => '/icons/icon-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => '/icons/maskable-192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
            [
                'src' => '/icons/maskable-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
            [
                'src' => '/icons/icon.svg',
                'sizes' => 'any',
                'type' => 'image/svg+xml',
                'purpose' => 'any',
            ],
        ];

        $shortcuts = [
            [
                'name' => 'Tiket Saya',
                'short_name' => 'Tiket',
                'url' => '/tickets',
                'icons' => [
                    ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ],
            ],
            [
                'name' => 'Scanner Gerbang',
                'short_name' => 'Scanner',
                'url' => '/organizer/scanner',
                'icons' => [
                    ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ],
            ],
        ];

        return response()->json([
            'id' => '/',
            'name' => $appName.' — Tiket Museum',
            'short_name' => $appName,
            'description' => 'Platform pemesanan tiket museum online dengan e-ticket QR.',
            'lang' => 'id',
            'dir' => 'ltr',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation' => 'portrait',
            'theme_color' => $themeColor,
            'background_color' => $backgroundColor,
            'categories' => ['travel', 'entertainment'],
            'icons' => $icons,
            'shortcuts' => $shortcuts,
        ], 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
