<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        return $request->user()?->hasVerifiedEmail()
            ? redirect()->intended('/')
            : Inertia::render('Auth/VerifyEmail', ['status' => session('status')]);
    }

    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->intended('/');
        }

        $request->user()?->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
