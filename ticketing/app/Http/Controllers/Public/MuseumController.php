<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Museum;
use App\Models\MuseumCategory;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MuseumController extends Controller
{
    /**
     * Daftar museum published dengan pencarian dan filter (FR-MUS-01, FR-MUS-02).
     */
    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->toString());
        $category = $request->string('category')->toString();
        $city = $request->string('city')->toString();

        $museums = Museum::query()
            ->published()
            ->with(['category:id,name', 'images'])
            ->when($search !== '', function ($query) use ($search): void {
                // whereLike(caseSensitive: false) memakai ILIKE di PostgreSQL dan
                // LIKE di SQLite/MySQL, sehingga pencarian konsisten lintas driver.
                $query->where(function ($inner) use ($search): void {
                    $inner->whereLike('name', "%{$search}%", caseSensitive: false)
                        ->orWhereLike('city', "%{$search}%", caseSensitive: false);
                });
            })
            ->when($category !== '', fn ($query) => $query->whereHas(
                'category',
                fn ($inner) => $inner->where('slug', $category),
            ))
            ->when($city !== '', fn ($query) => $query->where('city', $city))
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Museum $museum): array => [
                'slug' => $museum->slug,
                'name' => $museum->name,
                'city' => $museum->city,
                'province' => $museum->province,
                'category' => $museum->category?->name,
                'excerpt' => str($museum->description ?? '')->limit(120)->toString(),
                'cover_url' => $museum->images->firstWhere('is_cover', true)?->url()
                    ?? $museum->images->first()?->url(),
                'booking_mode' => $museum->booking_mode->label(),
            ]);

        return Inertia::render('Public/Museums/Index', [
            'museums' => $museums,
            'filters' => ['q' => $search, 'category' => $category, 'city' => $city],
            'categories' => MuseumCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->map(fn (MuseumCategory $item): array => ['label' => $item->name, 'value' => $item->slug])
                ->all(),
            'cities' => Museum::query()
                ->published()
                ->whereNotNull('city')
                ->distinct()
                ->orderBy('city')
                ->pluck('city')
                ->all(),
        ]);
    }

    /**
     * Detail museum (FR-MUS-03, FR-MUS-04).
     * Museum non-published menghasilkan 404 (BR-01).
     */
    public function show(Museum $museum): Response
    {
        abort_unless($museum->status->isPubliclyVisible(), 404);

        $museum->load(['category:id,name', 'images', 'facilities', 'operatingHours']);

        $directionsUrl = $museum->hasCoordinates()
            ? sprintf('https://www.google.com/maps/dir/?api=1&destination=%s,%s', $museum->latitude, $museum->longitude)
            : null;

        $embedUrl = $museum->hasCoordinates() && config('services.google_maps.key')
            ? sprintf(
                'https://www.google.com/maps/embed/v1/place?key=%s&q=%s,%s&zoom=15',
                config('services.google_maps.key'),
                $museum->latitude,
                $museum->longitude,
            )
            : null;

        return Inertia::render('Public/Museums/Show', [
            'museum' => [
                'slug' => $museum->slug,
                'name' => $museum->name,
                'description' => $museum->description,
                'category' => $museum->category?->name,
                'address' => $museum->address,
                'city' => $museum->city,
                'province' => $museum->province,
                'latitude' => $museum->latitude,
                'longitude' => $museum->longitude,
                'booking_mode' => $museum->booking_mode->label(),
                'max_tickets_per_booking' => $museum->max_tickets_per_booking,
                'youtube_video_id' => $museum->youtube_video_id,
                'images' => $museum->images->map(fn ($image): array => [
                    'url' => $image->url(),
                    'caption' => $image->caption,
                ])->values(),
                'facilities' => $museum->facilities->map(fn ($facility): array => [
                    'name' => $facility->name,
                    'icon' => $facility->icon,
                ])->values(),
                'operating_hours' => $museum->operatingHours->map(fn ($hour): array => [
                    'day' => $hour->dayLabel(),
                    'open_time' => $hour->open_time ? substr((string) $hour->open_time, 0, 5) : null,
                    'close_time' => $hour->close_time ? substr((string) $hour->close_time, 0, 5) : null,
                    'is_closed' => $hour->is_closed,
                ])->values(),
            ],
            // Fase 4: harga "mulai dari" dan daftar jenis tiket aktif (FR-ORG-06).
            'ticketTypes' => $museum->ticketTypes()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (TicketType $ticketType): array => [
                    'id' => $ticketType->id,
                    'name' => $ticketType->name,
                    'description' => $ticketType->description,
                    'price' => $ticketType->price,
                    'price_label' => $ticketType->formattedPrice(),
                ])
                ->values(),
            'lowestPrice' => $museum->ticketTypes()->active()->min('price'),
            'directionsUrl' => $directionsUrl,
            'embedUrl' => $embedUrl,
        ]);
    }
}
