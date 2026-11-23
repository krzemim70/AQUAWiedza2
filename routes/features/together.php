<?php

use App\Http\Controllers\TogetherController;
use Illuminate\Support\Facades\Route;

// Publiczne „Czy te ryby mogą być razem?” (/doradca/czy-razem; bramka access:public_together).
Route::get('/doradca/czy-razem', TogetherController::class)
    ->middleware('access:public_together')
    ->name('advisor.together');
