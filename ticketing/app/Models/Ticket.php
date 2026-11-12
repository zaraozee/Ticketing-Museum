<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Satu tiket = satu QR (ERD §3.5, D-29, D-30).
 *
 * Token asli **tidak** disimpan; hanya `token_hash` untuk pencarian saat scan
 * dan `uuid` untuk menghitung ulang token via HMAC (ERD §5). Penerbitan tiket
 * terjadi di dalam transaksi konfirmasi pembayaran (BR-07).
 *
 * @property int $id
 * @property string $uuid
 * @property string $ticket_code
 * @property string $token_hash
 * @property int $booking_id
 * @property int $booking_item_id
 * @property int $sequence
 * @property int $museum_id
 * @property int $visit_slot_id
 * @property string|null $holder_name
 * @property TicketStatus $status
 * @property Carbon|null $used_at
 * @property int|null $used_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'ticket_code',
        'token_hash',
        'booking_id',
        'booking_item_id',
        'sequence',
        'museum_id',
        'visit_slot_id',
        'holder_name',
        'status',
        'used_at',
        'used_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'sequence' => 'integer',
            'used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            if (empty($ticket->uuid)) {
                $ticket->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Kode tiket pendek yang dapat diketik petugas.
     *
     * Alfabet tanpa karakter ambigu (0/O, 1/I/L) agar tidak salah baca.
     */
    public static function generateTicketCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = '';

            for ($i = 0; $i < 10; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::query()->where('ticket_code', $code)->exists());

        return $code;
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return BelongsTo<BookingItem, $this>
     */
    public function bookingItem(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class);
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
     * @param  Builder<Ticket>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', TicketStatus::ACTIVE->value);
    }
}
