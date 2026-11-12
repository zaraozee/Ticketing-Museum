<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

/**
 * Otorisasi booking (BR-02).
 *
 * Booking hanya boleh dilihat/diubah oleh pemiliknya. Admin platform boleh
 * membaca untuk keperluan antrean anomali (FR-PAY-07), tetapi **tidak** dapat
 * mengubah status pembayaran dari sini (BR-10).
 */
class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->isGlobalAdmin()) {
            return true;
        }

        return (int) $booking->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Membatalkan booking `pending_payment` hanya oleh pemilik.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        return (int) $booking->user_id === (int) $user->id;
    }

    public function update(User $user, Booking $booking): bool
    {
        return (int) $booking->user_id === (int) $user->id;
    }

    public function delete(User $user, Booking $booking): bool
    {
        // BR-18: data transaksi tidak pernah dihapus keras.
        return false;
    }
}
