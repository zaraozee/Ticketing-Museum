<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Models\Museum;
use App\Services\Museum\MuseumPublishService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MuseumSubmissionController extends Controller
{
    public function __construct(private readonly MuseumPublishService $publisher) {}

    public function store(Museum $museum): RedirectResponse
    {
        Gate::authorize('submitForReview', $museum);

        try {
            $this->publisher->submit($museum);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return back()->with('success', 'Museum diajukan untuk verifikasi Admin.');
    }
}
