<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountPrivacyController;
use App\Http\Controllers\AddToTankController;
use App\Http\Controllers\AdvisorController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\DropController;
use App\Http\Controllers\GuidedAdvisorController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MailPreviewController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\TankAdvisorController;
use App\Livewire\Aquarium\RedfieldCalculator;
use App\Livewire\Aquarium\TankIndex;
use App\Livewire\Aquarium\TankShow;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/katalog/{area}/{id}', CatalogController::class)->name('catalog.show');
Route::get('/dobor', [DropController::class, 'show'])->name('drop.show');
Route::post('/dobor', [DropController::class, 'run'])->name('drop.run');
Route::get('/{page}', [PageController::class, 'show'])->whereIn('page', PageController::SLUGS)->name('page');
Route::get('/doradca', AdvisorController::class)->name('advisor');
Route::get('/doradca/moj-zbiornik', [TankAdvisorController::class, 'show'])->middleware(['auth', 'consents', 'access:tank_ranking'])->name('advisor.tank');
Route::middleware(['auth', 'consents', 'access:guided_advisor'])->group(function () {
    Route::get('/doradca/kreator', [GuidedAdvisorController::class, 'show'])->name('advisor.guided');
    Route::post('/doradca/kreator', [GuidedAdvisorController::class, 'answer'])->name('advisor.guided.answer');
});
Route::get('/moje-konto', [AccountController::class, 'show'])->middleware(['auth', 'consents'])->name('account');
Route::post('/moje-konto/miasto', [AccountController::class, 'updateCity'])->middleware('auth')->name('account.city');
Route::post('/moje-konto/nazwa', [AccountController::class, 'updateName'])->middleware('auth')->name('account.name');
Route::post('/moje-konto/haslo', [AccountController::class, 'updatePassword'])->middleware('auth')->name('account.password');
Route::prefix('moje-konto/prywatnosc')->middleware(['auth', 'access:own_data'])->name('account.privacy')->group(function () {
    Route::get('/', [AccountPrivacyController::class, 'show']);
    Route::post('/newsletter', [AccountPrivacyController::class, 'withdrawNewsletter'])->name('.newsletter');
    Route::post('/eksport', [AccountPrivacyController::class, 'export'])->name('.export');
    Route::delete('/konto', [AccountPrivacyController::class, 'destroy'])->middleware('access:delete_account')->name('.delete');
});
Route::post('/doradca/obsada', [TankAdvisorController::class, 'addToLivestock'])->middleware(['auth', 'consents', 'access:tank_ranking'])->name('advisor.livestock');
Route::post('/doradca/dodaj-do-akwarium', AddToTankController::class)->middleware(['auth', 'consents', 'access:tank_ranking'])->name('advisor.add-to-tank');
Route::prefix('moje-akwarium')->middleware(['auth', 'consents'])->group(function () {
    Route::get('/', TankIndex::class)->middleware('access:tank_view')->name('aquarium.index');
    Route::get('/zbiorniki/{tank}', TankShow::class)->whereNumber('tank')->middleware('access:tank_view')->name('aquarium.tank');
    Route::get('/redfield', RedfieldCalculator::class)->middleware('access:redfield')->name('aquarium.redfield');
});

Route::middleware(['auth', 'consents', 'access:shop_cart'])->group(function () {
    Route::get('/koszyk', [CartController::class, 'show'])->name('cart.show');
    Route::post('/koszyk', [CartController::class, 'add'])->name('cart.add');
    Route::post('/koszyk/ceny', [CartController::class, 'acceptPrices'])->name('cart.prices');
    Route::post('/koszyk/wyslij', [CartController::class, 'submit'])->name('cart.submit');
    Route::patch('/koszyk/{offer}', [CartController::class, 'update'])->whereNumber('offer')->name('cart.update');
    Route::delete('/koszyk/{offer}', [CartController::class, 'remove'])->whereNumber('offer')->name('cart.remove');
    Route::get('/zamowienia', [CustomerOrderController::class, 'index'])->name('orders.index');
    Route::get('/zamowienia/{order}', [CustomerOrderController::class, 'show'])->whereNumber('order')->name('orders.show');
    Route::post('/zamowienia/{order}/zamknij', [CustomerOrderController::class, 'close'])->whereNumber('order')->name('orders.close');
});

Route::get('/zgody', [ConsentController::class, 'show'])->middleware('auth')->name('consents');
Route::post('/zgody', [ConsentController::class, 'store'])->middleware('auth');

Route::middleware('guest')->group(function () {
    Route::get('/rejestracja', [RegisterController::class, 'choose'])->name('register');
    Route::get('/rejestracja/gotowe', [RegisterController::class, 'done'])->name('register.done');
    Route::get('/rejestracja/{type}', [RegisterController::class, 'create'])->whereIn('type', ['akwarysta', 'sklep'])->name('register.create');
    Route::post('/rejestracja/{type}', [RegisterController::class, 'store'])->whereIn('type', ['akwarysta', 'sklep']);

    Route::get('/logowanie', [SessionController::class, 'create'])->name('login');
    Route::post('/logowanie', [SessionController::class, 'store']);

    Route::get('/haslo/reset', [PasswordController::class, 'request'])->name('password.request');
    Route::post('/haslo/email', [PasswordController::class, 'email'])->middleware('throttle:6,1,password-email:')->name('password.email');
    Route::get('/haslo/reset/{token}', [PasswordController::class, 'reset'])->name('password.reset');
    Route::post('/haslo/reset', [PasswordController::class, 'update'])->middleware('throttle:6,1,password-reset:')->name('password.update');
});

Route::post('/wyloguj', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/weryfikacja', [VerificationController::class, 'notice'])->name('verification.notice');
Route::post('/weryfikacja/ponow', [VerificationController::class, 'resend'])->middleware('throttle:6,1,verification-resend:')->name('verification.send');
Route::get('/weryfikacja/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1,verification-link:'])->name('verification.verify');

// Trasy funkcji: jeden plik na funkcję w routes/features, dołączany automatycznie (pakiety nie edytują tego pliku).
foreach (glob(__DIR__.'/features/*.php') as $file) {
    require $file;
}

if (app()->isLocal()) {
    Route::get('/_podglad-poczty/{rodzaj}', MailPreviewController::class)->withoutMiddleware('web');
}
