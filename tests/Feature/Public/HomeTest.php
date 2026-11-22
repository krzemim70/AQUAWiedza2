<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Models\User;

it('serves the home page with one h1, skip link and CTAs', function () {
    app(ImportPackage::class)->handle(kbPackage());

    $response = $this->get('/')->assertOk()
        ->assertSee('Przejdź do treści')
        ->assertSee('Dobierz obsadę')->assertSee('Załóż konto')
        ->assertSee(route('drop.show'), false)
        ->assertSee('<html lang="pl">', false)
        ->assertSee('fetchpriority="high"', false)
        ->assertSee('pozycj');

    expect(substr_count($response->getContent(), '<h1'))->toBe(1);
});

it('sends logged-in users to the advisor from the hero', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertOk()
        ->assertSeeHtml('href="'.route('advisor').'" class="button button-lg">Dobierz obsadę');
});

it('serves placeholder content pages', function (string $url) {
    $this->get($url)->assertOk()->assertSee('w przygotowaniu');
})->with(['/regulamin', '/prywatnosc', '/kontakt', '/dla-sklepow']);

it('serves the guest quick selection under /dobor', function () {
    $this->get('/dobor')->assertOk()->assertSee('Wielkość akwarium (wymagane)');
});
