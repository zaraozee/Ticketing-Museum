<?php

namespace App\Models;

use Database\Factories\MuseumCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $parent_id
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MuseumCategory extends Model
{
    /** @use HasFactory<MuseumCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
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

    protected static function booted(): void
    {
        static::saving(function (MuseumCategory $category): void {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * @return BelongsTo<MuseumCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MuseumCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Museum, $this>
     */
    public function museums(): HasMany
    {
        return $this->hasMany(Museum::class, 'category_id');
    }
}
