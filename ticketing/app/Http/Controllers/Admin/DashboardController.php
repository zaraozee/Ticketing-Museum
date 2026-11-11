<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\OrganizerProfile;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $stats = [
            'total_users' => User::count(),
            'pending_organizers' => OrganizerProfile::where('status', 'pending')->count(),
            'approved_organizers' => OrganizerProfile::where('status', 'approved')->count(),
        ];

        $recentLogs = ActivityLog::with('actor:id,name,email')
            ->latest('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentLogs' => $recentLogs,
        ]);
    }
}
