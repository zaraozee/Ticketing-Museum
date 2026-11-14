<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

class SocialAuthController extends Controller
{
    /**
     * Redirect to Google provider.
     *
     * Kredensial OAuth hanya dapat dibuat di Google Cloud Console, sehingga
     * aplikasi harus gagal dengan pesan yang jelas — bukan melempar pengguna ke
     * halaman error Google yang tidak menjelaskan apa pun.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        if (! $this->hasGoogleCredentials()) {
            return redirect()->route('login')
                ->with('error', 'Masuk dengan Google belum dikonfigurasi. Silakan gunakan email dan kata sandi, atau hubungi admin.');
        }

        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        return $driver->redirect();
    }

    /**
     * Apakah Client ID dan Secret Google sudah diisi di `.env`.
     */
    private function hasGoogleCredentials(): bool
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        return is_string($clientId) && $clientId !== ''
            && is_string($clientSecret) && $clientSecret !== '';
    }

    /**
     * Handle callback from Google.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        try {
            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')
                ->with('error', 'Gagal masuk dengan Google: '.$e->getMessage());
        }

        $email = $googleUser->getEmail();
        $googleId = (string) $googleUser->getId();

        if (empty($email)) {
            return redirect()->route('login')
                ->with('error', 'Akun Google Anda tidak menyediakan email.');
        }

        // 1. Check if social account link already exists
        $socialAccount = SocialAccount::where('provider', 'google')
            ->where('provider_user_id', $googleId)
            ->first();

        if ($socialAccount) {
            $user = $socialAccount->user;
        } else {
            // 2. Prevent duplicate account: Check if user with same email exists
            $user = User::where('email', $email)->first();

            $user = DB::transaction(function () use ($user, $googleUser, $email, $googleId) {
                if (! $user) {
                    $user = User::create([
                        'name' => $googleUser->getName() ?? 'Pengunjung',
                        'email' => $email,
                        'email_verified_at' => now(),
                        'password' => null,
                    ]);

                    $user->assignRole(UserRole::USER->value);

                    ActivityLogger::log('user.register_google', $user);
                } elseif (! $user->email_verified_at) {
                    $user->markEmailAsVerified();
                }

                SocialAccount::create([
                    'user_id' => $user->id,
                    'provider' => 'google',
                    'provider_user_id' => $googleId,
                ]);

                return $user;
            });
        }

        Auth::login($user, remember: true);

        ActivityLogger::log('user.login_google', $user);

        if ($user->isGlobalAdmin()) {
            return redirect()->intended(route('admin.dashboard'));
        }

        if ($user->isOrganizer()) {
            return redirect()->intended(route('organizer.dashboard'));
        }

        return redirect()->intended(route('home'));
    }
}
