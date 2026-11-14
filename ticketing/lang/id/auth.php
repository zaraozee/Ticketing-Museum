<?php

/*
 * Terjemahan notifikasi autentikasi bawaan Laravel.
 *
 * Tanpa berkas ini Laravel memakai string bahasa Inggris bawaan framework
 * (`Verify your email address`, `Reset your password`) padahal `app.locale`
 * sudah `id`. Email transaksional aplikasi memakai Bahasa Indonesia, jadi
 * notifikasi bawaan pun harus mengikuti.
 */

return [
    'failed' => 'Kredensial ini tidak cocok dengan data kami.',
    'password' => 'Kata sandi salah.',
    'throttle' => 'Terlalu banyak percobaan masuk. Silakan coba lagi dalam :seconds detik.',
];
