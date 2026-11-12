<?php

namespace App\Jobs;

use App\Services\Payment\WebhookProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Memproses satu notifikasi pembayaran di luar siklus HTTP
 * (THIRD_PARTY_INTEGRATIONS §1.3 langkah 4).
 *
 * Payload dibawa apa adanya; seluruh verifikasi tetap dilakukan di
 * `WebhookProcessor` sehingga job tidak pernah mempercayai isinya.
 */
class ProcessPaymentEvent implements ShouldQueue
{
    use Queueable;

    /**
     * Notifikasi berulang lebih baik dicoba beberapa kali daripada hilang.
     */
    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function handle(WebhookProcessor $processor): void
    {
        $processor->handle($this->payload);
    }
}
