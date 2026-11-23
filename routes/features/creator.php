<?php

use App\Http\Controllers\AquariumCreatorController;
use App\Http\Controllers\CreatorBundleController;
use Illuminate\Support\Facades\Route;

// Publiczny kreator akwarium (/kreator-akwarium) i pakiet danych katalogu (/kreator-akwarium/pakiet/{version}.json).
Route::get('/kreator-akwarium', [AquariumCreatorController::class, 'show'])
    ->name('creator.show');

Route::get('/kreator-akwarium/pakiet/{version}.json', CreatorBundleController::class)
    ->where('version', '[a-z0-9-]+')
    ->name('creator.bundle');
