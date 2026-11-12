<?php

namespace App\Enums;

/**
 * Status satu percobaan pembayaran (SYSTEM_DESIGN §11.2).
 *
 * created -> pending -> paid
 *                    -> failed  (boleh dicoba lagi pada transaksi yang sama)
 *                    -> expired | cancelled
 * paid    -> refunded
 */
enum PaymentStatus: string
{
    case CREATED = 'created';
    case PENDING = 'pending';
    case PAID = 'paid';
    case FAILED = 'failed';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Dibuat',
            self::PENDING => 'Menunggu Pembayaran',
            self::PAID => 'Lunas',
            self::FAILED => 'Gagal',
            self::EXPIRED => 'Kedaluwarsa',
            self::CANCELLED => 'Dibatalkan',
            self::REFUNDED => 'Dikembalikan',
        };
    }

    /**
     * Payment masih aktif: booking tidak boleh membuat percobaan baru (BR-09).
     */
    public function isActive(): bool
    {
        return in_array($this, [self::CREATED, self::PENDING], true);
    }

    /**
     * Payment dianggap selesai dan tidak akan berubah lagi.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::PAID, self::FAILED, self::EXPIRED, self::CANCELLED, self::REFUNDED], true);
    }

    /**
     * Urutan monoton untuk mencegah transisi mundur (§11.2 "kondisi terlarang").
     *
     * Webhook terlambat yang membawa status "lebih rendah" harus diabaikan,
     * bukan menimpa status yang sudah lebih maju.
     */
    public function rank(): int
    {
        return match ($this) {
            self::CREATED => 0,
            self::PENDING => 1,
            self::FAILED, self::EXPIRED, self::CANCELLED => 2,
            self::PAID => 3,
            self::REFUNDED => 4,
        };
    }

    /**
     * Apakah transisi dari status saat ini ke $target diizinkan.
     *
     * Aturan khusus: `failed -> pending` diperbolehkan (BR-08: pengguna boleh
     * mencoba lagi pada transaksi yang sama), walau rank-nya sama-sama 2 dan 1.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return true;
        }

        if ($this === self::FAILED && in_array($target, [self::PENDING, self::CREATED], true)) {
            return true;
        }

        // `paid` dan `refunded` tidak boleh mundur ke status apa pun.
        if ($this === self::REFUNDED) {
            return false;
        }

        if ($this === self::PAID) {
            return $target === self::REFUNDED;
        }

        return $target->rank() > $this->rank();
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
