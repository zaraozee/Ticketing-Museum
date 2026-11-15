<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\MuseumStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InsufficientQuotaException;
use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Museum;
use App\Models\TicketType;
use App\Models\User;
use App\Models\VisitSlot;
use App\Services\Audit\ActivityLogger;
use App\Services\Notification\BookingNotifier;
use App\Services\Quota\QuotaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pembuatan dan pembatalan booking (FR-BKG-01…09).
 *
 * Kuota ditahan lewat `QuotaService::reserve()` yang memakai satu UPDATE
 * kondisional atomik; bila kuota tidak cukup, tidak ada baris yang dibuat.
 */
class BookingService
{
    public function __construct(
        private readonly QuotaService $quota,
        private readonly BookingNotifier $notifier,
    ) {}

    /**
     * Booking yang baru kedaluwarsa dan menunggu dinotifikasi.
     *
     * @var array<int, Booking>
     */
    private array $expiredNotifications = [];

    /**
     * Buat booking `pending_payment` dan tahan kuota (BR-05).
     *
     * @param  array<int, array<string, mixed>>  $items  input mentah dari request
     *
     * @throws ValidationException
     */
    public function create(
        User $user,
        Museum $museum,
        VisitSlot $slot,
        array $items,
        string $contactName,
        string $contactEmail,
        string $contactPhone,
    ): Booking {
        // BR-01: hanya museum published yang dapat dipesan.
        if ($museum->status !== MuseumStatus::PUBLISHED) {
            throw ValidationException::withMessages([
                'museum' => 'Museum ini belum dapat dipesan.',
            ]);
        }

        $this->ensureSlotBelongsToMuseum($museum, $slot);
        $this->ensureSlotIsBookable($museum, $slot);

        $prepared = $this->prepareItems($museum, $items);

        $totalQuantity = array_sum(array_column($prepared, 'quantity'));

        $maxPerBooking = $museum->max_tickets_per_booking
            ?? (int) AppSetting::get('max_tickets_per_booking', '10');

        if ($totalQuantity > $maxPerBooking) {
            throw ValidationException::withMessages([
                'quantity' => "Maksimal {$maxPerBooking} tiket per pemesanan.",
            ]);
        }

        $totalAmount = array_sum(array_column($prepared, 'subtotal'));

        return DB::transaction(function () use (
            $user, $museum, $slot, $prepared, $totalQuantity, $totalAmount,
            $contactName, $contactEmail, $contactPhone
        ): Booking {
            // BR-05: tahan kuota lebih dulu. Gagal di sini berarti berhenti —
            // tidak ada booking yang dibuat setengah jadi.
            if (! $this->quota->reserve($slot, $totalQuantity)) {
                $remaining = $slot->fresh()->remaining();

                // AC-1: kondisi ini wajib menghasilkan 409, bukan redirect biasa.
                throw new InsufficientQuotaException(
                    remaining: $remaining,
                    message: "Kuota tidak mencukupi. Sisa {$remaining} tiket.",
                );
            }

            $booking = Booking::create([
                'user_id' => $user->id,
                'museum_id' => $museum->id,
                'visit_slot_id' => $slot->id,
                'status' => BookingStatus::PENDING_PAYMENT,
                'total_amount' => $totalAmount,
                'contact_name' => $contactName,
                'contact_email' => $contactEmail,
                'contact_phone' => $contactPhone,
                'museum_name_snapshot' => $museum->name,
                'visit_date_snapshot' => $slot->visit_date->toDateString(),
                'slot_label_snapshot' => $slot->label(),
                // BR-06: batas bayar dihitung dari waktu pembuatan booking.
                'expires_at' => now()->addMinutes($this->paymentWindowMinutes()),
            ]);

            foreach ($prepared as $row) {
                BookingItem::create([
                    'booking_id' => $booking->id,
                    'ticket_type_id' => $row['ticket_type_id'],
                    'ticket_name_snapshot' => $row['name'],
                    'unit_price' => $row['unit_price'],
                    'quantity' => $row['quantity'],
                    'subtotal' => $row['subtotal'],
                ]);
            }

            ActivityLogger::log('booking.created', $booking, [
                'museum_id' => $museum->id,
                'visit_slot_id' => $slot->id,
                'quantity' => $totalQuantity,
                'total_amount' => $totalAmount,
            ], $user->id);

            return $booking;
        });
    }

