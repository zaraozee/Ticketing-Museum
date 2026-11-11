<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Jenis tiket dan harga (FR-ORG-06, ERD §3.3).
 *
 * Harga disimpan sebagai integer rupiah. Perubahan harga tidak memengaruhi
 * booking lama karena `booking_items` menyimpan snapshot (BR-19).
 *
 * @property int $id
 * @property int $museum_id
 * @property string $name
 * @property string|null $description
 * @property int $price
 * @property int|null $min_age
 * @property int|null $max_age
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TicketType extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'museum_id',
        'name',
        'description',
        'price',
        'min_age',
        'max_age',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'min_age' => 'integer',
            'max_age' => 'integer',
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
     * @param  Builder<TicketType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Harga dalam format rupiah untuk tampilan.
     */
    public function formattedPrice(): string
    {
        return 'Rp '.number_format($this->price, 0, ',', '.');
    }
}
