<?php

namespace App\Enums;

/**
 * Jenis halaman konten statis yang dikelola Admin (FR-ADM-09).
 */
enum ContentPageType: string
{
    case PAGE = 'page';
    case FAQ = 'faq';

    public function label(): string
    {
        return match ($this) {
            self::PAGE => 'Halaman',
            self::FAQ => 'FAQ',
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
