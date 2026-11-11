<?php

namespace App\Services\Museum;

use App\Enums\MuseumStatus;
use App\Models\Museum;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alur status museum (BR-21, D-22).
 *
 * draft -> pending_review -> published | rejected
 * published -> suspended | archived
 * rejected -> pending_review (ajukan ulang)
 */
class MuseumPublishService
{
    /**
     * Syarat minimum pengajuan verifikasi (ROADMAP Fase 3 AC-3).
     *
     * @return array<int, string> daftar alasan penolakan; kosong berarti lengkap
     */
    public function submissionProblems(Museum $museum): array
    {
        $problems = [];

        if ($museum->images()->count() === 0) {
            $problems[] = 'Museum harus memiliki minimal satu foto.';
        }

        if ($museum->operatingHours()->where('is_closed', false)->count() === 0) {
            $problems[] = 'Jam operasional museum belum diisi.';
        }

        if (! $museum->hasCoordinates()) {
            $problems[] = 'Koordinat lokasi museum (latitude dan longitude) belum diisi.';
        }

        if ($museum->description === null || trim($museum->description) === '') {
            $problems[] = 'Deskripsi museum belum diisi.';
        }

        return $problems;
    }

    public function submit(Museum $museum): Museum
    {
        $problems = $this->submissionProblems($museum);

        if ($problems !== []) {
            // Inertia hanya meneruskan pesan pertama dari tiap kunci error,
            // sehingga daftar masalah digabung agar seluruhnya sampai ke UI.
            throw ValidationException::withMessages([
                'submission' => implode(' | ', $problems),
            ]);
        }

        return DB::transaction(function () use ($museum): Museum {
            $museum->forceFill([
                'status' => MuseumStatus::PENDING_REVIEW,
                'submitted_at' => now(),
                'rejected_at' => null,
                'rejection_note' => null,
            ])->save();

            ActivityLogger::log('museum.submitted', $museum, [
                'status' => MuseumStatus::PENDING_REVIEW->value,
            ]);

            return $museum->refresh();
        });
    }

    public function approve(Museum $museum, User $admin): Museum
    {
        if ($museum->status !== MuseumStatus::PENDING_REVIEW) {
            throw ValidationException::withMessages([
                'status' => 'Hanya museum berstatus menunggu verifikasi yang dapat disetujui.',
            ]);
        }

        return DB::transaction(function () use ($museum, $admin): Museum {
            $museum->forceFill([
                'status' => MuseumStatus::PUBLISHED,
                'published_at' => now(),
                'rejected_at' => null,
                'rejection_note' => null,
            ])->save();

            ActivityLogger::log('museum.published', $museum, [
                'status' => MuseumStatus::PUBLISHED->value,
                'approved_by' => $admin->id,
            ]);

            return $museum->refresh();
        });
    }

    public function reject(Museum $museum, User $admin, string $note): Museum
    {
        if ($museum->status !== MuseumStatus::PENDING_REVIEW) {
            throw ValidationException::withMessages([
                'status' => 'Hanya museum berstatus menunggu verifikasi yang dapat ditolak.',
            ]);
        }

        return DB::transaction(function () use ($museum, $admin, $note): Museum {
            $museum->forceFill([
                'status' => MuseumStatus::REJECTED,
                'rejected_at' => now(),
                'rejection_note' => $note,
            ])->save();

            ActivityLogger::log('museum.rejected', $museum, [
                'status' => MuseumStatus::REJECTED->value,
                'note' => $note,
                'rejected_by' => $admin->id,
            ]);

            return $museum->refresh();
        });
    }

    public function suspend(Museum $museum, User $admin, string $reason): Museum
    {
        if ($museum->status !== MuseumStatus::PUBLISHED) {
            throw ValidationException::withMessages([
                'status' => 'Hanya museum tayang yang dapat ditangguhkan.',
            ]);
        }

        return DB::transaction(function () use ($museum, $admin, $reason): Museum {
            $museum->forceFill([
                'status' => MuseumStatus::SUSPENDED,
                'rejection_note' => $reason,
            ])->save();

            ActivityLogger::log('museum.suspended', $museum, [
                'reason' => $reason,
                'by' => $admin->id,
            ]);

            return $museum->refresh();
        });
    }

    public function restore(Museum $museum, User $admin): Museum
    {
        if ($museum->status !== MuseumStatus::SUSPENDED) {
            throw ValidationException::withMessages([
                'status' => 'Hanya museum yang ditangguhkan yang dapat diaktifkan kembali.',
            ]);
        }

        return DB::transaction(function () use ($museum, $admin): Museum {
            $museum->forceFill([
                'status' => MuseumStatus::PUBLISHED,
                'rejection_note' => null,
            ])->save();

            ActivityLogger::log('museum.restored', $museum, ['by' => $admin->id]);

            return $museum->refresh();
        });
    }
}
