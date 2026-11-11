<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Cetakan slot berulang per museum (FR-ORG-07, ERD §3.3).
 *
 * Template hanya cetakan; baris yang benar-benar dijual ada di `visit_slots`
 * hasil command `slots:generate`. Untuk museum `booking_mode = daily`,
 * `start_time`/`end_time` bernilai null (satu slot = seluruh hari).
 *
 * @property int $id
 * @property int $museum_id
 * @property string $name
 * @property string|null $start_time
 * @property string|null $end_time
 * @property int $capacity
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SlotTemplate extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'museum_id',
        'name',
        'start_time',
        'end_time',
        'capacity',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
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
     * @param  Builder<SlotTemplate>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Label slot untuk ditampilkan (mis. "09:00 - 11:00" atau "Sepanjang hari").
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
}
