<?php

namespace App\Models;

use App\Enums\AvailabilityStatus;
use App\Enums\VisitSlotStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu baris = satu museum + tanggal + slot. **Inti kuota** (ERD §3.3, D-26).
 *
 * `capacity` adalah total kuota; `reserved_count` kuota yang ditahan booking
 * `pending_payment`; `sold_count` kuota yang sudah dibayar. Invarian yang dijaga
 * aplikasi dan CHECK database: `reserved_count + sold_count <= capacity`.
 *
 * @property int $id
 * @property int $museum_id
 * @property Carbon $visit_date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property int $capacity
 * @property int $reserved_count
 * @property int $sold_count
 * @property VisitSlotStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class VisitSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'museum_id',
        'visit_date',
        'start_time',
        'end_time',
        'capacity',
        'reserved_count',
        'sold_count',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Cast `date:Y-m-d` agar nilai tersimpan & dibandingkan sebagai
            // tanggal murni. Tanpa format eksplisit, Eloquent menyimpan
            // "Y-m-d H:i:s" sehingga pencarian slot per tanggal tidak cocok.
            'visit_date' => 'date:Y-m-d',
            'capacity' => 'integer',
            'reserved_count' => 'integer',
            'sold_count' => 'integer',
            'status' => VisitSlotStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Museum, $this>
     */
    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }

    /**
     * Sisa kuota yang masih dapat dijual.
     */
    public function remaining(): int
    {
        return max(0, $this->capacity - $this->reserved_count - $this->sold_count);
    }

    /**
     * Status ketersediaan untuk kalender publik (AC-5 Fase 4).
     */
    public function availability(): AvailabilityStatus
    {
        return AvailabilityStatus::fromRemaining($this->remaining(), $this->capacity, $this->status);
    }

    /**
     * Label slot (mis. "09:00 - 11:00" atau "Sepanjang hari").
     */
    public function label(): string
    {
        if ($this->start_time === null) {
            return 'Sepanjang hari';
        }

        $start = substr((string) $this->start_time, 0, 5);
        $end = $this->end_time === null ? null : substr((string) $this->end_time, 0, 5);

        return $end === null ? $start : "{$start} - {$end}";
    }

    /**
     * @param  Builder<VisitSlot>  $query
     */
    public function scopeBookable(Builder $query): void
    {
        $query->where('status', VisitSlotStatus::OPEN->value);
    }
}
