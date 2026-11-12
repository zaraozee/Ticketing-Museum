<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Satu percobaan pembayaran (ERD §3.4).
 *
 * Status hanya boleh berubah lewat `PaymentService`/`WebhookProcessor`, tidak
 * pernah dari input frontend (§11.2 "kondisi terlarang").
 *
 * @property int $id
 * @property int $booking_id
 * @property string $provider
 * @property string $provider_order_id
 * @property string|null $provider_transaction_id
 * @property string|null $payment_type
 * @property int $gross_amount
 * @property PaymentStatus $status
 * @property string|null $snap_token
 * @property string|null $snap_redirect_url
 * @property Carbon|null $expires_at
 * @property Carbon|null $paid_at
 * @property bool $needs_review
 * @property string|null $review_note
 * @property array<string, mixed>|null $last_response
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'provider',
        'provider_order_id',
        'provider_transaction_id',
        'payment_type',
        'gross_amount',
        'status',
        'snap_token',
        'snap_redirect_url',
        'expires_at',
        'paid_at',
        'needs_review',
        'review_note',
        'last_response',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'gross_amount' => 'integer',
            'needs_review' => 'boolean',
            'last_response' => 'array',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return HasMany<PaymentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('id');
    }

    /**
     * @param  Builder<Payment>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [
            PaymentStatus::CREATED->value,
            PaymentStatus::PENDING->value,
        ]);
    }

    /**
     * Payment aktif yang masih berlaku (belum lewat `expires_at`).
     *
     * @param  Builder<Payment>  $query
     */
    public function scopeStillValid(Builder $query): void
    {
        $query->active()->where(function (Builder $query): void {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
