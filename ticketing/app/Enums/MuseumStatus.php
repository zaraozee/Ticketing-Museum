<?php

namespace App\Enums;

/**
 * Status alur museum (BR-21, D-22).
 *
 * draft -> pending_review -> published | rejected
 * published -> suspended (pelanggaran) -> published | archived
 */
enum MuseumStatus: string
{
    case DRAFT = 'draft';
    case PENDING_REVIEW = 'pending_review';
    case PUBLISHED = 'published';
    case REJECTED = 'rejected';
    case SUSPENDED = 'suspended';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draf',
            self::PENDING_REVIEW => 'Menunggu Verifikasi',
            self::PUBLISHED => 'Tayang',
            self::REJECTED => 'Ditolak',
            self::SUSPENDED => 'Ditangguhkan',
            self::ARCHIVED => 'Diarsipkan',
        };
    }

    /**
     * Museum yang boleh tampil di halaman publik dan dapat dipesan (BR-01).
     */
    public function isPubliclyVisible(): bool
    {
        return $this === self::PUBLISHED;
    }

    /**
     * Organizer masih boleh menyunting konten museum pada status ini.
     *
     * BR-21 secara eksplisit menyebut perubahan data kritis pada museum
     * `published` (nama, alamat, koordinat) dicatat di `activity_logs`,
     * sehingga museum tayang tetap dapat disunting. Status yang sedang
     * ditinjau Admin (`pending_review`) dan yang dibekukan (`suspended`,
     * `archived`) bersifat baca-saja bagi Organizer.
     */
    public function isEditableByOrganizer(): bool
    {
        return in_array($this, [self::DRAFT, self::REJECTED, self::PUBLISHED], true);
    }

    /**
     * Status yang boleh diajukan (ulang) untuk verifikasi Admin.
     */
    public function isSubmittable(): bool
    {
        return in_array($this, [self::DRAFT, self::REJECTED], true);
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
