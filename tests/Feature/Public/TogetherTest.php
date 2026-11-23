<?php

use App\Domain\Aquarium\Models\Livestock;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\Settings\SettingKey;
use App\Models\User;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

it('renders the public together page for a guest without parameters as indexable with canonical URL', function () {
    $html = $this->get(route('advisor.together'))
        ->assertOk()
        ->assertSee('Czy te ryby mogą być razem?')
        ->assertSee('Szukaj gatunku')
        ->getContent();

    expect($html)->toContain('<title>Czy te ryby mogą być razem? — Aikwarium</title>')
        ->toContain('<meta name="description" content=')
        ->toContain('<link rel="canonical" href="'.route('advisor.together').'">')
        ->not->toContain('<meta name="robots" content="noindex">');
});

it('compares 2 species for a guest with evidence, temperament labels, share link, offers, noindex and no DB writes', function () {
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 20]);
    [$shop, $owner, $location] = openShop(name: 'Sklep Akwarystyczny');
    publishedOffer($owner, $shop, $location, 'FN0029', qty: 4);

    $url = route('advisor.together', ['gatunki' => 'FN0029,FN0011']);
    $html = $this->get($url)
        ->assertOk()
        ->assertSee('Drobnoustek Becforda')
        ->assertSee('Neon Innesa')
        ->assertSee('Pasma temperatury w katalogu się różnią')
        ->assertSee('Pasma pH w katalogu się różnią')
        ->assertSee('Dlaczego?')
        ->assertSee('Relacja pary: brak danych.')
        ->assertSee('Sklep Akwarystyczny')
        ->assertSee('Poznań')
        ->assertSee('15,00')
        ->getContent();

    expect($html)
        ->toContain('<link rel="canonical" href="'.route('advisor.together').'">')
        ->toContain('<meta name="robots" content="noindex">')
        ->toContain(e($url))
        ->and(Tank::count())->toBe(0)
        ->and(Livestock::count())->toBe(0);
});

it('shows table-description mismatch for FN0001 instead of pH band difference on the together page', function () {
    $this->get(route('advisor.together', ['gatunki' => 'FN0001,FN0002']))
        ->assertOk()
        ->assertSee('Gupik Pawie Oczko')
        ->assertSee('Gupik Endlera')
        ->assertSee('brak danych do porównania')
        ->assertSee('6,8–7,5')
        ->assertSee('pH 7,0–8,5')
        ->assertSee('Dlaczego?')
        ->assertDontSee('Pasma pH w katalogu się różnią');
});

it('enforces the configured maximum of 8 species when 9 are requested and marks the page noindex', function () {
    $nine = implode(',', faunaIds(9));

    $html = $this->get(route('advisor.together', ['gatunki' => $nine]))
        ->assertOk()
        ->assertSee('Możesz porównać maksymalnie 8 gatunków naraz (wybrano 9)')
        ->assertDontSee('Wynik porównania katalogowego')
        ->getContent();

    expect($html)
        ->toContain('<link rel="canonical" href="'.route('advisor.together').'">')
        ->toContain('<meta name="robots" content="noindex">');
});

it('supports searching species without JavaScript and links from the fauna product card', function () {
    $this->get(route('advisor.together', ['szukaj' => 'Gupik']))
        ->assertOk()
        ->assertSee('Gupik Pawie Oczko')
        ->assertSeeHtml(route('advisor.together', ['gatunki' => 'FN0001']))
        ->assertSee('<meta name="robots" content="noindex">', false);

    $this->get(route('catalog.show', ['fauna', 'FN0001']))
        ->assertOk()
        ->assertSee('Czy może być z innymi gatunkami?')
        ->assertSeeHtml(route('advisor.together', ['gatunki' => 'FN0001']));

    $this->get(route('catalog.show', ['flora', 'FL0011']))
        ->assertOk()
        ->assertDontSee('Czy może być z innymi gatunkami?');
});

it('forbids suspended users through ResolveAccess', function () {
    $suspended = User::factory()->create(['suspended_at' => now()]);

    $this->actingAs($suspended)->get(route('advisor.together'))->assertForbidden();
});
