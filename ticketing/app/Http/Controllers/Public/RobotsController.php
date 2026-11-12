<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * robots.txt dinamis (NFR-16).
 *
 * Arahan `Sitemap` pada robots.txt wajib berupa URL absolut, sehingga berkas
 * statis di `public/` tidak memadai (domain berubah antar-environment).
 * Berkas statis `public/robots.txt` dihapus dan digantikan rute ini; pastikan
 * web server meneruskan permintaan yang tidak menemukan berkas ke Laravel
 * (konfigurasi standar `try_files` Nginx / `.htaccess` Apache).
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /organizer',
            'Disallow: /bookings',
            'Disallow: /tickets',
            'Disallow: /notifications',
            'Disallow: /profile',
            'Disallow: /api/',
            'Disallow: /webhooks/',
            'Disallow: /offline',
            '',
            'Sitemap: '.url('/sitemap.xml'),
            '',
        ];

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
