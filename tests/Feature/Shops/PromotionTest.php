<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Actions\AcceptCartPrices;
use App\Domain\Shops\Actions\ApproveOrder;
use App\Domain\Shops\Actions\ChangeCart;
use App\Domain\Shops\Actions\EndOfferPromotion;
use App\Domain\Shops\Actions\ExpireOffers;
use App\Domain\Shops\Actions\ListCart;
use App\Domain\Shops\Actions\PublishOffer;
use App\Domain\Shops\Actions\SaveOffer;
use App\Domain\Shops\Actions\SetOfferPromotion;
use App\Domain\Shops\Actions\SubmitCart;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Domain\Shops\Data\OfferData;
use App\Domain\Shops\Data\PublicOffer;
use App\Domain\Shops\Models\Location;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\Shop;
use App\Filament\Shop\Resources\Offers\Pages\ListOffers;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    app(ImportPackage::class)->handle(kbPackage());
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 20]);
    Carbon::setTestNow('2026-12-10 10:00:00');
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Oferta opublikowana „teraz” w podanej cenie regularnej (ważna 200 dni).
 *
 * @return array{0: User, 1: Shop, 2: Location, 3: Offer, 4: string}
 */
function promoOffer(int $price = 2000, string $nip = '5260250274'): array
{
    [$shop, $owner, $loc] = openShop($nip);
    [$id] = faunaIds(1);
    $offer = app(SaveOffer::class)->handle($owner, $shop, new OfferData($loc->id, Area::Fauna, $id, '', $price, now()->subHour(), now()->addDays(200), 5));
    $offer = app(PublishOffer::class)->handle($owner, $offer);

    return [$owner, $shop, $loc, $offer, $id];
}

function reprice(User $owner, Shop $shop, Location $loc, Offer $offer, int $price): Offer
{
    return app(SaveOffer::class)->handle($owner, $shop, new OfferData($loc->id, $offer->area, $offer->legacy_id, '', $price, $offer->valid_from, $offer->valid_to), $offer);
}

function later(int $days): void
{
    Carbon::setTestNow(Carbon::parse('2026-12-10 10:00:00')->addDays($days));
}

function publicOf(Offer $offer): PublicOffer
{
    return app(PublicOffers::class)->forOffers([$offer->id])[$offer->id];
}

it('uses the promotional price only inside the window', function () {
    [$owner, , , $offer] = promoOffer(2000);
    later(1);
    app(SetOfferPromotion::class)->handle($owner, $offer, 1800, now()->addDay(), now()->addDays(3));

    expect(publicOf($offer)->priceGrosze)->toBe(2000);
    later(2);
    $p = publicOf($offer);
    expect($p->priceGrosze)->toBe(1800)->and($p->regularPriceGrosze)->toBe(2000);
    later(5);
    expect(publicOf($offer)->priceGrosze)->toBe(2000)->and(publicOf($offer)->promoEndsAt)->toBeNull();
});

it('validates promotion price, window and length', function () {
    [$owner, , , $offer] = promoOffer(2000);
    $set = fn (int $price, $from, $to) => fn () => app(SetOfferPromotion::class)->handle($owner, $offer, $price, $from, $to);

    expect($set(2000, now()->addDay(), now()->addDays(2)))->toThrow(ValidationException::class)
        ->and($set(2500, now()->addDay(), now()->addDays(2)))->toThrow(ValidationException::class)
        ->and($set(1800, now()->addDays(2), now()->addDay()))->toThrow(ValidationException::class)
        ->and($set(1800, now()->subDay(), now()->addDay()))->toThrow(ValidationException::class)
        ->and($set(1800, now()->addDay(), now()->addDays(32)))->toThrow(ValidationException::class);
    expect($offer->refresh()->promo_price_grosze)->toBeNull();

    app(SetOfferPromotion::class)->handle($owner, $offer, 1800, now()->addDay(), now()->addDays(31));
    expect($offer->refresh()->promo_price_grosze)->toBe(1800);
});

