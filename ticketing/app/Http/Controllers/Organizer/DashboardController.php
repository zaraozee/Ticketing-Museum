<?php

namespace App\Http\Controllers\Organizer;

use App\Enums\BookingStatus;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Museum;
use App\Models\Ticket;
use App\Models\TicketScan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user?->organizerProfile;

        if (! $profile) {
            return Inertia::render('Organizer/Dashboard', [
                'profile' => null,
                'stats' => null,
            ]);
        }

        $museumIds = Museum::where('organizer_profile_id', $profile->id)->pluck('id');

        $today = now()->toDateString();

        $stats = [
            'total_museums' => $museumIds->count(),
            'published_museums' => Museum::where('organizer_profile_id', $profile->id)->where('status', 'published')->count(),
            'today_visitors' => Ticket::whereIn('museum_id', $museumIds)
                ->whereHas('booking', fn ($q) => $q->where('visit_date_snapshot', $today))
                ->where('status', TicketStatus::USED)
                ->count(),
            'today_bookings' => Booking::whereIn('museum_id', $museumIds)
                ->whereDate('created_at', $today)
                ->count(),
            'pending_bookings' => Booking::whereIn('museum_id', $museumIds)
                ->where('status', BookingStatus::PENDING_PAYMENT)
                ->count(),
            'confirmed_bookings' => Booking::whereIn('museum_id', $museumIds)
                ->where('status', BookingStatus::CONFIRMED)
                ->count(),
            'today_revenue' => Booking::whereIn('museum_id', $museumIds)
                ->where('status', BookingStatus::CONFIRMED)
                ->whereDate('confirmed_at', $today)
                ->sum('total_amount'),
            'total_revenue' => Booking::whereIn('museum_id', $museumIds)
                ->where('status', BookingStatus::CONFIRMED)
                ->sum('total_amount'),
            'today_scans' => TicketScan::whereIn('museum_id', $museumIds)
                ->whereDate('scanned_at', $today)
                ->count(),
        ];

        // Recent bookings
        $recentBookings = Booking::whereIn('museum_id', $museumIds)
            ->with(['museum:id,name', 'user:id,name'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Booking $b): array => [
                'id' => $b->id,
                'code' => $b->booking_code,
                'museum_name' => $b->museum->name,
                'user_name' => $b->user->name,
                'status' => $b->status->value,
                'total_amount' => $b->total_amount,
                'visit_date' => $b->visit_date_snapshot->format('d/m/Y'),
                'created_at' => $b->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Organizer/Dashboard', [
            'profile' => $profile,
            'stats' => $stats,
            'recentBookings' => $recentBookings,
        ]);
    }
}
