<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFacilityRequest;
use App\Models\Facility;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FacilityController extends Controller
{
    public function index(): Response
    {
        $facilities = Facility::query()
            ->withCount('museums')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Facility $facility): array => [
                'id' => $facility->id,
                'name' => $facility->name,
                'icon' => $facility->icon,
                'is_active' => $facility->is_active,
                'sort_order' => $facility->sort_order,
                'museums_count' => $facility->museums_count,
            ]);

        return Inertia::render('Admin/Facilities/Index', [
            'facilities' => $facilities,
        ]);
    }

    public function store(StoreFacilityRequest $request): RedirectResponse
    {
        $facility = Facility::create($request->validated());

        ActivityLogger::log('facility.created', $facility, ['name' => $facility->name]);

        return back()->with('success', 'Fasilitas ditambahkan.');
    }

    public function update(StoreFacilityRequest $request, Facility $facility): RedirectResponse
    {
        $facility->update($request->validated());

        ActivityLogger::log('facility.updated', $facility, ['name' => $facility->name]);

        return back()->with('success', 'Fasilitas diperbarui.');
    }

    public function destroy(Facility $facility): RedirectResponse
    {
        $facility->delete();

        ActivityLogger::log('facility.deleted', $facility, ['name' => $facility->name]);

        return back()->with('success', 'Fasilitas dihapus.');
    }
}