    /**
     * Batalkan booking `pending_payment` milik pengguna (FR-BKG-07, BR-08).
     *
     * Kuota dilepas sehingga tiket dapat dijual lagi.
     */
    public function cancel(Booking $booking, ?User $actor = null): bool
    {
        if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
            return false;
        }

        return DB::transaction(function () use ($booking, $actor): bool {
            $affected = DB::table('bookings')
                ->where('id', $booking->id)
                // Guard status agar dua pembatalan bersamaan tidak melepas kuota dua kali.
                ->where('status', BookingStatus::PENDING_PAYMENT->value)
                ->update([
                    'status' => BookingStatus::CANCELLED->value,
                    'cancelled_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($affected !== 1) {
                return false;
            }

            $quantity = (int) DB::table('booking_items')
                ->where('booking_id', $booking->id)
                ->sum('quantity');

            if ($quantity > 0) {
                $slot = VisitSlot::query()->find($booking->visit_slot_id);

                if ($slot !== null) {
                    $this->quota->release($slot, $quantity);
                }
            }

            $this->closeActivePayments($booking, PaymentStatus::CANCELLED);

            ActivityLogger::log('booking.cancelled', $booking, [
                'quantity' => $quantity,
            ], $actor?->id);

            $booking->refresh();

            return true;
        });
    }

