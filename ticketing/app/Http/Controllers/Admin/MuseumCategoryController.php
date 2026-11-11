<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMuseumCategoryRequest;
use App\Models\MuseumCategory;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MuseumCategoryController extends Controller
{
    public function index(): Response
    {
        $categories = MuseumCategory::query()
            ->withCount('museums')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (MuseumCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'is_active' => $category->is_active,
                'sort_order' => $category->sort_order,
                'museums_count' => $category->museums_count,
            ]);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreMuseumCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $category = MuseumCategory::create($data);

        ActivityLogger::log('category.created', $category, ['name' => $category->name]);

        return back()->with('success', 'Kategori museum ditambahkan.');
    }

    public function update(StoreMuseumCategoryRequest $request, MuseumCategory $category): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $category->update($data);

        ActivityLogger::log('category.updated', $category, ['name' => $category->name]);

        return back()->with('success', 'Kategori museum diperbarui.');
    }

    public function destroy(MuseumCategory $category): RedirectResponse
    {
        if ($category->museums()->exists()) {
            return back()->with('error', 'Kategori masih dipakai museum sehingga tidak dapat dihapus.');
        }

        $category->delete();

        ActivityLogger::log('category.deleted', $category, ['name' => $category->name]);

        return back()->with('success', 'Kategori museum dihapus.');
    }
}
