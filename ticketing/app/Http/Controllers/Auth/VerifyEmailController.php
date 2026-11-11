<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Audit\ActivityLogger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user && $user->hasVerifiedEmail()) {
            return redirect()->intended('/?verified=1');
        }

        if ($user && $user->markEmailAsVerified()) {
            event(new Verified($user));
            ActivityLogger::log('user.email_verified', $user);
        }

        return redirect()->intended('/?verified=1');
    }
}
