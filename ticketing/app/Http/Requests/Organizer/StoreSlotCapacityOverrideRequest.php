<?php

namespace App\Http\Requests\Organizer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Override kuota untuk satu tanggal (FR-ORG-07, D-27).
 */
class StoreSlotCapacityOverrideRequest extends FormRequest
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
        return [
            'visit_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'capacity' => ['required', 'integer', 'min:0', 'max:100000'],
            'note' => ['nullable', 'string', 'max:190'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'visit_date.required' => 'Tanggal override wajib diisi.',
            'visit_date.after_or_equal' => 'Override hanya dapat dibuat untuk tanggal hari ini atau setelahnya.',
            'capacity.required' => 'Kuota override wajib diisi.',
            'capacity.min' => 'Kuota override tidak boleh negatif.',
        ];
    }
}
