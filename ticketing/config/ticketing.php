<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ticket Signing Keys
    |--------------------------------------------------------------------------
    |
    | Peta kunci HMAC untuk penandatanganan QR tiket (rotasi kunci).
    | Format JSON: {"k1":"secret1","k2":"secret2"}
    |
    | Generate: openssl rand -base64 32
    |
    */
    'ticket_signing_keys' => env('TICKET_SIGNING_KEYS', ''),

    /*
    |--------------------------------------------------------------------------
    | Entry Grace Period
    |--------------------------------------------------------------------------
    |
    | Jendela waktu masuk: berapa menit sebelum/sesudah slot tiket valid.
    | Default: 15 menit lebih awal, 30 menit terlambat.
    |
    */
    'entry_early_minutes' => (int) env('ENTRY_EARLY_MINUTES', 15),
    'entry_late_minutes' => (int) env('ENTRY_LATE_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Booking Window
    |--------------------------------------------------------------------------
    |
    | Berapa hari ke depan booking dapat dibuat.
    |
    */
    'booking_window_days' => (int) env('BOOKING_WINDOW_DAYS', 60),

    /*
    |--------------------------------------------------------------------------
    | Sales Cutoff
    |--------------------------------------------------------------------------
    |
    | Berapa menit sebelum slot dimulai penjualan ditutup.
    |
    */
    'sales_cutoff_minutes' => (int) env('SALES_CUTOFF_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Payment Window
    |--------------------------------------------------------------------------
    |
    | Batas waktu pembayaran sejak booking dibuat (menit).
    |
    */
    'payment_window_minutes' => (int) env('PAYMENT_WINDOW_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | PWA (Fase 8, FR-PWA-01..04)
    |--------------------------------------------------------------------------
    |
    | Tema visual final. Dipakai manifest (server-rendered) dan meta theme-color
    | di layout, sehingga warna aplikasi hanya didefinisikan di satu tempat.
    |
    */
    'pwa' => [
        'theme_color' => env('PWA_THEME_COLOR', '#2563eb'),
        'background_color' => env('PWA_BACKGROUND_COLOR', '#f8fafc'),
        // Halaman yang tidak boleh pernah dilayani service worker dari cache
        // (SYSTEM_DESIGN §15.2). Divalidasi oleh ServiceWorkerContractTest.
        'network_only_paths' => [
            '/bookings',
            '/tickets',
            '/organizer',
            '/admin',
            '/api/',
            '/webhooks/',
            '/login',
            '/register',
            '/password',
            '/profile',
            '/notifications',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ambang Monitoring (Fase 9, NFR-13)
    |--------------------------------------------------------------------------
    |
    | Dipakai `ops:health` untuk memutuskan kapan alarm berbunyi. Nilai default
    | adalah batas yang wajar untuk MVP; sesuaikan setelah pemantauan produksi
    | menunjukkan pola trafik nyata.
    |
    */
    'monitoring' => [
        // Job gagal yang menumpuk menandakan bug atau ketergantungan mati.
        'failed_jobs_threshold' => (int) env('MONITORING_FAILED_JOBS_THRESHOLD', 5),
        // Job tertua yang masih menunggu; melebihi ini berarti worker mati.
        'queue_age_minutes' => (int) env('MONITORING_QUEUE_AGE_MINUTES', 10),
        // Pembayaran pending yang jauh melewati batas bayar.
        'stuck_payment_minutes' => (int) env('MONITORING_STUCK_PAYMENT_MINUTES', 30),
        // Notifikasi webhook sah yang belum diproses.
        'webhook_lag_minutes' => (int) env('MONITORING_WEBHOOK_LAG_MINUTES', 10),
    ],
];
