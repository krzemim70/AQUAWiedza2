<?php

use App\Http\Controllers\CreatorBundleController;
use Illuminate\Support\Facades\Route;

// Pakiet danych katalogu kreatora akwarium (/kreator-akwarium/pakiet/{version}.json, publiczny).
Route::get('/kreator-akwarium/pakiet/{version}.json', CreatorBundleController::class)
    ->where('version', '[a-z0-9-]+')
    ->name('creator.bundle');
