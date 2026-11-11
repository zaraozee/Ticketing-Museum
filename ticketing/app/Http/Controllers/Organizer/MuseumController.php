<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\StoreMuseumRequest;
use App\Http\Requests\Organizer\UpdateMuseumRequest;
use App\Models\Facility;
use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Services\Museum\MuseumService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MuseumController extends Controller
{
    public function __construct(private readonly MuseumService $museums) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Museum::class);

        $profile = $request->user()?->organizerProfile;
        abort_if($profile === null, 403, 'Profil organizer tidak ditemukan.');

        $museums = Museum::query()
            ->ownedBy($profile->id)
            ->with(['category:id,name', 'images'])
            ->withCount('images')
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Museum $museum): array => [
                'id' => $museum->id,
                'uuid' => $museum->uuid,
                'slug' => $museum->slug,
                'name' => $museum->name,
                'city' => $museum->city,
                'status' => $museum->status->value,
                'status_label' => $museum->status->label(),
                'category' => $museum->category?->name,
                'images_count' => $museum->images_count,
                'cover_url' => $museum->images->first()?->url(),
                'rejection_note' => $museum->rejection_note,
            ]);

        return Inertia::render('Organizer/Museums/Index', [
            'museums' => $museums,
            'canCreate' => Gate::allows('create', Museum::class),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Museum::class);

        return Inertia::render('Organizer/Museums/Form', [
            'museum' => null,
            'categories' => $this->categoryOptions(),
            'facilities' => $this->facilityOptions(),
        ]);
    }

    public function store(StoreMuseumRequest $request): RedirectResponse
    {
        $profile = $request->user()?->organizerProfile;
        abort_if($profile === null, 403);

        $museum = $this->museums->create($profile, $this->payload($request));

        return redirect()
            ->route('organizer.museums.edit', $museum)
            ->with('success', 'Museum berhasil dibuat. Lengkapi foto, jam operasional, dan koordinat sebelum mengajukan verifikasi.');
    }

    public function edit(Museum $museum): Response
    {
        Gate::authorize('update', $museum);

        $museum->load(['images', 'facilities:id', 'operatingHours', 'closedDates']);

        return Inertia::render('Organizer/Museums/Form', [
            'museum' => [
                'id' => $museum->id,
                'slug' => $museum->slug,
                'name' => $museum->name,
                'category_id' => $museum->category_id,
                'description' => $museum->description,
                'address' => $museum->address,
                'city' => $museum->city,
                'province' => $museum->province,
                'latitude' => $museum->latitude,
                'longitude' => $museum->longitude,
                'timezone' => $museum->timezone,
                'booking_mode' => $museum->booking_mode->value,
                'max_tickets_per_booking' => $museum->max_tickets_per_booking,
                'entry_early_minutes' => $museum->entry_early_minutes,
                'entry_late_minutes' => $museum->entry_late_minutes,
                'youtube_video_id' => $museum->youtube_video_id,
                'status' => $museum->status->value,
                'status_label' => $museum->status->label(),
                'rejection_note' => $museum->rejection_note,
                'facility_ids' => $museum->facilities->pluck('id'),
                'operating_hours' => $museum->operatingHours->map(fn ($hour): array => [
                    'day_of_week' => $hour->day_of_week,
                    'open_time' => $hour->open_time ? substr((string) $hour->open_time, 0, 5) : null,
                    'close_time' => $hour->close_time ? substr((string) $hour->close_time, 0, 5) : null,
                    'is_closed' => $hour->is_closed,
                ])->values(),
                'images' => $museum->images->map(fn ($image): array => [
                    'id' => $image->id,
                    'url' => $image->url(),
                    'caption' => $image->caption,
                    'is_cover' => $image->is_cover,
                ])->values(),
                'closed_dates' => $museum->closedDates->map(fn ($date): array => [
                    'id' => $date->id,
                    'closed_on' => $date->closed_on->toDateString(),
                    'reason' => $date->reason,
                ])->values(),
            ],
            'categories' => $this->categoryOptions(),
            'facilities' => $this->facilityOptions(),
            'canSubmit' => $museum->status->isSubmittable(),
        ]);
    }

    public function update(UpdateMuseumRequest $request, Museum $museum): RedirectResponse
    {
        $this->museums->update($museum, $this->payload($request));

        return back()->with('success', 'Data museum berhasil diperbarui.');
    }

    public function destroy(Museum $museum): RedirectResponse
    {
        Gate::authorize('delete', $museum);

        $this->museums->delete($museum);

        return redirect()
            ->route('organizer.museums.index')
            ->with('success', 'Museum berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StoreMuseumRequest $request): array
    {
        $data = $request->validated();
        $data['youtube_video_id'] = $request->youtubeVideoId();

        return $data;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function categoryOptions(): array
    {
        return MuseumCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (MuseumCategory $category): array => [
                'value' => (string) $category->id,
                'label' => $category->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function facilityOptions(): array
    {
        return Facility::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Facility $facility): array => [
                'value' => (string) $facility->id,
                'label' => $facility->name,
            ])
            ->all();
    }
}
