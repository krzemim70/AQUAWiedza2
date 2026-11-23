<?php

use App\Http\Controllers\OfferImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('panel-sklepu/{shop}/import-ofert')->where(['shop' => '[0-9]+'])
    ->middleware(['auth', 'access:shop_panel'])->name('shop.offer-import.')->group(function () {
        Route::get('/wzor', [OfferImportController::class, 'template'])->name('template');
        Route::post('/sprawdz', [OfferImportController::class, 'preview'])->name('preview');
        Route::post('/{batch}/zapisz', [OfferImportController::class, 'apply'])->whereNumber('batch')->name('apply');
        Route::get('/{batch}/bledy', [OfferImportController::class, 'errors'])->whereNumber('batch')->name('errors');
    });
