<?php

namespace App\Enums;

/**
 * Tingkat ketersediaan slot untuk kalender publik (FR-BKG-02, AC-5 Fase 4).
 *
 * Ambang "hampir penuh" mengikuti persentase sisa kuota terhadap kapasitas.
 */
enum AvailabilityStatus: string
{
    case AVAILABLE = 'available';
    case ALMOST_FULL = 'almost_full';
    case FULL = 'full';
    case CLOSED = 'closed';

    /**
     * Tanggal yang belum memiliki slot sama sekali (belum digenerate).
     *
     * Dibedakan dari `CLOSED` agar klien tidak salah menyimpulkan bahwa museum
     * tutup pada tanggal tersebut — bisa jadi slotnya memang belum dibuat.
     */
    case NOT_OPEN = 'not_open';

    /**
     * Ambang sisa kuota (dalam persen) di bawah nilai ini dianggap hampir penuh.
     */
    public const ALMOST_FULL_THRESHOLD_PERCENT = 20;

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => 'Tersedia',
            self::ALMOST_FULL => 'Hampir penuh',
            self::FULL => 'Penuh',
            self::CLOSED => 'Tutup',
            self::NOT_OPEN => 'Belum dibuka',
        };
    }

    /**
     * Tentukan status ketersediaan dari sisa kuota.
     *
     * Slot yang ditutup/dibatalkan selalu `CLOSED`, berapa pun sisa kuotanya.
     */
    public static function fromRemaining(int $remaining, int $capacity, VisitSlotStatus $slotStatus): self
    {
        if (! $slotStatus->isBookable()) {
            return self::CLOSED;
        }

        if ($remaining <= 0) {
            return self::FULL;
        }

        if ($capacity <= 0) {
            return self::FULL;
        }

        $remainingPercent = ($remaining / $capacity) * 100;

        return $remainingPercent <= self::ALMOST_FULL_THRESHOLD_PERCENT
            ? self::ALMOST_FULL
            : self::AVAILABLE;
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
