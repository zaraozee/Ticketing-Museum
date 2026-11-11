<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user?->organizerProfile;

        return Inertia::render('Organizer/Dashboard', [
            'profile' => $profile,
        ]);
    }
}
