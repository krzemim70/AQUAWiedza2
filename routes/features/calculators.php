<?php

use App\Http\Controllers\CalculatorController;
use Illuminate\Support\Facades\Route;

// Kalkulatory (/kalkulatory, publiczne; bramka access:calculators).
// Plik dołączany automatycznie z routes/web.php; trasy dopisuje pakiet tej funkcji.
Route::prefix('kalkulatory')->middleware('access:calculators')->name('calculators.')->group(function () {
    Route::get('/', [CalculatorController::class, 'index'])->name('index');
    Route::get('/litraz', [CalculatorController::class, 'volume'])->name('volume');
    Route::post('/litraz/zapisz', [CalculatorController::class, 'saveVolume'])->middleware(['auth', 'consents'])->name('volume.save');
    Route::get('/dawkowanie', [CalculatorController::class, 'dosage'])->name('dosage');
    Route::get('/co2', [CalculatorController::class, 'co2'])->name('co2');
    Route::get('/obsada', [CalculatorController::class, 'stocking'])->name('stocking');
});
