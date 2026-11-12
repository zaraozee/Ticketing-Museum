<?php

namespace App\Http\Controllers\Public;

use App\Enums\MuseumStatus;
use App\Models\ContentPage;
use App\Models\Museum;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class SitemapController
{
    public function index(): Response
    {
        /** @var Collection<int, array{loc: string, lastmod?: string|null, changefreq: string, priority: string}> $urls */
        $urls = collect([
            ['loc' => url('/'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => url('/museums'), 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => url('/bantuan'), 'changefreq' => 'weekly', 'priority' => '0.5'],
        ]);

        // Museum published
        Museum::where('status', MuseumStatus::PUBLISHED)
            ->select('slug', 'updated_at')
            ->orderBy('updated_at', 'desc')
            ->chunk(200, function ($museums) use ($urls) {
                foreach ($museums as $museum) {
                    $urls->push([
                        'loc' => url("/museums/{$museum->slug}"),
                        'lastmod' => $museum->updated_at?->toDateString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.8',
                    ]);
                }
            });

        // Content pages
        ContentPage::select('slug', 'updated_at')
            ->orderBy('updated_at', 'desc')
            ->chunk(100, function ($pages) use ($urls) {
                foreach ($pages as $page) {
                    $urls->push([
                        'loc' => url("/bantuan/{$page->slug}"),
                        'lastmod' => $page->updated_at?->toDateString(),
                        'changefreq' => 'monthly',
                        'priority' => '0.4',
                    ]);
                }
            });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $xml .= '<url>';
            $xml .= '<loc>'.htmlspecialchars($url['loc']).'</loc>';
            if (isset($url['lastmod'])) {
                $xml .= '<lastmod>'.$url['lastmod'].'</lastmod>';
            }
            $xml .= '<changefreq>'.$url['changefreq'].'</changefreq>';
            $xml .= '<priority>'.$url['priority'].'</priority>';
            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
