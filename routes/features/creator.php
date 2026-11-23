<?php

use App\Http\Controllers\AquariumCreatorController;
use App\Http\Controllers\CreatorBundleController;
use Illuminate\Support\Facades\Route;

// Publiczny kreator akwarium (/kreator-akwarium), pakiet danych katalogu oraz punkty końcowe Kroku 6.
Route::get('/kreator-akwarium', [AquariumCreatorController::class, 'show'])
    ->name('creator.show');

Route::get('/kreator-akwarium/miasta', [AquariumCreatorController::class, 'cities'])
    ->name('creator.cities');

Route::get('/kreator-akwarium/zaloguj', [AquariumCreatorController::class, 'redirectToAuth'])
    ->name('creator.auth');

Route::get('/kreator-akwarium/pakiet/{version}.json', CreatorBundleController::class)
    ->where('version', '[a-z0-9-]+')
    ->name('creator.bundle');

Route::post('/kreator-akwarium/oferty', [AquariumCreatorController::class, 'offers'])
    ->middleware(['throttle:30,1', 'access:creator_offers'])
    ->name('creator.offers');

Route::post('/kreator-akwarium/plany', [AquariumCreatorController::class, 'savePlan'])
    ->middleware(['auth', 'consents', 'access:tank_plan'])
    ->name('creator.save-plan');

Route::post('/kreator-akwarium/koszyk', [AquariumCreatorController::class, 'addToCart'])
    ->middleware(['auth', 'consents', 'access:shop_cart'])
    ->name('creator.cart');

Route::post('/kreator-akwarium/obserwuj', [AquariumCreatorController::class, 'watch'])
    ->middleware(['auth', 'consents', 'access:record_watch'])
    ->name('creator.watch');

Route::post('/kreator-akwarium/plany/{plan}/zbiornik', [AquariumCreatorController::class, 'convert'])
    ->whereNumber('plan')
    ->middleware(['auth', 'consents', 'access:tank_plan'])
    ->name('creator.convert');
