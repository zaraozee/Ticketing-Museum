<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Museum;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user?->organizerProfile;

        $museumIds = Museum::where('organizer_profile_id', $profile->id)->pluck('id');

        $bookings = Booking::whereIn('museum_id', $museumIds)
            ->with(['museum:id,name', 'user:id,name,email'])
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->get('museum_id'), fn ($q, $mid) => $q->where('museum_id', $mid))
            ->latest()
            ->paginate(20)
            ->through(fn (Booking $b): array => [
                'id' => $b->id,
                'code' => $b->booking_code,
                'museum_name' => $b->museum->name,
                'user_name' => $b->user->name,
                'user_email' => $b->user->email,
                'status' => $b->status->value,
                'status_label' => $b->status->label(),
                'total_amount' => $b->total_amount,
                'visit_date' => $b->visit_date_snapshot->format('d/m/Y'),
                'created_at' => $b->created_at->format('d/m/Y H:i'),
            ]);

        $museums = Museum::where('organizer_profile_id', $profile->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Organizer/Bookings/Index', [
            'bookings' => $bookings,
            'museums' => $museums,
            'filters' => $request->only(['status', 'museum_id']),
        ]);
    }
}
