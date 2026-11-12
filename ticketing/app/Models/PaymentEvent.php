<?php

namespace App\Models;

use App\Enums\PaymentEventResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Jejak setiap callback/polling dan **kunci idempotensi** (ERD §3.4).
 *
 * `event_key` unik; percobaan `INSERT` kedua dengan kunci sama akan gagal dan
 * dianggap duplikat, sehingga webhook berulang tidak pernah memproses ulang.
 *
 * @property int $id
 * @property int|null $payment_id
 * @property string $provider
 * @property string $event_key
 * @property bool $signature_valid
 * @property array<string, mixed> $payload
 * @property PaymentEventResult $process_result
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'provider',
        'event_key',
        'signature_valid',
        'payload',
        'process_result',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'process_result' => PaymentEventResult::class,
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
