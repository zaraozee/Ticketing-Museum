<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContentPageType;
use App\Models\ContentPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContentPageRequest extends FormRequest
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
        $page = $this->route('contentPage');
        $pageId = $page instanceof ContentPage ? $page->id : null;

        return [
            'title' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:200', 'alpha_dash', Rule::unique('content_pages', 'slug')->ignore($pageId)],
            'type' => ['required', Rule::enum(ContentPageType::class)],
            'content' => ['nullable', 'string', 'max:100000'],
            'is_published' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Judul halaman wajib diisi.',
            'type.required' => 'Jenis halaman wajib dipilih.',
        ];
    }
}
