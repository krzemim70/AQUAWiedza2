<?php

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

it('adds the security headers to web responses and reports the content security policy', function () {
    $this->get('/logowanie')->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
        ->assertHeader('Content-Security-Policy-Report-Only')
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeaderMissing('Strict-Transport-Security');
});

it('adds HSTS in production over HTTPS', function () {
    $this->app->instance('env', 'production');

    $this->get('http://localhost/logowanie')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://localhost/logowanie')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

it('renders a Polish 404 page with the security headers', function () {
    $this->get('/brak-takiej-strony')->assertNotFound()
        ->assertSee('<html lang="pl">', escape: false)
        ->assertSee('Nie znaleziono strony')
        ->assertSee('Kontakt')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('renders a Polish 419 page for a token mismatch', function () {
    Route::post('/test-wygasla-sesja', fn () => throw new TokenMismatchException);

    $this->post('/test-wygasla-sesja')->assertStatus(419)
        ->assertSee('Sesja wygasła')
        ->assertSee('odśwież stronę i spróbuj ponownie');
});

it('renders a Polish 500 page when the session database is unavailable', function () {
    config([
        'app.debug' => false,
        'session.driver' => 'database',
        'session.connection' => 'unavailable',
    ]);
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');

    $this->get('/logowanie')->assertInternalServerError()
        ->assertSee('Wystąpił błąd')
        ->assertSee('Strona główna')
        ->assertSee('Kontakt');
});
