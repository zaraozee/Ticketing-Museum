<?php

namespace App\Services\Quota;

use App\Models\VisitSlot;
use Illuminate\Support\Facades\DB;

/**
 * Penahanan dan pelepasan kuota (BR-05..BR-08, §13).
 *
 * Semua perubahan counter dilakukan dengan **satu UPDATE kondisional**:
 *
 *   UPDATE visit_slots
 *      SET reserved_count = reserved_count + :qty
 *    WHERE id = :id
 *      AND status = 'open'
 *      AND (reserved_count + sold_count + :qty) <= capacity
 *
 * PostgreSQL/MySQL mengunci baris saat UPDATE, sehingga dua transaksi yang
 * memperebutkan kuota terakhir diserialisasi: yang kedua melihat nilai terbaru,
 * kondisi WHERE gagal, dan `affected rows = 0`. Inilah yang membuat overbooking
 * tidak mungkin terjadi tanpa perlu `SELECT ... FOR UPDATE` terpisah.
 *
 * CHECK `reserved_count + sold_count <= capacity` di database adalah jaring
 * pengaman terakhir bila ada bug di lapisan aplikasi.
 */
class QuotaService
{
    /**
     * Tahan kuota untuk booking `pending_payment`.
     *
     * @return bool true bila kuota berhasil ditahan; false bila tidak cukup
     */
    public function reserve(VisitSlot $slot, int $quantity): bool
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Jumlah tiket harus minimal 1.');
        }

        $affected = DB::update(
            'UPDATE visit_slots
                SET reserved_count = reserved_count + ?, updated_at = ?
              WHERE id = ?
                AND status = ?
                AND (reserved_count + sold_count + ?) <= capacity',
            [$quantity, now(), $slot->id, 'open', $quantity],
        );

        if ($affected === 1) {
            $slot->refresh();

            return true;
        }

        $slot->refresh();

        return false;
    }

    /**
     * Lepas kuota yang sebelumnya ditahan (booking `expired`/`cancelled`).
     *
     * Berbeda dari `reserve`, pelepasan **tidak boleh gagal senyap**: bila
     * jumlah yang dilepas melebihi `reserved_count` saat ini (mis. karena
     * pelepasan ganda), nilai dijepit ke 0 agar kuota tetap kembali tersedia
     * dan counter tidak pernah negatif.
     */
    public function release(VisitSlot $slot, int $quantity): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Jumlah tiket harus minimal 1.');
        }

        DB::update(
            'UPDATE visit_slots
                SET reserved_count = CASE
                        WHEN reserved_count >= ? THEN reserved_count - ?
                        ELSE 0
                    END,
                    updated_at = ?
              WHERE id = ?',
            [$quantity, $quantity, now(), $slot->id],
        );

        $slot->refresh();
    }

    /**
     * Pindahkan kuota dari ditahan ke terjual saat pembayaran sukses (BR-07).
     *
     * Harus dipanggil di dalam transaksi yang sama dengan perubahan status
     * booking dan penerbitan tiket.
     */
    public function confirm(VisitSlot $slot, int $quantity): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Jumlah tiket harus minimal 1.');
        }

        DB::update(
            'UPDATE visit_slots
                SET reserved_count = reserved_count - ?,
                    sold_count = sold_count + ?,
                    updated_at = ?
              WHERE id = ?
                AND reserved_count >= ?',
            [$quantity, $quantity, now(), $slot->id, $quantity],
        );

        $slot->refresh();
    }

    /**
     * Ubah kapasitas slot dengan menghormati BR-20: kapasitas tidak boleh
     * diturunkan di bawah kuota yang sudah terjual + ditahan.
     *
     * @return bool true bila perubahan diterapkan
     */
    public function changeCapacity(VisitSlot $slot, int $capacity): bool
    {
        if ($capacity < 0) {
            throw new \InvalidArgumentException('Kapasitas tidak boleh negatif.');
        }

        $affected = DB::update(
            'UPDATE visit_slots
                SET capacity = ?, updated_at = ?
              WHERE id = ?
                AND ? >= (reserved_count + sold_count)',
            [$capacity, now(), $slot->id, $capacity],
        );

        $slot->refresh();

        return $affected === 1;
    }
}
