<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi checkout (FR-BKG-01, FR-BKG-05, FR-BKG-06).
 *
 * Batas jumlah tiket per jenis diperiksa di sini; batas total per booking
 * diperiksa di `BookingService` karena bergantung pada setelan museum.
 */
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visit_slot_id' => ['required', 'integer', 'exists:visit_slots,id'],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:190'],
            'contact_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_id' => ['required', 'integer', 'distinct', 'exists:ticket_types,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Pilih minimal satu jenis tiket.',
            'items.*.quantity.min' => 'Jumlah tiket minimal 1.',
            'items.*.ticket_type_id.distinct' => 'Satu jenis tiket hanya boleh dipilih sekali.',
            'contact_phone.regex' => 'Nomor telepon tidak valid.',
        ];
    }
}
