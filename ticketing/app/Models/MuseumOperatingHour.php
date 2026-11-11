<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $museum_id
 * @property int $day_of_week
 * @property string|null $open_time
 * @property string|null $close_time
 * @property bool $is_closed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MuseumOperatingHour extends Model
{
    use HasFactory;

    /**
     * Label hari, indeks 0 = Minggu mengikuti konvensi Carbon.
     *
     * @var array<int, string>
     */
    public const DAY_LABELS = [
        0 => 'Minggu',
        1 => 'Senin',
        2 => 'Selasa',
        3 => 'Rabu',
        4 => 'Kamis',
        5 => 'Jumat',
        6 => 'Sabtu',
    ];

    protected $fillable = [
        'museum_id',
        'day_of_week',
        'open_time',
        'close_time',
        'is_closed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'is_closed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Museum, $this>
     */
    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }

    public function dayLabel(): string
    {
        return self::DAY_LABELS[$this->day_of_week] ?? '-';
    }
}
