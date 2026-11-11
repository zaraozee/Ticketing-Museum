<?php

namespace App\Http\Requests\Organizer;

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
