<?php

use App\Domain\Advisor\CreatorBundle;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->withoutVite();
});

it('serves /kreator-akwarium publicly for guests and logged-in users with SEO meta, bundle preload and zero shop offer queries', function () {
    app(ImportPackage::class)->handle(kbPackage());
    [$shop, $owner, $location] = openShop();
    publishedOffer($owner, $shop, $location, 'FN0001');

    $bundleUrl = app(CreatorBundle::class)->ensureBuilt()['url'];

    DB::flushQueryLog();
    DB::enableQueryLog();
    $guest = $this->get(route('creator.show'));
    $queries = array_column(DB::getQueryLog(), 'query');
    DB::disableQueryLog();

    $guest->assertOk()
        ->assertSee('<meta name="description"', false)
        ->assertSee('<link rel="preload" href="'.e($bundleUrl).'" as="fetch" crossorigin>', false);

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('shop_offers')
            ->and($sql)->not->toContain('locations');
    }

    $this->actingAs(aquarist())
        ->get(route('creator.show'))
        ->assertOk()
        ->assertSee('<link rel="preload" href="'.e($bundleUrl).'" as="fetch" crossorigin>', false);
});

it('renders empty state with HTTP 200 when no active catalog batch exists', function () {
    $this->get(route('creator.show'))
        ->assertOk()
        ->assertSee('Katalog w przygotowaniu')
        ->assertSee('empty-state');
});

it('renders all 6 steps, dialog, aria-live region, reduced-motion styles and honest wording without forbidden stems', function () {
    app(ImportPackage::class)->handle(kbPackage());

    $html = $this->get(route('creator.show'))->assertOk()->getContent();
    $css = (string) file_get_contents(resource_path('css/creator.css'));

    expect($html)
        ->toContain('Krok 1 z 6')
        ->toContain('Krok 2 z 6')
        ->toContain('Krok 3 z 6')
        ->toContain('Krok 4 z 6')
        ->toContain('Krok 5 z 6')
        ->toContain('Krok 6 z 6')
        ->toContain('<dialog')
        ->toContain('</dialog>')
        ->toContain('aria-live="polite"')
        ->toContain('Schemat wybranego zbiornika i stref')
        ->toContain('Relacja pary: brak danych')
        ->toContain('Pokarmy dla ślimaków: brak danych w bazie')
        ->toContain('Moc i przepływ: brak danych w bazie');

    expect($css)->toContain('prefers-reduced-motion');
    expect($html)->not->toMatch('/potrzebn|obowi[aą]zkow/iu');
});
