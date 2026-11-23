<?php

use App\Livewire\Aquarium\PlanIndex;
use App\Livewire\Aquarium\PlanShow;
use Illuminate\Support\Facades\Route;

// Plan nowego akwarium (/moje-akwarium/plany; bramka access:tank_plan).
// Plik dołączany automatycznie z routes/web.php.
Route::prefix('moje-akwarium/plany')->middleware(['auth', 'consents', 'access:tank_plan'])->name('plans.')->group(function () {
    Route::get('/', PlanIndex::class)->name('index');
    Route::get('/{plan}', PlanShow::class)->whereNumber('plan')->name('show');
});
