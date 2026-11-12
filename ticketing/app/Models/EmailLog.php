<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Log pengiriman email transaksional (FR-NTF-01, NFR-13).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $mailable_type
 * @property int|null $mailable_id
 * @property string $subject
 * @property string $to_email
 * @property string $status
 * @property int $attempts
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class EmailLog extends Model
{
    protected $fillable = [
        'user_id',
        'mailable_type',
        'mailable_id',
        'subject',
        'to_email',
        'status',
        'attempts',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
