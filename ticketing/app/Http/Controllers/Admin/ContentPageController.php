<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentPageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentPageRequest;
use App\Models\ContentPage;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController extends Controller
{
    public function index(): Response
    {
        $pages = ContentPage::query()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(fn (ContentPage $page): array => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'type' => $page->type->value,
                'type_label' => $page->type->label(),
                'is_published' => $page->is_published,
                'sort_order' => $page->sort_order,
            ]);

        return Inertia::render('Admin/ContentPages/Index', [
            'pages' => $pages,
            'types' => array_map(
                fn (ContentPageType $type): array => ['value' => $type->value, 'label' => $type->label()],
                ContentPageType::cases(),
            ),
        ]);
    }

    public function store(StoreContentPageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);

        $page = ContentPage::create($data);

        ActivityLogger::log('content_page.created', $page, ['title' => $page->title]);

        return back()->with('success', 'Halaman konten dibuat.');
    }

    public function update(StoreContentPageRequest $request, ContentPage $contentPage): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['title']);

        $contentPage->update($data);

        ActivityLogger::log('content_page.updated', $contentPage, ['title' => $contentPage->title]);

        return back()->with('success', 'Halaman konten diperbarui.');
    }

    public function destroy(ContentPage $contentPage): RedirectResponse
    {
        $contentPage->delete();

        ActivityLogger::log('content_page.deleted', $contentPage, ['title' => $contentPage->title]);

        return back()->with('success', 'Halaman konten dihapus.');
    }
}
