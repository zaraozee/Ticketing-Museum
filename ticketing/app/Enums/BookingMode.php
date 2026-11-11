<?php

namespace App\Enums;

/**
 * Mode pemesanan museum (D-08).
 *
 * daily      : satu sesi per hari, kuota harian.
 * timed_slot : slot waktu dengan kuota masing-masing.
 */
enum BookingMode: string
{
    case DAILY = 'daily';
    case TIMED_SLOT = 'timed_slot';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Harian',
            self::TIMED_SLOT => 'Slot Waktu',
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
