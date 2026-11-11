<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Password;

test('reset password link screen can be rendered', function () {
    $response = $this->get('/forgot-password');

    $response->assertStatus(200);
});

test('reset password screen can be rendered', function () {
    $response = $this->get('/reset-password/test-token');

    $response->assertStatus(200);
});

test('password can be reset with valid token', function () {
    Event::fake();

    $user = User::factory()->create();

    $token = Password::createToken($user);

    $response = $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect('/login');

    Event::assertDispatched(PasswordReset::class);
    $this->assertTrue(auth()->attempt([
        'email' => $user->email,
        'password' => 'new-password123',
    ]));

    $this->assertDatabaseHas('activity_logs', [
        'actor_id' => $user->id,
        'action' => 'user.password_reset',
    ]);
});
