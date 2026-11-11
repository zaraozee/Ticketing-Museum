<?php

namespace App\Http\Controllers\Public;

use App\Enums\ContentPageType;
use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController extends Controller
{
    /**
     * Halaman bantuan: FAQ + daftar halaman kebijakan yang tayang (FR-ADM-09).
     */
    public function index(): Response
    {
        return Inertia::render('Public/ContentPages/Index', [
            'faqs' => ContentPage::query()
                ->published()
                ->where('type', ContentPageType::FAQ->value)
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(['title', 'slug', 'content'])
                ->map(fn (ContentPage $page): array => [
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'content' => $page->content,
                ])
                ->all(),
            'pages' => ContentPage::query()
                ->published()
                ->where('type', ContentPageType::PAGE->value)
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(['title', 'slug'])
                ->map(fn (ContentPage $page): array => [
                    'title' => $page->title,
                    'slug' => $page->slug,
                ])
                ->all(),
        ]);
    }

    /**
     * Detail halaman konten. Halaman draf menghasilkan 404.
     */
    public function show(ContentPage $contentPage): Response
    {
        abort_unless($contentPage->is_published, 404);

        return Inertia::render('Public/ContentPages/Show', [
            'page' => [
                'title' => $contentPage->title,
                'slug' => $contentPage->slug,
                'content' => $contentPage->content,
                'type' => $contentPage->type->label(),
            ],
        ]);
    }
}
