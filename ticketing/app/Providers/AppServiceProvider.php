<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Museum;
use App\Policies\BookingPolicy;
use App\Policies\MuseumPolicy;
use App\Services\Payment\FakeMidtransClient;
use App\Services\Payment\HttpMidtransClient;
use App\Services\Payment\MidtransClient;
use Illuminate\Support\Facades\Gate;
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
    }
}
