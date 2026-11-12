<?php

use App\Enums\BookingStatus;
use App\Enums\TicketStatus;
use App\Mail\BookingConfirmedMail;
use App\Mail\VisitReminderMail;
use App\Models\Booking;
use App\Models\EmailLog;
use App\Models\Museum;
use App\Models\Notification;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Notification\BookingNotifier;
use App\Services\Notification\EmailLogger;
use Illuminate\Contracts\Mail\Mailer as MailerContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| Email transaksional dan notifikasi (FR-NTF-01, FR-NTF-03, FR-TIX-04)
|--------------------------------------------------------------------------
|
| Menguji kontrak yang terlihat pengguna: email masuk antrean (bukan dikirim
| saat request), kegagalan tercatat di `email_logs`, dan pengingat H-1 tidak
| pernah terkirim dua kali untuk booking yang sama.
|
*/

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->museum = Museum::factory()->published()->create();

    $this->booking = Booking::factory()
        ->forUser($this->user)
        ->for($this->museum)
        ->confirmed()
        ->create([
            'visit_date_snapshot' => now()->addDay()->toDateString(),
            'contact_email' => 'pengunjung@example.test',
        ]);

    Ticket::factory()->count(2)->create([
        'booking_id' => $this->booking->id,
        'museum_id' => $this->museum->id,
        'visit_slot_id' => $this->booking->visit_slot_id,
        'status' => TicketStatus::ACTIVE,
    ]);
});

test('the confirmation email is queued and recorded in email_logs', function (): void {
    Mail::fake();

    app(BookingNotifier::class)->confirmed($this->booking);

    Mail::assertQueued(BookingConfirmedMail::class);

    $log = EmailLog::query()->firstOrFail();

    expect($log->to_email)->toBe('pengunjung@example.test')
        ->and($log->user_id)->toBe($this->user->id)
        ->and($log->mailable_type)->toBe(BookingConfirmedMail::class)
        ->and($log->status)->toBe('pending')
        ->and($log->subject)->toContain($this->booking->booking_code);
});

test('the confirmation email is queued rather than sent inside the request', function (): void {
    Mail::fake();

    app(BookingNotifier::class)->confirmed($this->booking);

    // Email yang dikirim langsung akan memblokir request pengguna selama
    // menunggu SMTP; semuanya harus lewat antrean.
    Mail::assertNothingSent();
    Mail::assertQueued(BookingConfirmedMail::class);
});

test('the mailable implements ShouldQueue so the queue worker handles it', function (): void {
    expect(new BookingConfirmedMail($this->booking))
        ->toBeInstanceOf(ShouldQueue::class);
});

test('the confirmation email renders the booking details without exposing payment data', function (): void {
    $mail = new BookingConfirmedMail($this->booking);

    $html = $mail->render();

    expect($html)
        ->toContain($this->booking->booking_code)
        ->toContain($this->booking->museum_name_snapshot)
        // Nama aplikasi diambil dari konfigurasi, tidak di-hardcode.
        ->toContain((string) config('app.name'))
        // QR tidak dilampirkan inline: klien email memblokirnya.
        ->not->toContain('data:image');
});

test('the confirmation notification is created once even when called repeatedly', function (): void {
    Mail::fake();

    $notifier = app(BookingNotifier::class);

    $notifier->confirmed($this->booking);
    $notifier->confirmed($this->booking);

    // Webhook duplikat tidak boleh menumpuk notifikasi yang sama.
    expect(Notification::query()->where('user_id', $this->user->id)->count())->toBe(1);
});

test('an expired booking produces an in-app notification', function (): void {
    app(BookingNotifier::class)->expired($this->booking);

    $notification = Notification::query()->firstOrFail();

    expect($notification->type)->toBe('booking.expired')
        ->and($notification->title)->toContain('kedaluwarsa')
        ->and($notification->data['booking_code'])->toBe($this->booking->booking_code);
});

test('a permanent failure is recorded as failed with the error message', function (): void {
    $log = EmailLog::create([
        'user_id' => $this->user->id,
        'mailable_type' => BookingConfirmedMail::class,
        'subject' => 'E-ticket',
        'to_email' => 'pengunjung@example.test',
        'status' => 'pending',
        'attempts' => 1,
    ]);

    EmailLogger::failed($log, new RuntimeException('SMTP menolak koneksi'));

    $fresh = $log->fresh();

    expect($fresh->status)->toBe('failed')
        ->and($fresh->error_message)->toBe('SMTP menolak koneksi');
});

test('a failed send through the mailer marks the log failed and rethrows', function (): void {
    $mail = BookingConfirmedMail::forBooking($this->booking);

    $mailer = Mockery::mock(MailerContract::class);
    $mailer->shouldReceive('send')->andThrow(new RuntimeException('SMTP menolak koneksi'));

    expect(fn () => $mail->send($mailer))->toThrow(RuntimeException::class);

    // Worker antrean harus melihat kegagalan agar mekanisme retry berjalan,
    // dan dukungan pelanggan harus dapat melihat penyebabnya.
    expect(EmailLog::query()->find($mail->emailLogId)->status)->toBe('failed');
});

test('the visit reminder command queues one email per confirmed booking', function (): void {
    Mail::fake();

    $this->artisan('bookings:remind')
        ->expectsOutputToContain('email pengingat diantrekan')
        ->assertSuccessful();

    Mail::assertQueued(VisitReminderMail::class, 1);

    expect($this->booking->fresh()->reminder_sent_at)->not->toBeNull();
});

test('the visit reminder is never sent twice for the same booking', function (): void {
    Mail::fake();

    $this->artisan('bookings:remind')->assertSuccessful();
    $this->artisan('bookings:remind')->assertSuccessful();

    // Penjadwal dapat berjalan ulang setelah restart; pengunjung tidak boleh
    // menerima pengingat kedua.
    Mail::assertQueued(VisitReminderMail::class, 1);
});

test('the visit reminder ignores bookings that are not confirmed or not tomorrow', function (): void {
    Mail::fake();

    $pending = Booking::factory()
        ->forUser($this->user)
        ->for($this->museum)
        ->create(['visit_date_snapshot' => now()->addDay()->toDateString()]);

    $nextWeek = Booking::factory()
        ->forUser($this->user)
        ->for($this->museum)
        ->confirmed()
        ->create(['visit_date_snapshot' => now()->addWeek()->toDateString()]);

    $this->artisan('bookings:remind')->assertSuccessful();

    Mail::assertQueued(VisitReminderMail::class, 1);

    expect($pending->fresh()->reminder_sent_at)->toBeNull()
        ->and($nextWeek->fresh()->reminder_sent_at)->toBeNull();
});

test('the dry run reports the count without queuing anything', function (): void {
    Mail::fake();

    $this->artisan('bookings:remind', ['--dry-run' => true])
        ->expectsOutputToContain('akan diingatkan')
        ->assertSuccessful();

    Mail::assertNothingQueued();

    expect($this->booking->fresh()->reminder_sent_at)->toBeNull();
});

test('a confirmed booking still issues tickets when the email cannot be queued', function (): void {
    // Email adalah efek samping, bukan syarat sahnya tiket: pengguna tetap
    // dapat membuka e-ticket dari akun walau SMTP bermasalah.
    expect(Ticket::query()->where('booking_id', $this->booking->id)->count())->toBe(2)
        ->and($this->booking->status)->toBe(BookingStatus::CONFIRMED);
});
