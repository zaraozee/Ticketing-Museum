<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Museum;
use App\Models\OrganizerProfile;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $today = now()->toDateString();

        $stats = [
            'total_users' => User::count(),
            'total_organizers' => OrganizerProfile::where('status', 'approved')->count(),
            'pending_organizers' => OrganizerProfile::where('status', 'pending')->count(),
            'total_museums' => Museum::count(),
            'published_museums' => Museum::where('status', 'published')->count(),
            'today_bookings' => Booking::whereDate('created_at', $today)->count(),
            'confirmed_bookings' => Booking::where('status', BookingStatus::CONFIRMED)->count(),
            'today_revenue' => Booking::where('status', BookingStatus::CONFIRMED)
                ->whereDate('confirmed_at', $today)
                ->sum('total_amount'),
            'total_revenue' => Booking::where('status', BookingStatus::CONFIRMED)->sum('total_amount'),
            'total_tickets' => Ticket::count(),
            'pending_anomalies' => Payment::where('needs_review', true)->count(),
        ];

        $recentBookings = Booking::with(['museum:id,name', 'user:id,name'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'code' => $b->booking_code,
                'museum_name' => $b->museum->name,
                'user_name' => $b->user->name,
                'status' => $b->status->value,
                'total_amount' => $b->total_amount,
                'created_at' => $b->created_at->format('d/m/Y H:i'),
            ]);

        $recentLogs = ActivityLog::with('actor:id,name,email')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'action' => $log->action,
                'actor_name' => $log->actor?->name,
                'actor_email' => $log->actor?->email,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentBookings' => $recentBookings,
            'recentLogs' => $recentLogs,
        ]);
    }
}
