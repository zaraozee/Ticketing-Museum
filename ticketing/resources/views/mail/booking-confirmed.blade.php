@component('mail::message')
# E-ticket Anda sudah terbit

Halo {{ $booking->contact_name }},

Pembayaran untuk pemesanan **{{ $booking->booking_code }}** telah kami terima. Berikut ringkasan kunjungan Anda:

@component('mail::table')
| | |
|:--|:--|
| Museum | {{ $booking->museum_name_snapshot }} |
| Tanggal kunjungan | {{ $booking->visit_date_snapshot->translatedFormat('l, d F Y') }} |
| Slot | {{ $booking->slot_label_snapshot }} |
| Jumlah tiket | {{ $tickets->count() }} |
@endcomponent

@component('mail::button', ['url' => $ticketUrl, 'color' => 'primary'])
Buka E-ticket
@endcomponent

Tunjukkan QR pada halaman e-ticket kepada petugas di pintu masuk. Halaman tersebut dapat dibuka kembali kapan saja dari menu **Tiket Saya**, termasuk saat perangkat Anda tidak memiliki koneksi internet.

**Kode tiket:** {{ $tickets->pluck('ticket_code')->join(', ') }}

Terima kasih telah menggunakan {{ $appName }}.

@endcomponent
