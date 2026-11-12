<?php

namespace App\Enums;

/**
 * Hasil pemindaian tiket di gerbang (FR-GATE-03).
 *
 * Setiap hasil memiliki warna, ikon, dan pesan untuk petugas.
 */
enum ScanResult: string
{
    case VALID = 'valid';
    case ALREADY_USED = 'already_used';
    case INVALID = 'invalid';
    case NOT_YET_VALID = 'not_yet_valid';
    case EXPIRED = 'expired';
    case WRONG_MUSEUM = 'wrong_museum';
    case VOIDED = 'voided';

    /**
     * Label untuk UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::VALID => 'VALID — silakan masuk',
            self::ALREADY_USED => 'SUDAH DIGUNAKAN',
            self::INVALID => 'TIDAK VALID',
            self::NOT_YET_VALID => 'BELUM BERLAKU',
            self::EXPIRED => 'KEDALUWARSA',
            self::WRONG_MUSEUM => 'TIKET MUSEUM LAIN',
            self::VOIDED => 'TIKET DIBATALKAN',
        };
    }

    /**
     * Warna untuk UI (Tailwind classes).
     */
    public function color(): string
    {
        return match ($this) {
            self::VALID => 'green',
            self::ALREADY_USED => 'red',
            self::INVALID => 'red',
            self::NOT_YET_VALID => 'yellow',
            self::EXPIRED => 'gray',
            self::WRONG_MUSEUM => 'orange',
            self::VOIDED => 'red',
        };
    }

    /**
     * Apakah scan ini mengizinkan masuk.
     */
    public function allowsEntry(): bool
    {
        return $this === self::VALID;
    }
}
