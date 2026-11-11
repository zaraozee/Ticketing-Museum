<?php

namespace App\Http\Requests\Organizer;

use App\Models\Museum;

class UpdateMuseumRequest extends StoreMuseumRequest
{
    public function authorize(): bool
    {
        $museum = $this->route('museum');

        return $museum instanceof Museum
            && ($this->user()?->can('update', $museum) ?? false);
    }
}
