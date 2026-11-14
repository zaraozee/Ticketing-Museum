<?php

use App\Mail\VerifyEmailMail;
use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get('/verify-email');

    $response->assertStatus(200);
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect('/?verified=1');

    $this->assertDatabaseHas('activity_logs', [
        'actor_id' => $user->id,
        'action' => 'user.email_verified',
    ]);
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email@example.com')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('the verification email is queued rather than sent inside the request', function () {
    $user = User::factory()->unverified()->create();

    Mail::fake();

    $user->sendEmailVerificationNotification();

    // Menunggu SMTP di dalam request membuat pengguna menunggu, dan SMTP yang
    // lambat dapat menggagalkan pendaftaran yang sebenarnya sudah berhasil.
    Mail::assertNothingSent();
    Mail::assertQueued(VerifyEmailMail::class);
});

test('the verification email is recorded in email_logs', function () {
    $user = User::factory()->unverified()->create([
        'email' => 'pendaftar@example.test',
    ]);

    Mail::fake();

    $user->sendEmailVerificationNotification();

    $log = EmailLog::query()->firstOrFail();

    // Dukungan pelanggan harus dapat menelusuri "email verifikasi tidak sampai"
    // tanpa menebak-nebak.
    expect($log->to_email)->toBe('pendaftar@example.test')
        ->and($log->user_id)->toBe($user->id)
        ->and($log->mailable_type)->toBe(VerifyEmailMail::class)
        ->and($log->status)->toBe('pending')
        ->and($log->subject)->toContain(config('app.name'));
});

test('the verification email contains a signed link that verifies the address', function () {
    $user = User::factory()->unverified()->create();

    Mail::fake();

    $user->sendEmailVerificationNotification();

    $mail = null;
    Mail::assertQueued(VerifyEmailMail::class, function (VerifyEmailMail $queued) use (&$mail): bool {
        $mail = $queued;

        return true;
    });

    expect($mail)->not->toBeNull();

    // Tautan di dalam email harus benar-benar dapat dipakai, bukan sekadar
    // tampak benar.
    $response = $this->actingAs($user)->get($mail->verificationUrl);

    $response->assertRedirect('/?verified=1');
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('the verification email is written in Indonesian', function () {
    $user = User::factory()->unverified()->create();

    $html = VerifyEmailMail::forUser($user)->render();

    // `app.locale` bernilai `id`; email yang tampil berbahasa Inggris berarti
    // terjemahan belum terpasang.
    expect($html)
        ->toContain('Verifikasi alamat email')
        ->toContain('Verifikasi Email')
        ->not->toContain('Verify your email address');
});
