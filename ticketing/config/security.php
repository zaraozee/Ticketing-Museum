<?php

/*
|--------------------------------------------------------------------------
| Keamanan (SYSTEM_DESIGN §16)
|--------------------------------------------------------------------------
|
| Nilai di sini adalah batas atas yang wajar untuk MVP. Sesuaikan setelah
| pemantauan produksi menunjukkan pola trafik nyata; angka yang terlalu ketat
| akan memblokir pengguna sah, terlalu longgar membuka penyalahgunaan.
|
*/

return [
    /*
    |--------------------------------------------------------------------------
    | Rate limit per menit
    |--------------------------------------------------------------------------
    |
    | Dikonsumsi `AppServiceProvider::configureRateLimiting()`. Kunci pembatas
    | menggabungkan IP dengan identitas agar penyerang tidak dapat mengunci
    | akun korban dari IP-nya sendiri.
    |
    */
    'rate_limits' => [
        'login' => (int) env('RATE_LIMIT_LOGIN', 5),
        'register' => (int) env('RATE_LIMIT_REGISTER', 10),
        'password' => (int) env('RATE_LIMIT_PASSWORD', 5),
        'booking' => (int) env('RATE_LIMIT_BOOKING', 20),
        'scan' => (int) env('RATE_LIMIT_SCAN', 60),
        'scan_manual' => (int) env('RATE_LIMIT_SCAN_MANUAL', 10),
        'webhook' => (int) env('RATE_LIMIT_WEBHOOK', 120),
    ],
];
