<?php

use App\Http\Controllers\RecordWatchController;
use App\Http\Middleware\RequireActiveSession;
use Illuminate\Support\Facades\Route;

Route::prefix('moje-konto/obserwowane')->name('watches.')->middleware(['auth', 'consents', 'access:record_watch'])->group(function () {
    Route::get('/', [RecordWatchController::class, 'index'])->name('index');
    Route::post('/powiadomienia', [RecordWatchController::class, 'updatePreference'])->name('preference');
    Route::post('/{area}/{id}', [RecordWatchController::class, 'store'])->where('id', '[A-Za-z0-9_-]{1,64}')->name('store');
    Route::delete('/{watchId}', [RecordWatchController::class, 'destroy'])->whereNumber('watchId')->name('destroy');
});

Route::get('/obserwowane/wypisz/{watchId}/{activation}', [RecordWatchController::class, 'unsubscribe'])
    ->whereNumber('watchId')->whereUuid('activation')->middleware('signed')
    ->withoutMiddleware(RequireActiveSession::class)->name('watches.unsubscribe');
