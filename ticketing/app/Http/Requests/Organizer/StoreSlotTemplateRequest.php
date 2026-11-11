<?php

namespace App\Http\Requests\Organizer;

use App\Models\Museum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validasi template slot (FR-ORG-07).
 *
 * Untuk museum `booking_mode = daily`, jam mulai/selesai harus kosong karena
 * satu slot mewakili seluruh hari. Untuk `timed_slot`, keduanya wajib dan
 * `end_time` harus setelah `start_time`.
 */
class StoreSlotTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Museum|null $museum */
                $museum = $this->route('museum');

                $isDaily = $museum?->booking_mode->value === 'daily';

                if ($isDaily && ($this->input('start_time') !== null || $this->input('end_time') !== null)) {
                    $validator->errors()->add(
                        'start_time',
                        'Museum dengan mode harian tidak memakai jam slot; kosongkan jam mulai dan selesai.',
                    );

                    return;
                }

                if (! $isDaily && $this->input('start_time') === null) {
                    $validator->errors()->add(
                        'start_time',
                        'Jam mulai slot wajib diisi untuk museum dengan slot waktu.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama slot wajib diisi.',
            'capacity.required' => 'Kuota slot wajib diisi.',
            'capacity.min' => 'Kuota slot minimal 1.',
            'end_time.after' => 'Jam selesai harus setelah jam mulai.',
        ];
    }
}
