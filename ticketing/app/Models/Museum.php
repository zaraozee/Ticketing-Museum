<?php

namespace App\Models;

use App\Enums\BookingMode;
use App\Enums\MuseumStatus;
use Database\Factories\MuseumFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property int $organizer_profile_id
 * @property int $category_id
 * @property string $name
 * @property string $slug
 * @property string $venue_type
 * @property string|null $description
 * @property string|null $address
 * @property string|null $city
 * @property string|null $province
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string $timezone
 * @property MuseumStatus $status
 * @property BookingMode $booking_mode
 * @property int $max_tickets_per_booking
 * @property int $entry_early_minutes
 * @property int $entry_late_minutes
 * @property string|null $youtube_video_id
 * @property Carbon|null $submitted_at
 * @property Carbon|null $published_at
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_note
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Museum extends Model
{
    /** @use HasFactory<MuseumFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'organizer_profile_id',
        'category_id',
        'name',
        'slug',
        'venue_type',
        'description',
        'address',
        'city',
        'province',
        'latitude',
        'longitude',
        'timezone',
        'status',
        'booking_mode',
        'max_tickets_per_booking',
        'entry_early_minutes',
        'entry_late_minutes',
        'youtube_video_id',
        'submitted_at',
        'published_at',
        'rejected_at',
        'rejection_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MuseumStatus::class,
            'booking_mode' => BookingMode::class,
            'max_tickets_per_booking' => 'integer',
            'entry_early_minutes' => 'integer',
            'entry_late_minutes' => 'integer',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Museum $museum): void {
            if (empty($museum->uuid)) {
                $museum->uuid = (string) Str::ulid();
            }
        });
    }

    /**
     * Route binding memakai slug, bukan id berurutan.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<OrganizerProfile, $this>
     */
    public function organizerProfile(): BelongsTo
    {
        return $this->belongsTo(OrganizerProfile::class);
    }

    /**
     * @return BelongsTo<MuseumCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MuseumCategory::class, 'category_id');
    }

    /**
     * @return HasMany<MuseumImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(MuseumImage::class)->orderBy('sort_order');
    }

    /**
     * @return BelongsToMany<Facility, $this>
     */
    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'museum_facility')->withTimestamps();
    }

    /**
     * @return HasMany<MuseumOperatingHour, $this>
     */
    public function operatingHours(): HasMany
    {
        return $this->hasMany(MuseumOperatingHour::class)->orderBy('day_of_week');
    }

    /**
     * @return HasMany<MuseumClosedDate, $this>
     */
    public function closedDates(): HasMany
    {
        return $this->hasMany(MuseumClosedDate::class)->orderBy('closed_on');
    }

    /**
     * @return HasMany<MuseumStaff, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(MuseumStaff::class);
    }

    /**
     * Jenis tiket yang dijual museum (FR-ORG-06).
     *
     * @return HasMany<TicketType, $this>
     */
    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('sort_order');
    }

    /**
     * Cetakan slot berulang (FR-ORG-07).
     *
     * @return HasMany<SlotTemplate, $this>
     */
    public function slotTemplates(): HasMany
    {
        return $this->hasMany(SlotTemplate::class)->orderBy('sort_order');
    }

    /**
     * Slot kunjungan yang dapat dipesan (inti kuota).
     *
     * @return HasMany<VisitSlot, $this>
     */
    public function visitSlots(): HasMany
    {
        return $this->hasMany(VisitSlot::class);
    }

    /**
     * Override kuota per tanggal (FR-ORG-07).
     *
     * @return HasMany<SlotCapacityOverride, $this>
     */
    public function slotCapacityOverrides(): HasMany
    {
        return $this->hasMany(SlotCapacityOverride::class);
    }

    /**
     * Museum yang tampil di halaman publik (BR-01).
     *
     * @param  Builder<Museum>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', MuseumStatus::PUBLISHED->value);
    }

    /**
     * Batasi query ke museum milik organizer profile tertentu (BR-02).
     *
     * @param  Builder<Museum>  $query
     */
    public function scopeOwnedBy(Builder $query, int $organizerProfileId): void
    {
        $query->where('organizer_profile_id', $organizerProfileId);
    }

    public function coverImage(): ?MuseumImage
    {
        return $this->images->firstWhere('is_cover', true) ?? $this->images->first();
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
