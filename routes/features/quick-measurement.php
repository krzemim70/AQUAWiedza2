<?php

use App\Http\Controllers\QuickMeasurementController;
use Illuminate\Support\Facades\Route;

// Szybki pomiar (/moje-akwarium/szybki-pomiar; bramka access:quick_measurement).
// Plik dołączany automatycznie z routes/web.php.
Route::prefix('moje-akwarium')->middleware(['auth', 'consents', 'access:quick_measurement'])->group(function () {
    Route::get('/szybki-pomiar', [QuickMeasurementController::class, 'show'])->name('aquarium.quick-measurement');
    Route::post('/szybki-pomiar', [QuickMeasurementController::class, 'store'])->name('aquarium.quick-measurement.store');
});
