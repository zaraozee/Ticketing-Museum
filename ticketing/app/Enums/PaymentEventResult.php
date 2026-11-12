<?php

namespace App\Enums;

/**
 * Hasil pemrosesan satu notifikasi pembayaran (ERD §3.4 `process_result`).
 */
enum PaymentEventResult: string
{
    case APPLIED = 'applied';
    case IGNORED_DUPLICATE = 'ignored_duplicate';
    case REJECTED_SIGNATURE = 'rejected_signature';
    case AMOUNT_MISMATCH = 'amount_mismatch';
    case UNKNOWN_ORDER = 'unknown_order';
    case NEEDS_REVIEW = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::APPLIED => 'Diterapkan',
            self::IGNORED_DUPLICATE => 'Duplikat diabaikan',
            self::REJECTED_SIGNATURE => 'Signature tidak sah',
            self::AMOUNT_MISMATCH => 'Nominal tidak cocok',
            self::UNKNOWN_ORDER => 'Order tidak dikenal',
            self::NEEDS_REVIEW => 'Perlu tinjauan',
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
