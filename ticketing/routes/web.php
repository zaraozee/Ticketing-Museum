<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\ContentPageController as AdminContentPageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FacilityController as AdminFacilityController;
use App\Http\Controllers\Admin\MuseumCategoryController as AdminMuseumCategoryController;
use App\Http\Controllers\Admin\MuseumReviewController;
use App\Http\Controllers\Organizer\DashboardController as OrganizerDashboardController;
use App\Http\Controllers\Organizer\MuseumClosedDateController;
use App\Http\Controllers\Organizer\MuseumController as OrganizerMuseumController;
use App\Http\Controllers\Organizer\MuseumImageController;
use App\Http\Controllers\Organizer\MuseumSubmissionController;
use App\Http\Controllers\Organizer\ScheduleController as OrganizerScheduleController;
use App\Http\Controllers\Organizer\ScheduleOverviewController as OrganizerScheduleOverviewController;
use App\Http\Controllers\Organizer\SlotCapacityOverrideController as OrganizerSlotCapacityOverrideController;
use App\Http\Controllers\Organizer\SlotTemplateController as OrganizerSlotTemplateController;
use App\Http\Controllers\Organizer\TicketTypeController as OrganizerTicketTypeController;
use App\Http\Controllers\Public\ContentPageController as PublicContentPageController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\MuseumController as PublicMuseumController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Publik: katalog museum (FR-MUS-01..04)
Route::get('/museums', [PublicMuseumController::class, 'index'])->name('museums.index');
Route::get('/museums/{museum}', [PublicMuseumController::class, 'show'])->name('museums.show');

// Publik: halaman bantuan, FAQ, dan kebijakan (FR-ADM-09)
Route::get('/bantuan', [PublicContentPageController::class, 'index'])->name('content.index');
Route::get('/bantuan/{contentPage}', [PublicContentPageController::class, 'show'])->name('content.show');

Route::middleware('auth')->group(function () {
    // Profile Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Organizer Area
    Route::middleware('role:organizer')->prefix('organizer')->name('organizer.')->group(function () {
        Route::get('/', [OrganizerDashboardController::class, 'index'])->name('dashboard');

        Route::resource('museums', OrganizerMuseumController::class)->except(['show']);

        Route::post('museums/{museum}/submit', [MuseumSubmissionController::class, 'store'])
            ->name('museums.submit');

        Route::post('museums/{museum}/images', [MuseumImageController::class, 'store'])
            ->name('museums.images.store');
        Route::patch('museums/{museum}/images/{image}', [MuseumImageController::class, 'update'])
            ->name('museums.images.update');
        Route::delete('museums/{museum}/images/{image}', [MuseumImageController::class, 'destroy'])
            ->name('museums.images.destroy');

        Route::post('museums/{museum}/closed-dates', [MuseumClosedDateController::class, 'store'])
            ->name('museums.closed-dates.store');
        Route::delete('museums/{museum}/closed-dates/{closedDate}', [MuseumClosedDateController::class, 'destroy'])
            ->name('museums.closed-dates.destroy');

        // Fase 4 — jenis tiket (FR-ORG-06)
        Route::get('museums/{museum}/tickets', [OrganizerTicketTypeController::class, 'index'])
            ->name('museums.tickets');
        Route::post('museums/{museum}/ticket-types', [OrganizerTicketTypeController::class, 'store'])
            ->name('museums.ticket-types.store');
        Route::patch('museums/{museum}/ticket-types/{ticketType}', [OrganizerTicketTypeController::class, 'update'])
            ->name('museums.ticket-types.update');
        Route::delete('museums/{museum}/ticket-types/{ticketType}', [OrganizerTicketTypeController::class, 'destroy'])
            ->name('museums.ticket-types.destroy');

        // Fase 4 — template slot & jadwal (FR-ORG-07)
        Route::post('museums/{museum}/slot-templates', [OrganizerSlotTemplateController::class, 'store'])
            ->name('museums.slot-templates.store');
        Route::patch('museums/{museum}/slot-templates/{slotTemplate}', [OrganizerSlotTemplateController::class, 'update'])
            ->name('museums.slot-templates.update');
        Route::delete('museums/{museum}/slot-templates/{slotTemplate}', [OrganizerSlotTemplateController::class, 'destroy'])
            ->name('museums.slot-templates.destroy');

        Route::get('museums/{museum}/schedule', [OrganizerScheduleController::class, 'index'])
            ->name('museums.schedule');
        Route::post('museums/{museum}/schedule/generate', [OrganizerScheduleController::class, 'generate'])
            ->name('museums.schedule.generate');
        Route::patch('museums/{museum}/schedule/slots/{slot}', [OrganizerScheduleController::class, 'updateSlotCapacity'])
            ->name('museums.schedule.slots.update');

        Route::post('museums/{museum}/slot-overrides', [OrganizerSlotCapacityOverrideController::class, 'store'])
            ->name('museums.slot-overrides.store');
        Route::delete('museums/{museum}/slot-overrides/{override}', [OrganizerSlotCapacityOverrideController::class, 'destroy'])
            ->name('museums.slot-overrides.destroy');

        // Fase 4 — pintu masuk modul Tiket & Jadwal Kuota.
        Route::get('schedules', [OrganizerScheduleOverviewController::class, 'index'])
            ->name('schedules.index');
    });

    // Admin Area
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('museums', [MuseumReviewController::class, 'index'])->name('museums.index');
        Route::get('museums/{museum}', [MuseumReviewController::class, 'show'])->name('museums.show');
        Route::patch('museums/{museum}/review', [MuseumReviewController::class, 'update'])
            ->name('museums.review');

        Route::resource('categories', AdminMuseumCategoryController::class)
            ->except(['create', 'show', 'edit']);
        Route::resource('facilities', AdminFacilityController::class)
            ->except(['create', 'show', 'edit']);
        Route::resource('content-pages', AdminContentPageController::class)
            ->except(['create', 'show', 'edit'])
            // Tabel admin mengirim primary key; ContentPage memakai slug sebagai
            // route key untuk halaman publik, jadi binding admin dipatok ke id.
            ->parameters(['content-pages' => 'contentPage:id']);
    });
});

require __DIR__.'/auth.php';