it('blocks a regular price at or below an unfinished promotion price and lets a running promotion only be ended', function () {
    [$owner, $shop, $loc, $offer] = promoOffer(2000);
    app(SetOfferPromotion::class)->handle($owner, $offer, 1800, now(), now()->addDays(5));

    expect(fn () => reprice($owner, $shop, $loc, $offer, 1700))->toThrow(ValidationException::class)
        ->and(fn () => app(SetOfferPromotion::class)->handle($owner, $offer, 1500, now(), now()->addDays(5)))->toThrow(ValidationException::class);
    reprice($owner, $shop, $loc, $offer, 2100);
    expect(publicOf($offer)->priceGrosze)->toBe(1800);
});

it('requires shop membership to set a promotion', function () {
    [, , , $offer] = promoOffer(2000);
    $stranger = User::factory()->shop()->create();

    expect(fn () => app(SetOfferPromotion::class)->handle($stranger, $offer, 1800, now()->addDay(), now()->addDays(2)))
        ->toThrow(AuthorizationException::class);
});

it('takes the reference price as the lowest effective price of the lookback period before the promotion', function () {
    [$owner, $shop, $loc, $offer] = promoOffer(2200);
    later(10);
    $offer = reprice($owner, $shop, $loc, $offer, 2500);
    later(20);
    $offer = reprice($owner, $shop, $loc, $offer, 2100);
    later(45);
    app(SetOfferPromotion::class)->handle($owner, $offer, 1800, now(), now()->addDays(5));

    // okno: dni 15-45; cena 2200 skończyła się w dniu 10, więc najniższa to 2100 (nie 2200 ani 1800)
    $p = publicOf($offer);
    expect($p->priceGrosze)->toBe(1800)->and($p->lowestPriorGrosze)->toBe(2100)->and($p->lowestPriorSinceStart)->toBeFalse()
        ->and($p->lookbackDays)->toBe(30);
});

it('gives the lowest price of the history for 22 zl, then 20 zl, then a promotion of 18 zl', function () {
    [$owner, $shop, $loc, $offer] = promoOffer(2200);
    later(5);
    $offer = reprice($owner, $shop, $loc, $offer, 2000);
    later(10);
    app(SetOfferPromotion::class)->handle($owner, $offer, 1800, now(), now()->addDays(5));

    expect(publicOf($offer))->lowestPriorGrosze->toBe(2000)->lowestPriorSinceStart->toBeTrue();
});

it('lets an earlier promotion inside the lookback period lower the reference price', function () {
    [$owner, , , $offer] = promoOffer(2000);
    later(2);
    app(SetOfferPromotion::class)->handle($owner, $offer, 1500, now(), now()->addDays(2));
    later(10);
    app(SetOfferPromotion::class)->handle($owner, $offer, 1800, now(), now()->addDays(3));

    expect(publicOf($offer))->lowestPriorGrosze->toBe(1500);
});

it('says since the start of sales when the offer is younger than the lookback period', function () {
    [$owner, , , $offer] = promoOffer(2000);
    later(3);
    app(SetOfferPromotion::class)->handle($owner, $offer, 1800, now(), now()->addDays(3));

    expect(publicOf($offer))->lowestPriorGrosze->toBe(2000)->lowestPriorSinceStart->toBeTrue();
});

it('does not mark a promotion when there is no price history before it', function () {
    [$owner, , , $offer] = promoOffer(2000);
    app(SetOfferPromotion::class)->handle($owner, $offer, 1800, now(), now()->addDays(3));
    DB::table('shop_offer_price_history')->delete();

    $p = publicOf($offer);
    expect($p->priceGrosze)->toBe(1800)->and($p->promoEndsAt)->toBeNull()->and($p->lowestPriorGrosze)->toBeNull();

    // historia zaczynająca się dopiero w chwili startu promocji też nie daje ceny odniesienia
    DB::table('shop_offer_price_history')->insert(['offer_id' => $offer->id, 'kind' => 'regular', 'price_grosze' => 2000, 'valid_from' => now()]);
    expect(publicOf($offer)->promoEndsAt)->toBeNull();
});

