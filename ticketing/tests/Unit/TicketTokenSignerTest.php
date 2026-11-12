<?php

use App\Services\Ticket\TicketTokenSigner;

beforeEach(function () {
    config(['ticketing.ticket_signing_keys' => json_encode(['k1' => 'test-secret-key-12345'])]);
    $this->signer = app(TicketTokenSigner::class);
});

test('dapat menandatangani token QR', function () {
    $uuid = '01JCEXAMPLE1234567890ABCDEF';

    $token = $this->signer->sign($uuid);

    expect($token)->toStartWith('MT1.k1.')
        ->and($token)->toContain($uuid)
        ->and(explode('.', $token))->toHaveCount(4);
});

test('dapat memverifikasi token valid', function () {
    $uuid = '01JCEXAMPLE1234567890ABCDEF';
    $token = $this->signer->sign($uuid);

    $result = $this->signer->verify($token);

    expect($result['valid'])->toBeTrue()
        ->and($result['ticket_uuid'])->toBe($uuid)
        ->and($result['reason'])->toBeNull();
});

test('menolak token dengan signature salah', function () {
    $uuid = '01JCEXAMPLE1234567890ABCDEF';
    $token = $this->signer->sign($uuid);

    // Modifikasi signature
    $parts = explode('.', $token);
    $parts[3] = 'fakesignature12345678';
    $badToken = implode('.', $parts);

    $result = $this->signer->verify($badToken);

    expect($result['valid'])->toBeFalse()
        ->and($result['reason'])->toBe('invalid_signature');
});

test('menolak token dengan format salah', function () {
    $result = $this->signer->verify('invalid-token');

    expect($result['valid'])->toBeFalse()
        ->and($result['reason'])->toBe('invalid_format');
});

test('menolak token dengan versi tidak didukung', function () {
    $result = $this->signer->verify('MT2.k1.uuid.sig');

    expect($result['valid'])->toBeFalse()
        ->and($result['reason'])->toBe('unsupported_version');
});

test('menolak token dengan kid tidak dikenal', function () {
    $result = $this->signer->verify('MT1.unknown.uuid.sig');

    expect($result['valid'])->toBeFalse()
        ->and($result['reason'])->toBe('unknown_key');
});

test('signature adalah 22 karakter base64url', function () {
    $uuid = '01JCEXAMPLE1234567890ABCDEF';
    $token = $this->signer->sign($uuid);

    $parts = explode('.', $token);
    $signature = $parts[3];

    expect($signature)->toHaveLength(22)
        ->and($signature)->toMatch('/^[A-Za-z0-9_-]+$/'); // Base64url
});
