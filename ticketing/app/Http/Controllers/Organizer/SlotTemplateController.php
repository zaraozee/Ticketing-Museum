<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\StoreSlotTemplateRequest;
use App\Models\Museum;
use App\Models\SlotTemplate;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Kelola template slot dan kuota (FR-ORG-07).
 */
class SlotTemplateController extends Controller
{
    public function store(StoreSlotTemplateRequest $request, Museum $museum): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);

        $data = $request->validated();
        $data['museum_id'] = $museum->id;
        $data['sort_order'] = $data['sort_order'] ?? ((int) $museum->slotTemplates()->max('sort_order') + 1);
        $data['is_active'] = $request->boolean('is_active', true);

        $template = SlotTemplate::create($data);

        ActivityLogger::log('slot_template.created', $template, [
            'museum_id' => $museum->id,
            'name' => $template->name,
            'capacity' => $template->capacity,
        ]);

        return back()->with('success', 'Template slot ditambahkan. Jalankan pembuatan slot untuk menerapkannya.');
    }

    public function update(StoreSlotTemplateRequest $request, Museum $museum, SlotTemplate $slotTemplate): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);
        $this->ensureBelongsTo($museum, $slotTemplate);

        $before = $slotTemplate->only(['name', 'capacity']);

        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $slotTemplate->is_active);

        $slotTemplate->update($data);

        ActivityLogger::log('slot_template.updated', $slotTemplate, [
            'museum_id' => $museum->id,
            'before' => $before,
            'after' => $slotTemplate->only(['name', 'capacity']),
        ]);

        return back()->with('success', 'Template slot diperbarui.');
    }

    public function destroy(Museum $museum, SlotTemplate $slotTemplate): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);
        $this->ensureBelongsTo($museum, $slotTemplate);

        // Soft delete agar slot yang sudah dihasilkan tetap dapat ditelusuri.
        $slotTemplate->delete();

        ActivityLogger::log('slot_template.deleted', $slotTemplate, [
            'museum_id' => $museum->id,
            'name' => $slotTemplate->name,
        ]);

        return back()->with('success', 'Template slot dihapus.');
    }

    private function ensureBelongsTo(Museum $museum, SlotTemplate $slotTemplate): void
    {
        abort_unless((int) $slotTemplate->museum_id === (int) $museum->id, 404);
    }
}
