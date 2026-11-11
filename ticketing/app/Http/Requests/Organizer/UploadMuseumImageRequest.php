<?php

namespace App\Http\Requests\Organizer;

use App\Models\Museum;
use Illuminate\Foundation\Http\FormRequest;

class UploadMuseumImageRequest extends FormRequest
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
            // MIME nyata diperiksa lewat `mimes`; ekstensi tidak dipercaya.
            'image' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
                'dimensions:min_width=400,min_height=300,max_width=8000,max_height=8000',
            ],
            'caption' => ['nullable', 'string', 'max:190'],
            'is_cover' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Pilih berkas gambar terlebih dahulu.',
            'image.mimes' => 'Format gambar harus JPG, PNG, atau WebP.',
            'image.mimetypes' => 'Berkas yang diunggah bukan gambar yang valid.',
            'image.max' => 'Ukuran gambar maksimal 5 MB.',
            'image.dimensions' => 'Dimensi gambar minimal 400x300 dan maksimal 8000x8000 piksel.',
        ];
    }
}
