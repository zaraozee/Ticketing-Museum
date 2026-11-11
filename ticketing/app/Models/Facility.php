<?php

namespace App\Models;

use Database\Factories\FacilityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Master fasilitas lintas museum (FR-ADM-05, D-23).
 *
 * @property int $id
 * @property string $name
 * @property string|null $icon
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Facility extends Model
{
    /** @use HasFactory<FacilityFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'icon',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Museum, $this>
     */
    public function museums(): BelongsToMany
    {
        return $this->belongsToMany(Museum::class, 'museum_facility')->withTimestamps();
    }
}