it('records an early end of a promotion and keeps it for later reference prices', function () {
    [$owner, , , $offer] = promoOffer(2000);
    later(1);
    app(SetOfferPromotion::class)->handle($owner, $offer, 1500, now(), now()->addDays(10));
    later(2);
    app(EndOfferPromotion::class)->handle($owner, $offer);

    expect(publicOf($offer)->priceGrosze)->toBe(2000)->and(publicOf($offer)->promoEndsAt)->toBeNull()
        ->and(DB::table('shop_offer_price_history')->where('kind', 'promo')->value('valid_to'))->toBe(now()->toDateTimeString());
    expect(fn () => app(EndOfferPromotion::class)->handle($owner, $offer->refresh()))->toThrow(ValidationException::class);

    later(5);
    app(SetOfferPromotion::class)->handle($owner, $offer, 1800, now(), now()->addDays(3));
    expect(publicOf($offer))->lowestPriorGrosze->toBe(1500);
});

it('removes a planned promotion without a trace when it is ended before it starts', function () {
    [$owner, , , $offer] = promoOffer(2000);
    app(SetOfferPromotion::class)->handle($owner, $offer, 1500, now()->addDay(), now()->addDays(3));
    app(EndOfferPromotion::class)->handle($owner, $offer);

    expect($offer->refresh()->promo_price_grosze)->toBeNull()
        ->and(DB::table('shop_offer_price_history')->where('kind', 'promo')->count())->toBe(0);
});

it('stops counting prices after the offer expires', function () {
    [, , , $offer] = promoOffer(2000);
    $offer->forceFill(['valid_to' => now()->addDays(2)])->save();
    later(3);
    app(ExpireOffers::class)->handle();

    expect(DB::table('shop_offer_price_history')->whereNull('valid_to')->count())->toBe(0)
        ->and(Carbon::parse(DB::table('shop_offer_price_history')->value('valid_to'))->toDateString())->toBe('2026-12-12');
});

it('shows the label and the reference price, with no strikethrough and no percent', function () {
    [$owner, , , $offer] = promoOffer(2000);
    later(3);
    app(SetOfferPromotion::class)->handle($owner, $offer, 1800, now(), now()->addDays(5));
    $html = Blade::render('<x-offer-price :offer="$o" />', ['o' => publicOf($offer)]);

    expect($html)->toContain('18,00 zł')->toContain('Promocja do 18.12')
        ->toContain('Najniższa cena od rozpoczęcia sprzedaży: 20,00 zł')
        ->not->toContain('%')->not->toContain('<s>')->not->toContain('<del')->not->toContain('line-through');

    later(40);
    $html = Blade::render('<x-offer-price :offer="$o" />', ['o' => publicOf($offer)]);
    expect($html)->not->toContain('Promocja do');

    app(SetOfferPromotion::class)->handle($owner, $offer, 1700, now(), now()->addDays(5));
    $html = Blade::render('<x-offer-price :offer="$o" />', ['o' => publicOf($offer)]);
    expect($html)->toContain('Najniższa cena z 30 dni przed obniżką: 20,00 zł');
});

it('sorts offers of one record by effective price', function () {
    [$ownerA, , , $offerA, $id] = promoOffer(2000);
    [$shopB, $ownerB, $locB] = openShop('7740001454', 'Drugi');
    $offerB = app(PublishOffer::class)->handle($ownerB, app(SaveOffer::class)->handle($ownerB, $shopB, new OfferData($locB->id, Area::Fauna, $id, '', 1900, now()->subHour(), now()->addDays(30), 3)));
    expect(array_map(fn ($o) => $o->id, app(PublicOffers::class)->forRecords(Area::Fauna, [$id])[$id]))->toBe([$offerB->id, $offerA->id]);

    later(1);
    app(SetOfferPromotion::class)->handle($ownerA, $offerA, 1700, now(), now()->addDays(3));
    expect(array_map(fn ($o) => $o->id, app(PublicOffers::class)->forRecords(Area::Fauna, [$id])[$id]))->toBe([$offerA->id, $offerB->id]);
});

