@component('mail::message')
# Verifikasi alamat email

Halo {{ $user->name }},

Terima kasih telah mendaftar di {{ $appName }}. Klik tombol di bawah untuk memverifikasi alamat email Anda.

@component('mail::button', ['url' => $verificationUrl, 'color' => 'primary'])
Verifikasi Email
@endcomponent

Tautan ini berlaku selama {{ $expiresInMinutes }} menit. Bila tombol di atas tidak berfungsi, salin dan tempel tautan berikut ke peramban Anda:

<span class="break-all">{{ $verificationUrl }}</span>

Bila Anda tidak membuat akun di {{ $appName }}, abaikan email ini — tidak ada tindakan lebih lanjut yang diperlukan.

@endcomponent
