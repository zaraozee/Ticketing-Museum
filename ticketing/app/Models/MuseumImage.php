<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $museum_id
 * @property string $path
 * @property string|null $caption
 * @property int $sort_order
 * @property bool $is_cover
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class MuseumImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'museum_id',
        'path',
        'caption',
        'sort_order',
        'is_cover',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_cover' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Museum, $this>
     */
    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }

    public function url(): string
    {
        return Storage::disk((string) config('filesystems.museum_disk', 'public'))->url($this->path);
    }
}
