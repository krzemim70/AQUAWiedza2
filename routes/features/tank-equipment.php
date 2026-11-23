<?php

use App\Http\Controllers\TankEquipmentController;
use Illuminate\Support\Facades\Route;

Route::prefix('moje-akwarium')->middleware(['auth', 'consents', 'access:tank_view'])->name('aquarium.equipment.')->group(function () {
    Route::post('/wyposazenie', [TankEquipmentController::class, 'store'])->name('store');
    Route::patch('/zbiorniki/{tank}/wyposazenie/{equipment}', [TankEquipmentController::class, 'update'])->whereNumber(['tank', 'equipment'])->name('update');
    Route::delete('/zbiorniki/{tank}/wyposazenie/{equipment}', [TankEquipmentController::class, 'destroy'])->whereNumber(['tank', 'equipment'])->name('destroy');
});
