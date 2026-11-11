<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Account/Profile', [
            'status' => session('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $emailChanged = $user->email !== $request->email;

        $user->fill($request->validated());

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        ActivityLogger::log('user.profile_update', $user, [
            'email_changed' => $emailChanged,
        ]);

        return back()->with('status', 'Profil berhasil diperbarui.');
    }

    /**
     * Update the user's password.
     */
    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        ActivityLogger::log('user.password_change', $user);

        return back()->with('status', 'Kata sandi berhasil diperbarui.');
    }
}
