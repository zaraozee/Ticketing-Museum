<?php

namespace App\Enums;

enum StaffRole: string
{
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case GATE_STAFF = 'gate_staff';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Pemilik',
            self::MANAGER => 'Manajer',
            self::GATE_STAFF => 'Petugas Gate',
        };
    }
}
