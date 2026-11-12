<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan respons (SYSTEM_DESIGN §16).
 *
 * Diterapkan global agar berlaku untuk halaman Inertia, endpoint API, manifest,
 * service worker, dan halaman offline sekaligus. Nilai yang bergantung pada
 * lingkungan (HSTS, CSP ketat) diambil dari config `security` supaya lokal dan
 * produksi dapat berbeda tanpa mengubah kode.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            // Cegah MIME sniffing (mis. JS dianggap HTML).
            'X-Content-Type-Options' => 'nosniff',
            // Jangan kirim URL lengkap ke origin lain.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // Cegah framing (clickjacking). Midtrans Snap dibuka lewat
            // redirect/popup, bukan iframe, jadi SAMEORIGIN cukup.
            'X-Frame-Options' => 'SAMEORIGIN',
            // Matikan fitur yang tidak dipakai; kamera diizinkan karena
            // scanner gate memerlukannya (FR-GATE-01).
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=(self), payment=(self)',
            // Isolasi browsing context.
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];

        if ($request->secure()) {
            // HSTS hanya boleh dikirim lewat HTTPS; mengirimnya lewat HTTP
            // diabaikan browser dan menyulitkan pengembangan lokal.
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        if ($request->is('api/*')) {
            // Respons API tidak boleh dirender sebagai dokumen.
            $headers['Content-Security-Policy'] = "default-src 'none'; frame-ancestors 'none'";
        }

        foreach ($headers as $name => $value) {
            // Hormati header yang sudah diset lebih spesifik oleh controller
            // (mis. Service-Worker-Allowed pada /sw.js).
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
