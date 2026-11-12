<?php

namespace App\Enums;

/**
 * Status booking (SYSTEM_DESIGN §11.2).
 *
 * pending_payment -> confirmed | expired | cancelled
 * confirmed       -> refunded
 */
enum BookingStatus: string
{
    case PENDING_PAYMENT = 'pending_payment';
    case CONFIRMED = 'confirmed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_PAYMENT => 'Menunggu Pembayaran',
            self::CONFIRMED => 'Terkonfirmasi',
            self::EXPIRED => 'Kedaluwarsa',
            self::CANCELLED => 'Dibatalkan',
            self::REFUNDED => 'Dikembalikan',
        };
    }

    /**
     * Status akhir: tidak ada transisi keluar.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::EXPIRED, self::CANCELLED, self::REFUNDED], true);
    }

    /**
     * Kuota masih ditahan untuk status ini (BR-05, BR-06).
     */
    public function holdsQuota(): bool
    {
        return $this === self::PENDING_PAYMENT;
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
