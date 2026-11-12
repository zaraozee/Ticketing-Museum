<?php

use App\Http\Controllers\Api\TicketTokenController;
use App\Http\Controllers\Gate\ScanController;
use App\Http\Controllers\Public\AvailabilityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API publik (versi di path, SYSTEM_DESIGN §9.3)
|--------------------------------------------------------------------------
|
| Endpoint ketersediaan dipakai kalender pemesanan (FR-BKG-02..04).
| Semua rute di sini bersifat publik dan hanya melayani museum `published`.
|
*/

Route::middleware(['web'])->prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('museums/{museum}/availability', [AvailabilityController::class, 'month'])
        ->name('museums.availability');
    Route::get('museums/{museum}/slots', [AvailabilityController::class, 'slots'])
        ->name('museums.slots');

    // Ticket QR token (FR-TIX-02, FR-TIX-05)
    Route::middleware('auth')->get('tickets/{uuid}/token', [TicketTokenController::class, 'show'])
        ->name('tickets.token');

    // Gate Scanner API (FR-GATE-01...06)
    Route::middleware('auth')->prefix('gate')->name('gate.')->group(function (): void {
        Route::get('museums', [ScanController::class, 'museums'])->name('museums');
        Route::post('scans', [ScanController::class, 'scan'])->name('scans.store');
        Route::get('scans', [ScanController::class, 'history'])->name('scans.index');
    });
});
