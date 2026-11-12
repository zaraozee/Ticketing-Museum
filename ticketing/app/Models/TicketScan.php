<?php

namespace App\Models;

use App\Enums\ScanResult;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Riwayat scan tiket di gerbang (FR-GATE-05).
 *
 * @property int $id
 * @property int|null $ticket_id
 * @property int $museum_id
 * @property int $scanned_by
 * @property ScanResult $result
 * @property string $client_scan_uuid
 * @property Carbon $scanned_at
 * @property string|null $notes
 */
class TicketScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'museum_id',
        'scanned_by',
        'result',
        'client_scan_uuid',
        'scanned_at',
        'notes',
    ];

    protected $casts = [
        'result' => ScanResult::class,
        'scanned_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<Museum, $this>
     */
    public function museum(): BelongsTo
    {
        return $this->belongsTo(Museum::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function scannedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
