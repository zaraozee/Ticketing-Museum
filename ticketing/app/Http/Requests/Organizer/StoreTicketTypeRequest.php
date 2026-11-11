<?php

namespace App\Http\Requests\Organizer;

use App\Models\Museum;
use App\Models\TicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi jenis tiket (FR-ORG-06).
 */
class StoreTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manageContent', $this->route('museum')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Museum|null $museum */
        $museum = $this->route('museum');

        /** @var TicketType|null $ticketType */
        $ticketType = $this->route('ticketType');

        return [
            'name' => [
                'required', 'string', 'max:100',
                // Nama unik per museum selama belum dihapus (ERD §4).
                Rule::unique('ticket_types', 'name')
                    ->where('museum_id', $museum?->id)
                    ->whereNull('deleted_at')
                    ->ignore($ticketType?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            // Harga integer rupiah; batas atas menjaga nilai tetap wajar.
            'price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'min_age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'max_age' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:min_age'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama jenis tiket wajib diisi.',
            'name.unique' => 'Nama jenis tiket tersebut sudah dipakai museum ini.',
            'price.required' => 'Harga tiket wajib diisi.',
            'price.integer' => 'Harga tiket harus berupa angka bulat rupiah.',
            'price.min' => 'Harga tiket tidak boleh negatif.',
            'max_age.gte' => 'Usia maksimum tidak boleh lebih kecil dari usia minimum.',
        ];
    }
}
