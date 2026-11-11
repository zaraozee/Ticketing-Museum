<?php

namespace App\Http\Requests\Admin;

use App\Models\Museum;
use Illuminate\Foundation\Http\FormRequest;

class ReviewMuseumRequest extends FormRequest
{
    public function authorize(): bool
    {
        $museum = $this->route('museum');

        return $museum instanceof Museum
            && ($this->user()?->can('review', $museum) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject,suspend,restore'],
            'note' => ['required_if:decision,reject,suspend', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Keputusan verifikasi wajib dipilih.',
            'decision.in' => 'Keputusan verifikasi tidak valid.',
            'note.required_if' => 'Alasan wajib diisi saat menolak atau menangguhkan museum.',
        ];
    }
}
