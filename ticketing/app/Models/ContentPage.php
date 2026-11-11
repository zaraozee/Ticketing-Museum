<?php

namespace App\Models;

use App\Enums\ContentPageType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Halaman konten statis dan FAQ yang dikelola Admin (FR-ADM-09).
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property ContentPageType $type
 * @property string|null $content
 * @property bool $is_published
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ContentPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'type',
        'content',
        'is_published',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ContentPageType::class,
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (ContentPage $page): void {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @param  Builder<ContentPage>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
