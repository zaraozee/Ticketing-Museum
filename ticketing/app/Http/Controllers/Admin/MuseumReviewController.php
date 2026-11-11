<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MuseumStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReviewMuseumRequest;
use App\Models\Museum;
use App\Services\Museum\MuseumPublishService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MuseumReviewController extends Controller
{
    public function __construct(private readonly MuseumPublishService $publisher) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: MuseumStatus::PENDING_REVIEW->value;

        $museums = Museum::query()
            ->with(['category:id,name', 'organizerProfile:id,organization_name', 'images'])
            ->when(
                in_array($status, MuseumStatus::values(), true),
                fn ($query) => $query->where('status', $status),
            )
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Museum $museum): array => [
                'id' => $museum->id,
                'slug' => $museum->slug,
                'name' => $museum->name,
                'city' => $museum->city,
                'status' => $museum->status->value,
                'status_label' => $museum->status->label(),
                'category' => $museum->category?->name,
                'organizer' => $museum->organizerProfile?->organization_name,
                'submitted_at' => $museum->submitted_at?->toIso8601String(),
                'cover_url' => $museum->images->first()?->url(),
            ]);

        return Inertia::render('Admin/Museums/Index', [
            'museums' => $museums,
            'filters' => ['status' => $status],
            'statuses' => array_map(
                fn (MuseumStatus $case): array => ['value' => $case->value, 'label' => $case->label()],
                MuseumStatus::cases(),
            ),
        ]);
    }

    public function show(Museum $museum): Response
    {
        $museum->load(['category:id,name', 'organizerProfile.user:id,name,email', 'images', 'facilities', 'operatingHours', 'closedDates']);

        return Inertia::render('Admin/Museums/Show', [
            'museum' => [
                'id' => $museum->id,
                'slug' => $museum->slug,
                'name' => $museum->name,
                'description' => $museum->description,
                'status' => $museum->status->value,
                'status_label' => $museum->status->label(),
                'rejection_note' => $museum->rejection_note,
                'city' => $museum->city,
                'province' => $museum->province,
                'address' => $museum->address,
                'latitude' => $museum->latitude,
                'longitude' => $museum->longitude,
                'booking_mode' => $museum->booking_mode->label(),
                'max_tickets_per_booking' => $museum->max_tickets_per_booking,
                'category' => $museum->category?->name,
                'organizer' => $museum->organizerProfile?->organization_name,
                'organizer_email' => $museum->organizerProfile?->user?->email,
                'submitted_at' => $museum->submitted_at?->toIso8601String(),
                'images' => $museum->images->map(fn ($image): array => [
                    'id' => $image->id,
                    'url' => $image->url(),
                    'caption' => $image->caption,
                ])->values(),
                'facilities' => $museum->facilities->pluck('name')->values(),
                'operating_hours' => $museum->operatingHours->map(fn ($hour): array => [
                    'day' => $hour->dayLabel(),
                    'open_time' => $hour->open_time ? substr((string) $hour->open_time, 0, 5) : null,
                    'close_time' => $hour->close_time ? substr((string) $hour->close_time, 0, 5) : null,
                    'is_closed' => $hour->is_closed,
                ])->values(),
                'closed_dates' => $museum->closedDates->map(fn ($date): array => [
                    'closed_on' => $date->closed_on->toDateString(),
                    'reason' => $date->reason,
                ])->values(),
            ],
            'canReview' => $museum->status === MuseumStatus::PENDING_REVIEW,
            'canSuspend' => $museum->status === MuseumStatus::PUBLISHED,
            'canRestore' => $museum->status === MuseumStatus::SUSPENDED,
        ]);
    }

    public function update(ReviewMuseumRequest $request, Museum $museum): RedirectResponse
    {
        $admin = $request->user();
        abort_if($admin === null, 403);

        $decision = (string) $request->validated('decision');
        $note = (string) ($request->validated('note') ?? '');

        match ($decision) {
            'approve' => $this->publisher->approve($museum, $admin),
            'reject' => $this->publisher->reject($museum, $admin, $note),
            'suspend' => $this->publisher->suspend($museum, $admin, $note),
            'restore' => $this->publisher->restore($museum, $admin),
            default => throw ValidationException::withMessages([
                'decision' => 'Keputusan verifikasi tidak dikenal.',
            ]),
        };

        return back()->with('success', 'Keputusan verifikasi museum disimpan.');
    }
}
