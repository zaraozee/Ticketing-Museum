<?php

namespace App\Http\Controllers\Organizer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organizer\StoreClosedDateRequest;
use App\Models\Museum;
use App\Models\MuseumClosedDate;
use App\Services\Museum\MuseumService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MuseumClosedDateController extends Controller
{
    public function __construct(private readonly MuseumService $museums) {}

    public function store(StoreClosedDateRequest $request, Museum $museum): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);

        $this->museums->addClosedDate(
            $museum,
            (string) $request->validated('closed_on'),
            $request->validated('reason'),
        );

        return back()->with('success', 'Tanggal tutup ditambahkan.');
    }

    public function destroy(Museum $museum, MuseumClosedDate $closedDate): RedirectResponse
    {
        Gate::authorize('manageContent', $museum);
        abort_unless((int) $closedDate->museum_id === (int) $museum->id, 404);

        $this->museums->removeClosedDate($museum, $closedDate);

        return back()->with('success', 'Tanggal tutup dihapus.');
    }
}
