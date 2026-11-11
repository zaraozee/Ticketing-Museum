<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case ORGANIZER = 'organizer';
    case USER = 'user';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Admin Platform',
            self::ORGANIZER => 'Pengelola Museum',
            self::USER => 'Pengunjung',
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
