<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('the login page hides the google button when credentials are missing', function () {
    config()->set('services.google.client_id', null);
    config()->set('services.google.client_secret', null);

    $response = $this->get('/login');

    $response->assertOk();
    // Tombol yang membawa pengguna ke halaman error Google lebih buruk
    // daripada tidak ada tombol sama sekali.
    $response->assertInertia(fn ($page) => $page->where('googleEnabled', false));
});

test('the login page shows the google button once credentials are configured', function () {
    config()->set('services.google.client_id', 'test-client-id');
    config()->set('services.google.client_secret', 'test-client-secret');

    $response = $this->get('/login');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('googleEnabled', true));
});

test('the google configuration check fails while credentials are missing', function () {
    config()->set('services.google.client_id', null);
    config()->set('services.google.client_secret', null);

    // Perintah ini yang dipakai saat menyiapkan lingkungan; ia harus
    // menunjuk variabel yang kurang, bukan sekadar gagal tanpa petunjuk.
    $this->artisan('auth:check-google')
        ->expectsOutputToContain('GOOGLE_CLIENT_ID: kosong')
        ->expectsOutputToContain('GOOGLE_CLIENT_SECRET: kosong')
        ->assertFailed();
});

test('the google configuration check passes when credentials are configured', function () {
    config()->set('services.google.client_id', 'test-client-id.apps.googleusercontent.com');
    config()->set('services.google.client_secret', 'GOCSPX-test-secret-value');
    config()->set('services.google.redirect', 'http://localhost:8000/auth/google/callback');
    config()->set('app.url', 'http://localhost:8000');

    $this->artisan('auth:check-google')
        ->expectsOutputToContain('Driver Google siap dipakai')
        ->assertSuccessful();
});

test('redirects to google provider', function () {
    config()->set('services.google.client_id', 'test-client-id');
    config()->set('services.google.client_secret', 'test-client-secret');
    config()->set('services.google.redirect', 'http://localhost/auth/google/callback');

    $response = $this->get('/auth/google');

    $response->assertRedirect();

    expect($response->headers->get('Location'))
        ->toContain('accounts.google.com')
        ->toContain('client_id=test-client-id');
});

test('redirects back to login with a clear message when google is not configured', function () {
    config()->set('services.google.client_id', null);
    config()->set('services.google.client_secret', null);

    $response = $this->get('/auth/google');

    // Tanpa kredensial, Google akan menolak dengan halaman error yang tidak
    // menjelaskan apa pun. Aplikasi harus menjelaskannya sendiri.
    $response->assertRedirect(route('login', absolute: false))
        ->assertSessionHas('error');

    expect(session('error'))->toContain('belum dikonfigurasi');
});

test('a google failure produces a message a visitor can act on', function () {
    Socialite::shouldReceive('driver->user')
        ->andThrow(new RuntimeException('Client error: `POST https://oauth2.googleapis.com/token` resulted in a `401 Unauthorized` response: redirect_uri_mismatch'));

    $response = $this->get('/auth/google/callback');

    $response->assertRedirect(route('login', absolute: false));

    // Pengunjung tidak boleh melihat pesan teknis mentah.
    expect(session('error'))
        ->toContain('alamat pengalihan tidak cocok')
        ->not->toContain('POST https://oauth2.googleapis.com');
});

test('new user can login via google and account is created', function () {
    $abstractUser = Mockery::mock(SocialiteUser::class, function (MockInterface $mock) {
        $mock->shouldReceive('getId')->andReturn('google-id-12345');
        $mock->shouldReceive('getName')->andReturn('Rian Pratama');
        $mock->shouldReceive('getEmail')->andReturn('rian@gmail.com');
    });

    Socialite::shouldReceive('driver->user')->andReturn($abstractUser);

    $response = $this->get('/auth/google/callback');

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));

    $user = User::where('email', 'rian@gmail.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Rian Pratama');
    expect($user->hasRole(UserRole::USER->value))->toBeTrue();

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-id-12345',
    ]);
});

test('existing user logging in via google links account and does not create duplicate user', function () {
    // User already exists
    $existingUser = User::factory()->create([
        'email' => 'rian@gmail.com',
        'name' => 'Rian Asli',
    ]);
    $existingUser->assignRole(UserRole::USER->value);

    $abstractUser = Mockery::mock(SocialiteUser::class, function (MockInterface $mock) {
        $mock->shouldReceive('getId')->andReturn('google-id-99999');
        $mock->shouldReceive('getName')->andReturn('Rian Google');
        $mock->shouldReceive('getEmail')->andReturn('rian@gmail.com');
    });

    Socialite::shouldReceive('driver->user')->andReturn($abstractUser);

    $response = $this->get('/auth/google/callback');

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));

    // Ensure total users with this email is exactly 1 (no duplicates)
    expect(User::where('email', 'rian@gmail.com')->count())->toBe(1);

    // Ensure social account is linked to the existing user
    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $existingUser->id,
        'provider' => 'google',
        'provider_user_id' => 'google-id-99999',
    ]);
});
