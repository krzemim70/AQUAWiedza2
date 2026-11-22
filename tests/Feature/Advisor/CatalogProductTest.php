<?php

use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Record;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Actions\PublishOffer;
use App\Domain\Shops\Actions\SaveOffer;
use App\Domain\Shops\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

function publishProductOffers(): array
{
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 20]);
    [$shop, $owner, $location] = openShop(name: 'Sklep z rogatkiem');
    [$secondShop, $secondOwner, $secondLocation] = openShop('7740001454', 'Sklep z anubiasem');

    $expensive = app(PublishOffer::class)->handle($owner, app(SaveOffer::class)->handle(
        $owner, $shop, offerData($location, 'FN0013', price: 1700, variant: 'Duża'),
    ));
    $outOfStock = app(PublishOffer::class)->handle($owner, app(SaveOffer::class)->handle(
        $owner, $shop, offerData($location, 'FN0013', price: 500, qty: 0, variant: 'Po odłowie'),
    ));
    $cheapest = app(PublishOffer::class)->handle($secondOwner, app(SaveOffer::class)->handle(
        $secondOwner, $secondShop, offerData($secondLocation, 'FN0013', price: 1200, qty: 3, variant: 'Mała'),
    ));

    return [$outOfStock, $cheapest, $expensive];
}

it('shows an excerpt to guests and the complete catalog details to Tank, with photo attribution', function () {
    $record = Record::where('legacy_id', 'FN0013')->firstOrFail();
    $record->description()->updateOrCreate([], ['body' => 'Pierwsze zdanie. Drugie zdanie. Dalsza część opisu gatunku.']);
    $record->photos()->create(['url' => '/zdjecie-bystrzyka.jpg', 'author' => 'Jan Fotograf', 'licence' => 'CC BY 4.0', 'verified' => true]);
    $url = route('catalog.show', ['fauna', 'FN0013']);

    $this->get($url)->assertOk()
        ->assertSee('Hyphessobrycon amandae')
        ->assertSee('Pierwsze zdanie. Drugie zdanie.')
        ->assertDontSee('Dalsza część opisu gatunku.')
        ->assertSee('Pełny opis i wszystkie parametry')
        ->assertSee('Temperatura')->assertDontSee('Temperament')
        ->assertSee('Autor zdjęcia: Jan Fotograf')->assertSee('Licencja: CC BY 4.0')
        ->assertSee('<link rel="canonical" href="'.$url.'">', false)
        ->assertSee('<meta name="description"', false)
        ->assertSee('Załóż konto, aby zapisać w swoim akwarium')
        ->assertSee('Brak ofert w sklepach.');

    $this->actingAs(aquarist())->get($url)->assertOk()
        ->assertSee('Dalsza część opisu gatunku.')->assertSee('Temperament')
        ->assertSee('Załóż zbiornik w Moim akwarium');
});

it('returns 404 for unknown areas and records', function (string $area, string $id) {
    $this->get(route('catalog.show', [$area, $id]))->assertNotFound();
})->with([['unknown', 'FN0013'], ['fauna', 'FN9999']]);

it('accepts a relative advisor return path without changing the canonical URL', function () {
    $back = '/doradca/kreator/wynik?area=fauna&card=FN0013';
    $url = route('catalog.show', ['fauna', 'FN0013']);
    $this->get($url.'?'.http_build_query(['wroc' => $back]))->assertOk()
        ->assertSee('Wróć do wyników')->assertSee('href="'.e($back).'"', false)
        ->assertSee('<link rel="canonical" href="'.$url.'">', false);
});

it('ignores a return path outside the advisor', function (mixed $back) {
    $this->get(route('catalog.show', ['fauna', 'FN0013', 'wroc' => $back]))
        ->assertOk()->assertDontSee('Wróć do wyników');
})->with([
    ['https://example.com/doradca'],
    ['//example.com/doradca'],
    ['/doradca-inny'],
    ['/doradca/../koszyk'],
    ['/doradca/%2e%2e/koszyk'],
    ['/doradca\\test'],
    [['/doradca']],
]);

it('shows offers in price order with cart forms only for eligible users and in-stock offers', function () {
    [$outOfStock, $cheapest, $expensive] = publishProductOffers();
    $url = route('catalog.show', ['fauna', 'FN0013']);

    $this->get($url)->assertOk()
        ->assertSeeInOrder(['5,00 zł', '12,00 zł', '17,00 zł'])
        ->assertSee('Sklep z rogatkiem')->assertSee('Poznań')
        ->assertSee('Wariant: Mała')->assertSee('Dostępna ilość: 3')->assertSee('Chwilowo niedostępna')
        ->assertDontSee('Dodaj do koszyka');

    $formField = fn (Offer $offer) => 'name="offer_id" value="'.$offer->id.'"';
    $this->actingAs(aquarist())->get($url)->assertOk()
        ->assertSee('action="'.route('cart.add').'"', false)
        ->assertSee($formField($cheapest), false)->assertSee($formField($expensive), false)
        ->assertDontSee($formField($outOfStock), false);
});

it('adds one item from the cheapest available offer on the compact card and returns to the card after login', function () {
    $path = '/doradca/test-karty';
    Route::middleware('web')->get($path, fn () => Blade::render(
        "@includeIf('advisor._card-actions', ['area' => \$area, 'id' => 'FN0013', 'name' => 'Bystrzyk', 'variant' => 'compact'])",
        ['area' => GuideArea::Fauna],
    ));

    $this->get($path)->assertOk()->assertSee('Brak ofert w sklepach.')->assertDontSee('Do koszyka');
    [$outOfStock, $cheapest, $expensive] = publishProductOffers();

    $login = route('login', ['wroc' => $path]);
    $this->get($path)->assertOk()->assertSee('od 12,00 zł · 2 sklepy')
        ->assertSee($login, false)->assertDontSee('name="offer_id"', false);
    $this->get($login)->assertOk()->assertSee('name="wroc" value="'.$path.'"', false);

    $user = User::factory()->create();
    $this->post($login, ['email' => $user->email, 'password' => 'password', 'wroc' => $path])->assertRedirect($path);
    $card = $this->get($path)->assertOk()
        ->assertDontSee('name="offer_id" value="'.$outOfStock->id.'"', false)
        ->assertDontSee('name="offer_id" value="'.$expensive->id.'"', false)
        ->getContent();
    preg_match('/name="offer_id" value="(\d+)"/', $card, $offer);
    preg_match('/name="qty" value="(\d+)"/', $card, $quantity);
    expect($offer[1])->toBe((string) $cheapest->id)->and($quantity[1])->toBe('1');

    $this->post(route('cart.add'), ['offer_id' => $offer[1], 'qty' => $quantity[1]])->assertRedirect(route('cart.show'));
    $this->assertDatabaseHas('shop_cart_items', ['user_id' => $user->id, 'offer_id' => $cheapest->id, 'qty' => 1]);
});
