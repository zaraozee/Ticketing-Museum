<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Museum;
use App\Policies\BookingPolicy;
use App\Policies\MuseumPolicy;
use App\Services\Payment\FakeMidtransClient;
use App\Services\Payment\HttpMidtransClient;
use App\Services\Payment\MidtransClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        /*
         * D-31: seluruh logika gateway dikurung di balik interface.
         *
         * Pemilihan implementasi bergantung pada ada/tidaknya Server Key.
         * `FakeMidtransClient` hanya dipakai bila kunci kosong **dan** aplikasi
         * tidak berjalan di produksi; di produksi kunci kosong adalah kesalahan
         * konfigurasi yang harus terlihat, bukan diam-diam memakai gateway palsu.
         */
        $this->app->singleton(MidtransClient::class, function (): MidtransClient {
            $serverKey = config('services.midtrans.server_key');
            $hasServerKey = is_string($serverKey) && $serverKey !== '';

            if ($hasServerKey) {
                return new HttpMidtransClient;
            }

            if (app()->environment('production')) {
                // Tetap kembalikan klien HTTP agar kegagalan muncul sebagai
                // PaymentGatewayException yang jelas, bukan pembayaran palsu.
                return new HttpMidtransClient;
            }

            return new FakeMidtransClient;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Museum::class, MuseumPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);

        $this->configureRateLimiting();
    }

    /**
     * Rate limit per area (SYSTEM_DESIGN §16).
     *
     * Kunci pembatas memakai kombinasi IP dan identitas (email atau user id)
     * agar satu penyerang tidak dapat mengunci akun korban hanya dengan
     * membanjiri endpoint dari IP-nya sendiri. Angka mengikuti §16 dan dapat
     * disetel lewat config `security.rate_limits`.
     */
    private function configureRateLimiting(): void
    {
        $limits = config('security.rate_limits');

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute($limits['login'])
            ->by($request->ip().'|'.mb_strtolower((string) $request->input('email'))));

        RateLimiter::for('register', fn (Request $request): Limit => Limit::perMinute($limits['register'])
            ->by($request->ip()));

        RateLimiter::for('password', fn (Request $request): Limit => Limit::perMinute($limits['password'])
            ->by($request->ip().'|'.mb_strtolower((string) $request->input('email'))));

        // Checkout: dibatasi per pengguna agar satu akun tidak membanjiri
        // pembuatan booking, tetapi cukup longgar untuk pemakaian wajar.
        RateLimiter::for('booking', fn (Request $request): Limit => Limit::perMinute($limits['booking'])
            ->by((string) (auth()->id() ?? $request->ip())));

        // Scan gate: per petugas (bukan per IP) karena banyak petugas dapat
        // berbagi satu jaringan museum.
        RateLimiter::for('scan', fn (Request $request): Limit => Limit::perMinute($limits['scan'])
            ->by((string) (auth()->id() ?? $request->ip())));

        // Lookup kode manual lebih ketat: kode pendek sehingga rawan ditebak.
        RateLimiter::for('scan-manual', fn (Request $request): Limit => Limit::perMinute($limits['scan_manual'])
            ->by((string) (auth()->id() ?? $request->ip())));

        // Webhook: longgar (Midtrans dapat mengirim burst), tetapi tetap
        // dibatasi agar tidak menjadi jalan amplifikasi.
        RateLimiter::for('webhook', fn (Request $request): Limit => Limit::perMinute($limits['webhook'])
            ->by($request->ip()));
    }
}
