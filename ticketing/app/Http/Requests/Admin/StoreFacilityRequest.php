<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isGlobalAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $facilityId = $this->route('facility')?->id;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('facilities', 'name')->ignore($facilityId)],
            'icon' => ['nullable', 'string', 'max:50'],
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
            'name.required' => 'Nama fasilitas wajib diisi.',
            'name.unique' => 'Fasilitas dengan nama tersebut sudah ada.',
        ];
    }
}