    /**
     * Tandai booking kedaluwarsa dan lepas kuota (BR-06, FR-PAY-06).
     *
     * Memakai guard status agar job yang berjalan bersamaan tidak melepas
     * kuota lebih dari sekali.
     */
    public function expire(Booking $booking): bool
    {
        if ($booking->status !== BookingStatus::PENDING_PAYMENT) {
            return false;
        }

        $expired = DB::transaction(function () use ($booking): bool {
            $affected = DB::table('bookings')
                ->where('id', $booking->id)
                ->where('status', BookingStatus::PENDING_PAYMENT->value)
                ->update([
                    'status' => BookingStatus::EXPIRED->value,
                    'expired_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($affected !== 1) {
                return false;
            }

            $quantity = (int) DB::table('booking_items')
                ->where('booking_id', $booking->id)
                ->sum('quantity');

            if ($quantity > 0) {
                $slot = VisitSlot::query()->find($booking->visit_slot_id);

                if ($slot !== null) {
                    $this->quota->release($slot, $quantity);
                }
            }

            $this->closeActivePayments($booking, PaymentStatus::EXPIRED);

            ActivityLogger::log('booking.expired', $booking, ['quantity' => $quantity]);

            $booking->refresh();

            // Diberitahukan setelah transaksi selesai agar pengguna tidak
            // menerima notifikasi untuk booking yang urung kedaluwarsa.
            // `DB::afterCommit()` tidak dipakai: callback-nya hanya berbunyi
            // saat level transaksi mencapai nol, sedangkan `RefreshDatabase`
            // membungkus tiap tes dengan transaksi.
            $this->expiredNotifications[] = $booking;

            return true;
        });

        $this->flushExpiredNotifications();

        return $expired;
    }

    /**
     * Kirim notifikasi booking kedaluwarsa yang terkumpul.
     */
    private function flushExpiredNotifications(): void
    {
        foreach ($this->expiredNotifications as $expiredBooking) {
            $this->notifier->expired($expiredBooking);
        }

        $this->expiredNotifications = [];
    }

    /**
     * Tutup percobaan pembayaran yang masih aktif milik booking.
     *
     * Tanpa ini payment tertinggal `pending` selamanya setelah booking-nya
     * berakhir: antrean anomali admin terisi pembayaran yang sudah tidak
     * mungkin dibayar, dan `ops:health` melaporkan "pembayaran menggantung"
     * secara permanen sehingga alarm AC-5 kehilangan maknanya.
     *
     * Hanya status aktif yang disentuh. `paid` sengaja dibiarkan: pembayaran
     * yang masuk setelah booking berakhir ditangani BR-23 (antrean anomali),
     * bukan ditutup diam-diam.
     */
    private function closeActivePayments(Booking $booking, PaymentStatus $target): void
    {
        DB::table('payments')
            ->where('booking_id', $booking->id)
            ->whereIn('status', [PaymentStatus::CREATED->value, PaymentStatus::PENDING->value])
            ->update([
                'status' => $target->value,
                'updated_at' => now(),
            ]);
    }

    /**
     * Validasi item dan bekukan harga saat ini ke dalam snapshot (BR-19).
     *
     * Menerima input mentah dari request: tipe `mixed` disengaja karena
     * validasi bentuk dan isinya adalah tanggung jawab metode ini, bukan
     * pemanggil.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{ticket_type_id: int, name: string, unit_price: int, quantity: int, subtotal: int}>
     */
    private function prepareItems(Museum $museum, array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Pilih minimal satu jenis tiket.',
            ]);
        }

        $prepared = [];

        foreach ($items as $row) {
            $quantity = (int) ($row['quantity'] ?? 0);

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'items' => 'Jumlah tiket setiap jenis minimal 1.',
                ]);
            }

            $ticketType = TicketType::query()
                ->where('id', (int) ($row['ticket_type_id'] ?? 0))
                ->where('museum_id', $museum->id)
                ->first();

            if ($ticketType === null) {
                throw ValidationException::withMessages([
                    'items' => 'Jenis tiket tidak ditemukan pada museum ini.',
                ]);
            }

            if (! $ticketType->is_active) {
                throw ValidationException::withMessages([
                    'items' => "Jenis tiket \"{$ticketType->name}\" sedang tidak dijual.",
                ]);
            }

            $prepared[] = [
                'ticket_type_id' => $ticketType->id,
                'name' => $ticketType->name,
                'unit_price' => $ticketType->price,
                'quantity' => $quantity,
                'subtotal' => $ticketType->price * $quantity,
            ];
        }

        return $prepared;
    }

    /**
     * BR-04: hanya tanggal dalam jendela pemesanan dan sebelum batas penjualan.
     */
    private function ensureSlotIsBookable(Museum $museum, VisitSlot $slot): void
    {
        if (! $slot->status->isBookable()) {
            throw ValidationException::withMessages([
                'visit_slot_id' => 'Slot ini sudah tidak dibuka untuk pemesanan.',
            ]);
        }

        $date = Carbon::parse($slot->visit_date->toDateString());

        if ($date->isBefore(Carbon::today())) {
            throw ValidationException::withMessages([
                'visit_slot_id' => 'Tanggal kunjungan sudah lewat.',
            ]);
        }

        // BR-04: jendela pemesanan adalah setelan global, bukan kolom museum.
        $windowDays = (int) AppSetting::get('booking_window_days', '60');

        if ($date->greaterThan(Carbon::today()->addDays($windowDays))) {
            throw ValidationException::withMessages([
                'visit_slot_id' => "Pemesanan hanya dibuka sampai {$windowDays} hari ke depan.",
            ]);
        }

        // BR-04: penjualan ditutup beberapa menit sebelum slot dimulai.
        if ($slot->start_time !== null) {
            $cutoff = (int) AppSetting::get('sales_cutoff_minutes', '30');

            $slotStart = Carbon::parse(
                $slot->visit_date->toDateString().' '.$slot->start_time,
                $museum->timezone ?? config('app.timezone'),
            );

            if (now()->greaterThanOrEqualTo($slotStart->copy()->subMinutes($cutoff))) {
                throw ValidationException::withMessages([
                    'visit_slot_id' => "Penjualan tiket ditutup {$cutoff} menit sebelum slot dimulai.",
                ]);
            }
        }

        /*
         * Tanggal tutup museum (FR-SCH-04).
         *
         * Tanpa pemeriksaan ini, menandai tanggal tutup hanya memengaruhi
         * pembuatan slot dan tampilan kalender: slot yang sudah terlanjur
         * dibuat tetap dapat dipesan, sehingga pengunjung membayar kunjungan
         * ke museum yang tutup. Pemesanan publik adalah jalur yang harus
         * menolaknya, bukan sekadar label di kalender.
         */
        $isClosed = $museum->closedDates()
            ->whereDate('closed_on', $date->toDateString())
            ->exists();

        if ($isClosed) {
            throw ValidationException::withMessages([
                'visit_slot_id' => 'Museum tutup pada tanggal tersebut. Silakan pilih tanggal lain.',
            ]);
        }
    }

    private function ensureSlotBelongsToMuseum(Museum $museum, VisitSlot $slot): void
    {
        if ((int) $slot->museum_id !== (int) $museum->id) {
            throw ValidationException::withMessages([
                'visit_slot_id' => 'Slot bukan milik museum ini.',
            ]);
        }
    }

    /**
     * D-20: batas waktu pembayaran default 15 menit, dapat diubah Admin.
     */
    private function paymentWindowMinutes(): int
    {
        return (int) AppSetting::get('payment_window_minutes', '15');
    }
}
