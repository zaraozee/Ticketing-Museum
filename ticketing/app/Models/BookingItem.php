<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Satu baris item pada booking (ERD §3.4).
 *
 * `ticket_name_snapshot` dan `unit_price` membekukan nilai saat transaksi
 * sehingga perubahan harga berikutnya tidak mengubah booking lama (BR-19).
 *
 * @property int $id
 * @property int $booking_id
 * @property int $ticket_type_id
 * @property string $ticket_name_snapshot
 * @property int $unit_price
 * @property int $quantity
 * @property int $subtotal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BookingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'ticket_type_id',
        'ticket_name_snapshot',
        'unit_price',
        'quantity',
        'subtotal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'quantity' => 'integer',
            'subtotal' => 'integer',
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
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->orderBy('sequence');
    }
}
