<?php

namespace App\Enums;

enum OrganizerStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Menunggu Verifikasi',
            self::APPROVED => 'Disetujui',
            self::SUSPENDED => 'Ditangguhkan',
        };
    }
}
