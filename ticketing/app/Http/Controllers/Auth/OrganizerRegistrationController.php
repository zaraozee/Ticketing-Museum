<?php

namespace App\Http\Controllers\Auth;

use App\Enums\OrganizerStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\OrganizerRegisterRequest;
use App\Models\OrganizerProfile;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class OrganizerRegistrationController extends Controller
{
    /**
     * Display the organizer registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/RegisterOrganizer');
    }

    /**
     * Handle an incoming organizer registration request.
     */
    public function store(OrganizerRegisterRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
            ]);

            $user->assignRole(UserRole::ORGANIZER->value);

            OrganizerProfile::create([
                'user_id' => $user->id,
                'organization_name' => $request->organization_name,
                'contact_phone' => $request->contact_phone ?? $request->phone,
                'status' => OrganizerStatus::PENDING,
            ]);

            return $user;
        });

        event(new Registered($user));

        ActivityLogger::log('organizer.register', $user, [
            'organization_name' => $request->organization_name,
        ], actorId: $user->id);

        Auth::login($user);

        return redirect()->route('organizer.dashboard')
            ->with('status', 'Pendaftaran pengelola museum berhasil diajukan. Akun Anda sedang menunggu verifikasi admin.');
    }
}
