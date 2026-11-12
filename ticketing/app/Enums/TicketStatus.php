<?php

namespace App\Enums;

/**
 * Status tiket (SYSTEM_DESIGN §11.2, D-30).
 *
 * active -> used | void | expired
 */
enum TicketStatus: string
{
    case ACTIVE = 'active';
    case USED = 'used';
    case VOID = 'void';
    case EXPIRED = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktif',
            self::USED => 'Sudah Dipakai',
            self::VOID => 'Dibatalkan',
            self::EXPIRED => 'Kedaluwarsa',
        };
    }

    /**
     * Hanya tiket aktif yang boleh dipindai berhasil.
     */
    public function isScannable(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
