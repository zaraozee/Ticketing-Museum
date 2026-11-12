<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Transaksi pemesanan (ERD §3.4).
 *
 * `total_amount` selalu sama dengan jumlah `booking_items.subtotal`; nilainya
 * dibekukan saat booking dibuat dan tidak berubah walau harga tiket dinaikkan
 * (BR-19).
 *
 * @property int $id
 * @property string $uuid
 * @property string $booking_code
 * @property int $user_id
 * @property int $museum_id
 * @property int $visit_slot_id
 * @property BookingStatus $status
 * @property int $total_amount
 * @property string $contact_name
 * @property string $contact_email
 * @property string $contact_phone
 * @property string $museum_name_snapshot
 * @property Carbon $visit_date_snapshot
 * @property string $slot_label_snapshot
 * @property Carbon|null $expires_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $expired_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'booking_code',
        'user_id',
        'museum_id',
        'visit_slot_id',
        'status',
        'total_amount',
        'contact_name',
        'contact_email',
        'contact_phone',
        'museum_name_snapshot',
        'visit_date_snapshot',
        'slot_label_snapshot',
        'expires_at',
        'confirmed_at',
        'cancelled_at',
        'expired_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'total_amount' => 'integer',
            'visit_date_snapshot' => 'date:Y-m-d',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            if (empty($booking->uuid)) {
                $booking->uuid = (string) Str::uuid();
            }

            if (empty($booking->booking_code)) {
                $booking->booking_code = self::generateBookingCode();
            }
        });
    }

    /**
     * Kode booking yang mudah dibaca: 3 huruf + tanda hubung + 6 karakter.
     *
     * Alfabet sengaja menghindari karakter ambigu (0/O, 1/I/L) agar aman
     * dibacakan lewat telepon atau diketik manual.
     */
    public static function generateBookingCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = 'MUS-';

            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::query()->where('booking_code', $code)->exists());

        return $code;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Museum, $this>
     */
    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }

    /**
     * @return BelongsTo<VisitSlot, $this>
     */
    public function visitSlot(): BelongsTo
    {
        return $this->belongsTo(VisitSlot::class);
    }

    /**
     * @return HasMany<BookingItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Total jumlah tiket pada seluruh item.
     */
    public function totalQuantity(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    /**
     * Booking masih boleh dibayar (belum lewat batas dan masih pending).
     */
    public function isPayable(): bool
    {
        if ($this->status !== BookingStatus::PENDING_PAYMENT) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Sisa waktu bayar dalam detik; 0 bila sudah lewat.
     */
    public function secondsUntilExpiry(): int
    {
        if ($this->expires_at === null) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    /**
     * @param  Builder<Booking>  $query
     */
    public function scopePendingPayment(Builder $query): void
    {
        $query->where('status', BookingStatus::PENDING_PAYMENT->value);
    }

    /**
     * @param  Builder<Booking>  $query
     */
    public function scopeExpiredCandidate(Builder $query): void
    {
        $query->where('status', BookingStatus::PENDING_PAYMENT->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());
    }
}
