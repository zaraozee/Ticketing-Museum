<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\UploadMuseumImageRequest;
use App\Models\Museum;
use App\Models\MuseumImage;
use App\Services\Museum\MuseumImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Throwable;

class MuseumImageController extends Controller
{
    public function __construct(private readonly MuseumImageService $images) {}

    public function store(UploadMuseumImageRequest $request, Museum $museum): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);

        $path = $this->images->store($request->file('image'), $museum->id);

        $isCover = $request->boolean('is_cover');

        try {
            DB::transaction(function () use ($museum, $path, $request, $isCover): void {
                if ($isCover) {
                    $museum->images()->update(['is_cover' => false]);
                }

                $museum->images()->create([
                    'path' => $path,
                    'caption' => $request->input('caption'),
                    'sort_order' => (int) $museum->images()->max('sort_order') + 1,
                    'is_cover' => $isCover || $museum->images()->count() === 0,
                ]);
            });
        } catch (Throwable $exception) {
            // Jangan tinggalkan berkas yatim di disk bila penyimpanan baris gagal.
            $this->images->delete($path);

            throw $exception;
        }

        return back()->with('success', 'Foto museum berhasil diunggah.');
    }

    public function update(Request $request, Museum $museum, MuseumImage $image): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);
        $this->ensureBelongsTo($museum, $image);

        $validated = Validator::make($request->all(), [
            'caption' => ['nullable', 'string', 'max:190'],
            'is_cover' => ['boolean'],
        ])->validate();

        DB::transaction(function () use ($museum, $image, $validated): void {
            if (($validated['is_cover'] ?? false) === true) {
                $museum->images()->update(['is_cover' => false]);
            }

            $image->update([
                'caption' => $validated['caption'] ?? $image->caption,
                'is_cover' => (bool) ($validated['is_cover'] ?? $image->is_cover),
            ]);
        });

        return back()->with('success', 'Foto museum diperbarui.');
    }

    public function destroy(Museum $museum, MuseumImage $image): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);
        $this->ensureBelongsTo($museum, $image);

        $wasCover = $image->is_cover;

        $this->images->delete($image->path);
        $image->delete();

        if ($wasCover) {
            $next = $museum->images()->orderBy('sort_order')->first();
            $next?->update(['is_cover' => true]);
        }

        return back()->with('success', 'Foto museum dihapus.');
    }

    private function ensureBelongsTo(Museum $museum, MuseumImage $image): void
    {
        abort_unless((int) $image->museum_id === (int) $museum->id, 404);
    }
}
