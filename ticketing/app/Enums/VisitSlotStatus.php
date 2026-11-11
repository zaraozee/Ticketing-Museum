<?php

namespace App\Enums;

/**
 * Status satu baris slot kunjungan (ERD §3.3).
 *
 * `open`     — dapat dipesan
 * `closed`   — ditutup manual oleh Organizer (mis. perbaikan ruang)
 * `cancelled`— slot dibatalkan; tidak dapat dipesan lagi
 */
enum VisitSlotStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Dibuka',
            self::CLOSED => 'Ditutup',
            self::CANCELLED => 'Dibatalkan',
        };
    }

    /**
     * Slot hanya dapat dipesan bila berstatus open.
     */
    public function isBookable(): bool
    {
        return $this === self::OPEN;
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
