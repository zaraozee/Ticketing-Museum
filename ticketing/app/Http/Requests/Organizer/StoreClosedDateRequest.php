<?php

namespace App\Http\Requests\Organizer;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Museum;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreClosedDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $museum = $this->route('museum');

        return $museum instanceof Museum
            && ($this->user()?->can('manageContent', $museum) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'closed_on' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:today',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $museum = $this->route('museum');

                    if (! $museum instanceof Museum) {
                        return;
                    }

                    if ($museum->closedDates()->whereDate('closed_on', $value)->exists()) {
                        $fail('Tanggal tersebut sudah ditandai tutup.');
                    }

                    /*
                     * Skenario 12 (§19): menutup tanggal yang sudah memiliki
                     * booking aktif akan membatalkan kunjungan yang sudah
                     * dibayar. Tolak dengan peringatan agar pengelola
                     * menghubungi pengunjung lebih dulu, alih-alih diam-diam
                     * membuat tiket yang tidak dapat dipakai.
                     */
                    $activeBookings = Booking::query()
                        ->where('museum_id', $museum->id)
                        ->whereDate('visit_date_snapshot', $value)
                        ->whereIn('status', [
                            BookingStatus::PENDING_PAYMENT,
                            BookingStatus::CONFIRMED,
                        ])
                        ->count();

                    if ($activeBookings > 0) {
                        $fail("Tanggal tersebut sudah memiliki {$activeBookings} booking aktif. Batalkan atau jadwalkan ulang booking tersebut lebih dulu.");
                    }
                },
            ],
            'reason' => ['nullable', 'string', 'max:190'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'closed_on.required' => 'Tanggal tutup wajib diisi.',
            'closed_on.after_or_equal' => 'Tanggal tutup tidak boleh di masa lalu.',
        ];
    }
}
