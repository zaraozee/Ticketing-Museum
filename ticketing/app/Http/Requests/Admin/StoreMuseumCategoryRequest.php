<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMuseumCategoryRequest extends FormRequest
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
        $categoryId = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'alpha_dash', Rule::unique('museum_categories', 'slug')->ignore($categoryId)],
            'parent_id' => ['nullable', 'integer', Rule::exists('museum_categories', 'id'), Rule::notIn([$categoryId])],
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
            'name.required' => 'Nama kategori wajib diisi.',
            'slug.unique' => 'Slug kategori sudah dipakai.',
            'parent_id.not_in' => 'Kategori tidak boleh menjadi induk dirinya sendiri.',
        ];
    }
}
