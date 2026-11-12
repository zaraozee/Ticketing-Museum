<?php

namespace App\Services\Ticket;

use InvalidArgumentException;
use RuntimeException;

/**
 * Penandatanganan dan verifikasi token QR tiket (BR-17, SYSTEM_DESIGN §11.3).
 *
 * Format: MT1.<kid>.<ticket_uuid>.<sig>
 * - kid = ID kunci (rotasi kunci)
 * - sig = base64url(HMAC-SHA256(key, "MT1." + uuid)) dipotong 22 char
 *
 * ponytail: token statis; upgrade ke TOTP untuk token dinamis berputar.
 */
class TicketTokenSigner
{
    private const VERSION = 'MT1';

    private const SIG_LENGTH = 22;

    /**
     * Generate token QR untuk tiket.
     */
    public function sign(string $ticketUuid): string
    {
        $kid = $this->defaultKid();
        $key = $this->getKey($kid);

        $payload = self::VERSION.'.'.$ticketUuid;
        $sig = $this->computeSignature($payload, $key);

        return self::VERSION.'.'.$kid.'.'.$ticketUuid.'.'.$sig;
    }

    /**
     * Verifikasi dan ekstrak UUID dari token.
     *
     * @return array{valid: bool, ticket_uuid: string|null, reason: string|null}
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 4) {
            return ['valid' => false, 'ticket_uuid' => null, 'reason' => 'invalid_format'];
        }

        [$version, $kid, $uuid, $providedSig] = $parts;

        if ($version !== self::VERSION) {
            return ['valid' => false, 'ticket_uuid' => null, 'reason' => 'unsupported_version'];
        }

        if (! $this->hasKey($kid)) {
            return ['valid' => false, 'ticket_uuid' => null, 'reason' => 'unknown_key'];
        }

        $key = $this->getKey($kid);
        $payload = $version.'.'.$uuid;
        $expectedSig = $this->computeSignature($payload, $key);

        // Constant-time comparison
        if (! hash_equals($expectedSig, $providedSig)) {
            return ['valid' => false, 'ticket_uuid' => null, 'reason' => 'invalid_signature'];
        }

        return ['valid' => true, 'ticket_uuid' => $uuid, 'reason' => null];
    }

    /**
     * Hitung signature HMAC-SHA256 dan potong ke 22 karakter base64url.
     */
    private function computeSignature(string $payload, string $key): string
    {
        $hash = hash_hmac('sha256', $payload, $key, true);
        $b64 = base64_encode($hash);

        // Base64url: +/= → -_
        $b64url = strtr($b64, '+/', '-_');
        $b64url = rtrim($b64url, '=');

        return substr($b64url, 0, self::SIG_LENGTH);
    }

    /**
     * Ambil kunci dari environment.
     */
    private function getKey(string $kid): string
    {
        $keys = $this->allKeys();

        if (! isset($keys[$kid])) {
            throw new InvalidArgumentException("Key ID '{$kid}' not found in TICKET_SIGNING_KEYS.");
        }

        return $keys[$kid];
    }

    /**
     * Cek apakah kunci tersedia.
     */
    private function hasKey(string $kid): bool
    {
        $keys = $this->allKeys();

        return isset($keys[$kid]);
    }

    /**
     * Default kid (kunci pertama).
     */
    private function defaultKid(): string
    {
        $keys = $this->allKeys();

        if (empty($keys)) {
            throw new RuntimeException('TICKET_SIGNING_KEYS tidak dikonfigurasi. Set di .env sebagai JSON.');
        }

        return array_key_first($keys);
    }

    /**
     * Parse semua kunci dari environment.
     *
     * Format: {"k1":"secret1","k2":"secret2"}
     *
     * @return array<string, string>
     */
    private function allKeys(): array
    {
        $raw = config('ticketing.ticket_signing_keys');

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('TICKET_SIGNING_KEYS harus berupa JSON object.');
        }

        return $decoded;
    }
}
