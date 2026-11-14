<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\AnomalyController as AdminAnomalyController;
use App\Http\Controllers\Admin\ContentPageController as AdminContentPageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FacilityController as AdminFacilityController;
use App\Http\Controllers\Admin\MuseumCategoryController as AdminMuseumCategoryController;
use App\Http\Controllers\Admin\MuseumReviewController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Organizer\BookingController as OrganizerBookingController;
use App\Http\Controllers\Organizer\DashboardController as OrganizerDashboardController;
use App\Http\Controllers\Organizer\GateScannerController;
use App\Http\Controllers\Organizer\MuseumClosedDateController;
use App\Http\Controllers\Organizer\MuseumController as OrganizerMuseumController;
use App\Http\Controllers\Organizer\MuseumImageController;
use App\Http\Controllers\Organizer\MuseumSubmissionController;
use App\Http\Controllers\Organizer\ReportController as OrganizerReportController;
use App\Http\Controllers\Organizer\ScheduleController as OrganizerScheduleController;
use App\Http\Controllers\Organizer\ScheduleOverviewController as OrganizerScheduleOverviewController;
use App\Http\Controllers\Organizer\SlotCapacityOverrideController as OrganizerSlotCapacityOverrideController;
use App\Http\Controllers\Organizer\SlotTemplateController as OrganizerSlotTemplateController;
use App\Http\Controllers\Organizer\StaffController as OrganizerStaffController;
use App\Http\Controllers\Organizer\TicketTypeController as OrganizerTicketTypeController;
use App\Http\Controllers\Public\ContentPageController as PublicContentPageController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\ManifestController;
use App\Http\Controllers\Public\MuseumController as PublicMuseumController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\ServiceWorkerController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\TicketController as PublicTicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Publik: katalog museum (FR-MUS-01..04)
Route::get('/museums', [PublicMuseumController::class, 'index'])->name('museums.index');
Route::get('/museums/{museum}', [PublicMuseumController::class, 'show'])->name('museums.show');

// Publik: halaman bantuan, FAQ, dan kebijakan (FR-ADM-09)
Route::get('/bantuan', [PublicContentPageController::class, 'index'])->name('content.index');
Route::get('/bantuan/{contentPage}', [PublicContentPageController::class, 'show'])->name('content.show');

// Publik: sitemap untuk SEO (FR-PWA-01, NFR-16)
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Publik: robots.txt dinamis agar URL sitemap absolut (NFR-16).
Route::get('/robots.txt', RobotsController::class)->name('robots');

/*
 * PWA (Fase 8, FR-PWA-01..03).
 *
 * Manifest disajikan server agar `name` mengikuti `APP_NAME`. Service worker
 * disajikan dari root (`/sw.js`) dengan header `Service-Worker-Allowed: /`
 * supaya scope-nya mencakup seluruh aplikasi, bukan hanya `/build/`.
 */
Route::get('/manifest.webmanifest', ManifestController::class)->name('pwa.manifest');
Route::get('/sw.js', ServiceWorkerController::class)->name('pwa.service-worker');

// PWA: halaman fallback offline (FR-PWA-03)
Route::get('/offline', function () {
    return view('offline');
})->name('offline');

// PWA: daftar e-ticket offline dari IndexedDB (SYSTEM_DESIGN §15.3).
Route::get('/offline/tickets', function () {
    return view('offline-tickets');
})->name('offline.tickets');

/*
 * Webhook pembayaran (FR-PAY-02, THIRD_PARTY §1.3).
 *
 * Di luar `auth` dan tanpa CSRF: pemanggilnya server Midtrans, bukan browser.
 * Keamanan bergantung pada verifikasi signature di `WebhookProcessor`.
 */
Route::post('/webhooks/midtrans', MidtransWebhookController::class)
    ->middleware('throttle:webhook')
    ->name('webhooks.midtrans');

Route::middleware('auth')->group(function () {
    // Profile Management
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Notifications (FR-NTF-02)
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllRead');

    /*
     * Transaksi tiket: pemesanan dan tiket milik pengunjung (FR-BKG-01…09,
     * FR-TIX-03…06, FR-PAY-05).
     *
     * Ditegakkan dengan `verified` (FR-AUTH-02). Pemesanan tiket adalah
     * komitmen finansial dan tiket dikirim ke alamat email kontak; menerimanya
     * dari alamat yang belum terbukti milik pendaftar berarti tiket dapat
     * diklaim orang lain. Verifikasi juga memastikan pengingat dan e-tiket
     * benar-benar sampai.
     *
     * Profil dan notifikasi sengaja TIDAK ditegakkan: keduanya dibutuhkan
     * justru ketika verifikasi belum selesai, dan keduanya tidak menimbulkan
     * kewajiban finansial.
     */
    Route::middleware('verified')->group(function () {
        // My Tickets (FR-TIX-03...06)
        Route::get('/tickets', [PublicTicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/{uuid}', [PublicTicketController::class, 'show'])->name('tickets.show');

        Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::get('/museums/{museum}/book', [BookingController::class, 'create'])->name('bookings.create');
        Route::post('/museums/{museum}/book', [BookingController::class, 'store'])
            ->middleware('throttle:booking')
            ->name('bookings.store');
        Route::get('/bookings/{booking:uuid}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('/bookings/{booking:uuid}/pay', [BookingController::class, 'pay'])
            ->middleware('throttle:booking')
            ->name('bookings.pay');
        Route::post('/bookings/{booking:uuid}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
        // Polling status: baca-saja, tidak pernah menerima status dari klien (AC-8).
        Route::get('/bookings/{booking:uuid}/status', [BookingController::class, 'status'])->name('bookings.status');
    });

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

        // Fase 6 — Gate Scanner & Riwayat Scan
        Route::get('scanner', [GateScannerController::class, 'scanner'])->name('gate.scanner');
        Route::get('scans', [GateScannerController::class, 'history'])->name('gate.history');

        // Fase 6 — Kelola Staf Gate
        Route::get('museums/{museum}/staff', [OrganizerStaffController::class, 'index'])->name('museums.staff.index');
        Route::post('museums/{museum}/staff', [OrganizerStaffController::class, 'store'])->name('museums.staff.store');
        Route::patch('museums/{museum}/staff/{staff}', [OrganizerStaffController::class, 'update'])->name('museums.staff.update');
        Route::delete('museums/{museum}/staff/{staff}', [OrganizerStaffController::class, 'destroy'])->name('museums.staff.destroy');

        // Fase 7 — Bookings & Reports
        Route::get('bookings', [OrganizerBookingController::class, 'index'])->name('bookings.index');
        Route::get('reports', [OrganizerReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [OrganizerReportController::class, 'export'])->name('reports.export');
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

        // Fase 6 — Antrean Anomali Pembayaran
        Route::get('anomalies', [AdminAnomalyController::class, 'index'])->name('anomalies.index');
        Route::patch('anomalies/{payment}/resolve', [AdminAnomalyController::class, 'resolve'])->name('anomalies.resolve');

        // Fase 7 — Reports
        Route::get('reports', [AdminReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [AdminReportController::class, 'export'])->name('reports.export');
    });
});

require __DIR__.'/auth.php';