it('asks the customer to accept the changed price when a promotion starts or ends', function () {
    [$owner, , , $offer] = promoOffer(2000);
    $customer = aquarist();
    app(ChangeCart::class)->handle($customer, $offer->id, 1);
    expect(app(ListCart::class)->handle($customer)[0])->issue->toBeNull()->priceGrosze->toBe(2000);

    later(1);
    app(SetOfferPromotion::class)->handle($owner, $offer, 1800, now(), now()->addDays(2));
    $line = app(ListCart::class)->handle($customer)[0];
    expect($line->priceGrosze)->toBe(1800)->and($line->issue)->toContain('Cena zmieniła się');
    expect(fn () => app(SubmitCart::class)->handle($customer, 'klucz-promo-1', '600 100 200'))->toThrow(ValidationException::class);

    app(AcceptCartPrices::class)->handle($customer);
    expect(app(ListCart::class)->handle($customer)[0]->issue)->toBeNull();

    later(4);
    expect(app(ListCart::class)->handle($customer)[0])->priceGrosze->toBe(2000)->issue->toContain('Cena zmieniła się');
});

it('approves an order placed in a promotion after it ended but still catches a regular price change', function () {
    Notification::fake();
    [$owner, , , $offer] = promoOffer(2000);
    $customer = aquarist();
    later(1);
    app(SetOfferPromotion::class)->handle($owner, $offer, 1800, now(), now()->addDays(2));
    app(ChangeCart::class)->handle($customer, $offer->id, 2);
    [$order] = app(SubmitCart::class)->handle($customer, 'klucz-promo-2', '600 100 200');

    expect($order->items[0])->price_grosze->toBe(1800)->regular_price_grosze->toBe(2000)
        ->and($order->total_grosze)->toBe(3600);

    later(5);
    expect(app(ApproveOrder::class)->handle($owner, $order)->state->value)->toBe('confirmed')
        ->and($order->items()->first()->price_grosze)->toBe(1800);

    [$owner2, $shop2, $loc2, $offer2] = promoOffer(2000, '7740001454');
    app(ChangeCart::class)->handle($customer, $offer2->id, 1);
    [$order2] = app(SubmitCart::class)->handle($customer, 'klucz-promo-3', '600 100 200');
    reprice($owner2, $shop2, $loc2, $offer2, 2100);
    expect(fn () => app(ApproveOrder::class)->handle($owner2, $order2))->toThrow(ValidationException::class);
});

it('sets and ends a promotion from the shop panel', function () {
    [$owner, $shop, , $offer] = promoOffer(2000);
    inShopPanel($shop, $owner);

    Livewire::test(ListOffers::class)
        ->callTableAction('promo', $offer, ['promo_price' => '18.00', 'promo_from' => now()->addDay(), 'promo_to' => now()->addDays(4)])
        ->assertHasNoTableActionErrors();
    expect($offer->refresh()->promo_price_grosze)->toBe(1800);

    Livewire::test(ListOffers::class)->callTableAction('endPromo', $offer)->assertHasNoTableActionErrors();
    expect($offer->refresh()->promo_price_grosze)->toBeNull();
});

it('shows the last day of the promotion when it ends at midnight', function () {
    [$owner, , , $offer] = promoOffer(2000);
    later(3);
    $midnight = Carbon::parse('2026-12-18 00:00', config('app.display_timezone'));
    app(SetOfferPromotion::class)->handle($owner, $offer, 1800, now(), $midnight);

    expect(Blade::render('<x-offer-price :offer="$o" />', ['o' => publicOf($offer)]))->toContain('Promocja do 17.12');
});
