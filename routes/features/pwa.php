<?php

use App\Http\Controllers\PushSubscriptionController;
use App\Http\Middleware\RequireActiveSession;
use Illuminate\Support\Facades\Route;

// PWA (strona offline) i powiadomienia push (/moje-konto/powiadomienia-push; bramka access:push_notifications).
// Plik dołączany automatycznie z routes/web.php.
Route::get('/offline', [PushSubscriptionController::class, 'offline'])
    ->withoutMiddleware(RequireActiveSession::class)
    ->name('pwa.offline');

Route::prefix('moje-konto/powiadomienia-push')
    ->middleware(['auth', 'consents', 'access:push_notifications'])
    ->name('push.')
    ->group(function () {
        Route::post('/', [PushSubscriptionController::class, 'store'])->name('store');
        Route::delete('/', [PushSubscriptionController::class, 'destroy'])->name('destroy');
    });
