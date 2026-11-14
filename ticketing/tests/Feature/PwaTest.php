<?php

use App\Enums\MuseumStatus;
use App\Models\Museum;

/*
|--------------------------------------------------------------------------
| PWA (Fase 8, FR-PWA-01..04)
|--------------------------------------------------------------------------
|
| Menguji kontrak yang dapat diverifikasi di sisi server: manifest, service
| worker, halaman offline, sitemap/robots, dan meta SEO. Perilaku runtime
| service worker (strategi cache saat offline) diverifikasi manual lewat
| `storage/verification/check-offline.cjs` — lihat README di folder itu.
|
*/

test('the web app manifest is served with installability fields', function (): void {
    config(['app.name' => 'MusenTix Uji']);

    $response = $this->get('/manifest.webmanifest');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/manifest+json; charset=utf-8');

    $manifest = $response->json();

    expect($manifest['name'])->toContain('MusenTix Uji')
        ->and($manifest['short_name'])->toBe('MusenTix Uji')
        ->and($manifest['start_url'])->toBe('/')
        ->and($manifest['scope'])->toBe('/')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['theme_color'])->toBe(config('ticketing.pwa.theme_color'))
        ->and($manifest['background_color'])->toBe(config('ticketing.pwa.background_color'))
        ->and($manifest['lang'])->toBe('id');
});

test('the manifest exposes 192 and 512 px icons including a maskable variant', function (): void {
    $manifest = $this->get('/manifest.webmanifest')->json();

    $icons = collect($manifest['icons']);

    $pngIcons = $icons->filter(fn (array $icon): bool => $icon['type'] === 'image/png');

    expect($pngIcons->pluck('sizes')->all())->toContain('192x192', '512x512')
        ->and($icons->where('purpose', 'maskable'))->not->toBeEmpty()
        ->and($icons->where('purpose', 'any'))->not->toBeEmpty();

    foreach ($icons as $icon) {
        expect(file_exists(public_path(ltrim($icon['src'], '/'))))
            ->toBeTrue("Ikon manifest tidak ditemukan: {$icon['src']}");
    }
});

test('the manifest includes shortcuts to my tickets and the gate scanner', function (): void {
    $manifest = $this->get('/manifest.webmanifest')->json();

    $urls = collect($manifest['shortcuts'])->pluck('url');

    expect($urls->all())->toContain('/tickets', '/organizer/scanner');
});

test('the service worker is served from the root with a root scope', function (): void {
    $response = $this->get('/sw.js');

    $response->assertOk();
    $response->assertHeader('Service-Worker-Allowed', '/');
    $response->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
    // Service worker tidak boleh di-cache browser (AC-4: pembaruan harus segera terdeteksi).
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('the built service worker never caches sensitive paths', function (): void {
    $path = public_path('build/sw.js');

    if (! is_file($path)) {
        $this->markTestSkipped('Jalankan `npm run build` sebelum menguji kontrak service worker.');
    }

    $contents = (string) file_get_contents($path);

    foreach (config('ticketing.pwa.network_only_paths') as $sensitivePath) {
        expect(str_contains($contents, $sensitivePath))
            ->toBeTrue("Service worker tidak memuat jalur NetworkOnly: {$sensitivePath}");
    }
});

test('the network only path list covers every transactional area', function (): void {
    // Kontrak dua arah: tes di atas memastikan setiap jalur di config muncul di
    // bundel SW; tes ini memastikan jalur penting tidak pernah hilang dari
    // config (kalau hilang, tes di atas akan lolos secara palsu).
    $paths = config('ticketing.pwa.network_only_paths');

    expect($paths)->toBeArray();

    foreach (['/bookings', '/tickets', '/organizer', '/admin', '/api/', '/login', '/register'] as $required) {
        expect($paths)->toContain($required);
    }
});

test('the offline fallback pages are reachable', function (): void {
    $this->get('/offline')
        ->assertOk()
        ->assertSee('offline', false);

    $this->get('/offline/tickets')
        ->assertOk()
        ->assertSee('Tidak terverifikasi', false);
});

test('the sitemap lists published museums and public pages only', function (): void {
    $published = Museum::factory()->create([
        'name' => 'Museum Batik',
        'slug' => 'museum-batik',
        'status' => MuseumStatus::PUBLISHED,
    ]);

    Museum::factory()->create([
        'name' => 'Museum Draf',
        'slug' => 'museum-draf',
        'status' => MuseumStatus::DRAFT,
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');

    $xml = $response->getContent();

    expect($xml)->toContain('<loc>'.url('/').'</loc>')
        ->and($xml)->toContain('<loc>'.url('/museums').'</loc>')
        ->and($xml)->toContain('<loc>'.url("/museums/{$published->slug}").'</loc>')
        ->and($xml)->not->toContain('museum-draf');
});

test('robots.txt points to an absolute sitemap url', function (): void {
    $response = $this->get('/robots.txt');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    expect($response->getContent())
        ->toContain('Sitemap: '.url('/sitemap.xml'))
        ->toContain('Disallow: /admin')
        ->toContain('Disallow: /organizer');
});

test('the app shell exposes pwa and seo metadata', function (): void {
    $response = $this->get('/');

    $response->assertOk();

    $html = $response->getContent();

    expect($html)
        ->toContain('rel="manifest" href="/manifest.webmanifest"')
        ->toContain('name="theme-color"')
        ->toContain('name="apple-mobile-web-app-capable"')
        ->toContain('viewport-fit=cover')
        ->toContain('data-inertia="description"')
        ->toContain('data-inertia="canonical"');
});
