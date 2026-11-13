<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],

    // Google Maps Embed API (THIRD_PARTY §4). Bila kosong, halaman museum
    // hanya menampilkan tautan "Petunjuk Arah" tanpa iframe.
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Midtrans (THIRD_PARTY_INTEGRATIONS §1, D-32)
    |--------------------------------------------------------------------------
    |
    | D-32: variabel ini sudah ada di .env.example sejak Fase 1 tetapi tidak
    | pernah terbaca karena entri config-nya belum dibuat. Server Key hanya
    | dipakai di backend dan tidak boleh pernah dikirim ke frontend.
    |
    */
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),

        // Snap memakai host berbeda untuk sandbox dan produksi. Nilai bawaan
        // sengaja mengikuti `MIDTRANS_IS_PRODUCTION` supaya menyalakan mode
        // produksi tidak meninggalkan skrip sandbox di halaman pembayaran —
        // kesalahan yang membuat pembayaran produksi gagal tanpa pesan jelas.
        'snap_url' => env('MIDTRANS_SNAP_URL')
            ?: (env('MIDTRANS_IS_PRODUCTION', false)
                ? 'https://app.midtrans.com/snap/snap.js'
                : 'https://app.sandbox.midtrans.com/snap/snap.js'),

        // Basis API Snap; sandbox dan produksi memakai host berbeda.
        'api_base_url' => env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com',

        'status_api_base_url' => env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com',

        // Batas waktu permintaan ke gateway (detik).
        'timeout' => (int) env('MIDTRANS_TIMEOUT', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token QR tiket (SYSTEM_DESIGN §11.3, D-34)
    |--------------------------------------------------------------------------
    |
    | `keys` adalah peta `kid => secret`. Bentuk peta (bukan satu nilai) agar
    | kunci dapat dirotasi: kunci baru ditambahkan, kunci lama tetap diterima
    | selama masa transisi. Kunci tidak pernah disimpan di database, sehingga
    | basis data yang bocor tidak cukup untuk memalsukan tiket.
    |
    */
    'ticket' => [
        'keys' => json_decode((string) env('TICKET_SIGNING_KEYS', '{}'), true) ?: [],
        // `kid` yang dipakai saat menerbitkan tiket baru.
        'active_kid' => env('TICKET_ACTIVE_KID', 'MT1'),
        // Panjang tanda tangan base64url yang dipakai (§11.3: 22 karakter).
        'signature_length' => 22,
    ],

];
