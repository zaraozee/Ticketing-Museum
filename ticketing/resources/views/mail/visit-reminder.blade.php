@component('mail::message')
# Kunjungan Anda besok

Halo {{ $booking->contact_name }},

Ini pengingat bahwa Anda akan berkunjung ke **{{ $booking->museum_name_snapshot }}** pada:

@component('mail::table')
| | |
|:--|:--|
| Tanggal | {{ $booking->visit_date_snapshot->translatedFormat('l, d F Y') }} |
| Slot | {{ $booking->slot_label_snapshot }} |
| Kode booking | {{ $booking->booking_code }} |
@endcomponent

Siapkan e-ticket Anda sebelum berangkat agar tidak perlu menunggu koneksi internet di lokasi.

@component('mail::button', ['url' => $ticketUrl, 'color' => 'primary'])
Lihat E-ticket
@endcomponent

Sampai jumpa di {{ $booking->museum_name_snapshot }}.

@endcomponent
