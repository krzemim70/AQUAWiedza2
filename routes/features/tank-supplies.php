<?php

use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\TankSupplyController;
use Illuminate\Support\Facades\Route;

// Wirtualne zapasy zbiornika oraz ponowne zamówienie pozycji z odebranego zamówienia.
// Plik dołączany automatycznie z routes/web.php.
Route::prefix('moje-akwarium')->middleware(['auth', 'consents', 'access:tank_supplies'])->name('aquarium.supplies.')->group(function () {
    Route::post('/zapasy', [TankSupplyController::class, 'store'])->name('store');
    Route::post('/zbiorniki/{tank}/zapasy/{supply}/zdarzenie', [TankSupplyController::class, 'event'])->whereNumber(['tank', 'supply'])->name('event');
    Route::post('/zbiorniki/{tank}/zapasy/{supply}/archiwizuj', [TankSupplyController::class, 'archive'])->whereNumber(['tank', 'supply'])->name('archive');
});

Route::post('/zamowienia/{order}/zamow-ponownie', [CustomerOrderController::class, 'reorder'])
    ->whereNumber('order')
    ->middleware(['auth', 'consents', 'access:shop_cart'])
    ->name('orders.reorder');
