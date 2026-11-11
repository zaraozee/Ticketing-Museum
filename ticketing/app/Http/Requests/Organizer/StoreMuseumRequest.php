<?php

namespace App\Http\Requests\Organizer;

use App\Enums\BookingMode;
use App\Models\Museum;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMuseumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Museum::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'category_id' => ['required', 'integer', Rule::exists('museum_categories', 'id')->where('is_active', true)],
            'description' => ['nullable', 'string', 'max:5000'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'booking_mode' => ['nullable', Rule::enum(BookingMode::class)],
            'max_tickets_per_booking' => ['nullable', 'integer', 'min:1', 'max:50'],
            'entry_early_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'entry_late_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'youtube_url' => ['nullable', 'string', 'max:255', $this->youtubeRule()],
            'facility_ids' => ['nullable', 'array'],
            'facility_ids.*' => ['integer', Rule::exists('facilities', 'id')->where('is_active', true)],
            'operating_hours' => ['nullable', 'array', 'max:7'],
            'operating_hours.*.day_of_week' => ['required', 'integer', 'between:0,6', 'distinct'],
            'operating_hours.*.open_time' => ['nullable', 'date_format:H:i'],
            'operating_hours.*.close_time' => ['nullable', 'date_format:H:i', 'after:operating_hours.*.open_time'],
            'operating_hours.*.is_closed' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama museum wajib diisi.',
            'category_id.required' => 'Kategori museum wajib dipilih.',
            'category_id.exists' => 'Kategori yang dipilih tidak valid.',
            'latitude.between' => 'Latitude harus di antara -90 dan 90.',
            'longitude.between' => 'Longitude harus di antara -180 dan 180.',
            'youtube_url.url' => 'Tautan YouTube tidak valid.',
            'operating_hours.*.close_time.after' => 'Jam tutup harus setelah jam buka.',
            'operating_hours.*.day_of_week.distinct' => 'Hari operasional tidak boleh duplikat.',
        ];
    }

    /**
     * Ekstrak ID video YouTube (THIRD_PARTY §6) — HTML/iframe pengguna tidak pernah disimpan.
     */
    public function youtubeVideoId(): ?string
    {
        $url = trim((string) $this->input('youtube_url'));

        if ($url === '') {
            return null;
        }

        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url) === 1) {
            return $url;
        }

        if (preg_match('~(?:youtu\.be/|v=|/embed/|/shorts/|/live/)([A-Za-z0-9_-]{11})~', $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function youtubeRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || trim((string) $value) === '') {
                return;
            }

            if ($this->youtubeVideoId() === null) {
                $fail('Tautan YouTube tidak valid. Gunakan tautan video YouTube.');
            }
        };
    }
}
