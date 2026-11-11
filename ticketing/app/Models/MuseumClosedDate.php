<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $museum_id
 * @property Carbon $closed_on
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MuseumClosedDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'museum_id',
        'closed_on',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closed_on' => 'date',
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
