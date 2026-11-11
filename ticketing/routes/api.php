<?php

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

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('museums/{museum}/availability', [AvailabilityController::class, 'month'])
        ->name('museums.availability');
    Route::get('museums/{museum}/slots', [AvailabilityController::class, 'slots'])
        ->name('museums.slots');
});
