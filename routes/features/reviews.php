<?php

use App\Http\Controllers\ShopReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('zamowienia/{order}/opinia')->whereNumber('order')
    ->middleware(['auth', 'consents', 'access:shop_cart'])->name('orders.review.')->group(function () {
        Route::get('/', [ShopReviewController::class, 'create'])->name('create');
        Route::post('/', [ShopReviewController::class, 'store'])->name('store');
    });

Route::get('/sklepy/{shop}/opinie', [ShopReviewController::class, 'index'])
    ->whereNumber('shop')->middleware('access:public_offers')->name('shops.reviews.index');
