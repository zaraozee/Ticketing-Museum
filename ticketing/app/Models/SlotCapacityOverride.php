<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Override kuota untuk satu tanggal (FR-ORG-07, D-27).
 *
 * Disimpan terpisah dari `visit_slots` agar `slots:generate` yang idempoten
 * tidak menimpa kuota yang sudah disesuaikan Organizer. `start_time` null
 * berarti override berlaku untuk museum `booking_mode = daily`.
 *
 * @property int $id
 * @property int $museum_id
 * @property Carbon $visit_date
 * @property string|null $start_time
 * @property int $capacity
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SlotCapacityOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'museum_id',
        'visit_date',
        'start_time',
        'capacity',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_date' => 'date:Y-m-d',
            'capacity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Museum, $this>
     */
    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }
}
