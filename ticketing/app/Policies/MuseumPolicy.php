<?php

namespace App\Policies;

use App\Enums\MuseumStatus;
use App\Models\Museum;
use App\Models\User;

/**
 * Otorisasi museum (BR-02). Setiap akses Organizer wajib dibatasi kepemilikan.
 */
class MuseumPolicy
{
    /**
     * Admin platform boleh melihat semua museum; organizer hanya miliknya.
     */
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdmin() || $user->isOrganizer();
    }

    public function view(User $user, Museum $museum): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $this->ownsMuseum($user, $museum);
    }

    public function create(User $user): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        if (! $user->isOrganizer()) {
            return false;
        }

        // BR-22: organizer harus approved sebelum dapat mengajukan museum.
        return $user->organizerProfile?->status->value === 'approved';
    }

    public function update(User $user, Museum $museum): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        if (! $this->ownsMuseum($user, $museum)) {
            return false;
        }

        // Museum yang sedang ditinjau atau sudah tayang tidak boleh diubah bebas.
        return $museum->status->isEditableByOrganizer();
    }

    public function delete(User $user, Museum $museum): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return $this->ownsMuseum($user, $museum)
            && $museum->status === MuseumStatus::DRAFT;
    }

    /**
     * Organizer mengajukan museum untuk verifikasi (FR-ORG-12).
     */
    public function submitForReview(User $user, Museum $museum): bool
    {
        if (! $this->ownsMuseum($user, $museum)) {
            return false;
        }

        return $museum->status->isSubmittable();
    }

    /**
     * Kelola galeri, jam operasional, dan tanggal tutup.
     */
    public function manageContent(User $user, Museum $museum): bool
    {
        return $this->update($user, $museum);
    }

    /**
     * Verifikasi dan moderasi museum hanya untuk Admin (FR-ADM-04).
     */
    public function review(User $user, Museum $museum): bool
    {
        return $user->isGlobalAdmin();
    }

    public function publish(User $user, Museum $museum): bool
    {
        return $user->isGlobalAdmin()
            && $museum->status === MuseumStatus::PENDING_REVIEW;
    }

    private function ownsMuseum(User $user, Museum $museum): bool
    {
        $profileId = $user->organizerProfile?->id;

        return $profileId !== null
            && (int) $museum->organizer_profile_id === (int) $profileId;
    }
}
