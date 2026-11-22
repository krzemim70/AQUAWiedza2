<?php

use App\Domain\Aquarium\Actions\CreateTank;
use App\Domain\Aquarium\Data\TankData;
use App\Domain\IdentityAccess\Actions\ApproveShopApplication;
use App\Domain\IdentityAccess\Models\ShopApplication;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\ChangeSetting;
use App\Domain\Settings\ChangeSettingData;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Actions\AddMember;
use App\Domain\Shops\Actions\EvaluateShop;
use App\Domain\Shops\Actions\ExpireOffers;
use App\Domain\Shops\Actions\GrantActivationException;
use App\Domain\Shops\Actions\OpenShop;
use App\Domain\Shops\Actions\PublishOffer;
use App\Domain\Shops\Actions\RemoveMember;
use App\Domain\Shops\Actions\ResumeShop;
use App\Domain\Shops\Actions\SaveLocation;
use App\Domain\Shops\Actions\SaveOffer;
use App\Domain\Shops\Actions\SetAvailableQuantity;
use App\Domain\Shops\Actions\SuspendShop;
use App\Domain\Shops\Actions\WithdrawOffer;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Domain\Shops\Data\LocationData;
use App\Domain\Shops\Data\OfferData;
use App\Domain\Shops\Models\Location;
use App\Domain\Shops\Models\Member;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;
use App\Domain\Shops\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

it('creates the shop with its owner when an application is approved', function () {
    $user = User::factory()->shop()->create();
    $application = ShopApplication::create(['user_id' => $user->id, 'company_name' => 'Rybka sp. z o.o.', 'nip' => '5260250274', 'address' => 'A', 'phone' => '1']);

    app(ApproveShopApplication::class)->handle(User::factory()->admin()->create(), $application, 'ok');

    $shop = Shop::firstOrFail();
    expect($shop->name)->toBe('Rybka sp. z o.o.')->and($shop->nip)->toBe('5260250274')->and($shop->state)->toBe(ShopState::Preparation)
        ->and($shop->members)->toHaveCount(1)->and($shop->members[0]->user_id)->toBe($user->id)
        ->and($user->canAccessTenant($shop))->toBeTrue()
        ->and($user->refresh()->canAccessPanel(Filament\Facades\Filament::getPanel('shop')))->toBeTrue();
});

it('allows one shop per NIP and rolls the approval back otherwise', function () {
    openShop();
    $user = User::factory()->shop()->create();
    $application = ShopApplication::create(['user_id' => $user->id, 'company_name' => 'Inna', 'nip' => '5260250274', 'address' => 'A', 'phone' => '1']);

    expect(fn () => app(ApproveShopApplication::class)->handle(User::factory()->admin()->create(), $application))->toThrow(ValidationException::class);
    expect($application->refresh()->status)->toBe(ShopApplication::PENDING)->and(Shop::count())->toBe(1);
});

it('lets only the owner manage the team and locations', function () {
    [$shop, $owner] = openShop();
    $staff = staffOf($shop, $owner);

    expect(Member::where('shop_id', $shop->id)->count())->toBe(2);
    expect(fn () => app(AddMember::class)->handle($staff, $shop, User::factory()->create()->email))->toThrow(AuthorizationException::class);
    expect(fn () => app(SaveLocation::class)->handle($staff, $shop, new LocationData('X', 'Y', 'Z')))->toThrow(AuthorizationException::class);
    expect(fn () => app(AddMember::class)->handle($owner, $shop, 'brak@example.com'))->toThrow(ValidationException::class);
    expect(fn () => app(AddMember::class)->handle($owner, $shop, $staff->email))->toThrow(ValidationException::class);

    expect(fn () => app(RemoveMember::class)->handle($owner, $shop->members()->where('role', 'owner')->first()))->toThrow(ValidationException::class);
    app(RemoveMember::class)->handle($owner, $shop->members()->where('user_id', $staff->id)->first());
    expect($staff->canAccessTenant($shop))->toBeFalse();
});

it('keeps exactly one owner per shop in the database', function () {
    [$shop] = openShop();

    expect(fn () => Member::create(['shop_id' => $shop->id, 'user_id' => User::factory()->create()->id, 'role' => 'owner']))
        ->toThrow(QueryException::class);
});

it('isolates shops from each other in actions', function () {
    [$shopA, $ownerA, $locA] = openShop();
    [$shopB, $ownerB, $locB] = openShop('7740001454', 'Drugi');
    $staffA = staffOf($shopA, $ownerA);
    [$id1, $id2] = faunaIds(2);
    $offerB = draftOffer($ownerB, $shopB, $locB, $id1);

    expect($staffA->canAccessTenant($shopB))->toBeFalse();
    expect(fn () => app(SaveOffer::class)->handle($staffA, $shopB, offerData($locB, $id2)))->toThrow(AuthorizationException::class);
    expect(fn () => app(PublishOffer::class)->handle($staffA, $offerB))->toThrow(AuthorizationException::class);
    expect(fn () => app(WithdrawOffer::class)->handle($staffA, $offerB))->toThrow(AuthorizationException::class);
    expect(fn () => app(SetAvailableQuantity::class)->handle($staffA, $offerB, 1, 3, true))->toThrow(AuthorizationException::class);
    // własny sklep, ale cudza placówka lub oferta
    expect(fn () => app(SaveOffer::class)->handle($staffA, $shopA, offerData($locB, $id2)))->toThrow(ValidationException::class);
    expect(fn () => app(SaveOffer::class)->handle($staffA, $shopA, offerData($locA, $id2), $offerB))->toThrow(ModelNotFoundException::class);
    expect($offerB->refresh()->state)->toBe(OfferState::Draft)->and($offerB->free_qty)->toBe(5);
});

it('validates offers', function (Closure $mutate) {
    [$shop, $owner, $loc] = openShop();
    [$id] = faunaIds(1);

    expect(fn () => app(SaveOffer::class)->handle($owner, $shop, $mutate(offerData($loc, $id), $loc)))->toThrow(ValidationException::class);
    expect(Offer::count())->toBe(0);
})->with([
    'zero price' => [fn (OfferData $d) => new OfferData($d->locationId, $d->area, $d->legacyId, '', 0, $d->validFrom, $d->validTo, 1)],
    'price above max' => [fn (OfferData $d) => new OfferData($d->locationId, $d->area, $d->legacyId, '', 10000001, $d->validFrom, $d->validTo, 1)],
    'validity reversed' => [fn (OfferData $d) => new OfferData($d->locationId, $d->area, $d->legacyId, '', 100, $d->validTo, $d->validFrom, 1)],
    'zero-length validity' => [fn (OfferData $d) => new OfferData($d->locationId, $d->area, $d->legacyId, '', 100, $d->validFrom, $d->validFrom, 1)],
    'negative quantity' => [fn (OfferData $d) => new OfferData($d->locationId, $d->area, $d->legacyId, '', 100, $d->validFrom, $d->validTo, -1)],
    'quantity above max' => [fn (OfferData $d) => new OfferData($d->locationId, $d->area, $d->legacyId, '', 100, $d->validFrom, $d->validTo, 1001)],
    'unknown catalogue record' => [fn (OfferData $d) => new OfferData($d->locationId, $d->area, 'NIE-MA', '', 100, $d->validFrom, $d->validTo, 1)],
    'inactive location' => [function (OfferData $d, Location $loc) {
        $loc->forceFill(['active' => false])->save();

        return $d;
    }],
]);

it('rejects the same product and variant twice in one location, but allows another variant', function () {
    [$shop, $owner, $loc] = openShop();
    [$id] = faunaIds(1);

    app(SaveOffer::class)->handle($owner, $shop, offerData($loc, $id, variant: 'Duża'));
    expect(fn () => app(SaveOffer::class)->handle($owner, $shop, offerData($loc, $id, variant: ' duża ')))->toThrow(ValidationException::class);
    app(SaveOffer::class)->handle($owner, $shop, offerData($loc, $id, variant: 'Mała'));

    expect(Offer::count())->toBe(2);
});

it('enforces price and validity checks in the database too', function () {
    [$shop, $owner, $loc] = openShop();
    [$id] = faunaIds(1);
    $offer = draftOffer($owner, $shop, $loc, $id);

    expect(fn () => DB::table('shop_offers')->where('id', $offer->id)->update(['price_grosze' => 0]))->toThrow(QueryException::class);
    expect(fn () => DB::table('shop_offers')->where('id', $offer->id)->update(['valid_to' => $offer->valid_from]))->toThrow(QueryException::class);
});

it('edits terms, bumps versions and opens the history with the initial quantity', function () {
    [$shop, $owner, $loc] = openShop();
    [$id] = faunaIds(1);
    $offer = draftOffer($owner, $shop, $loc, $id, 7);

    expect($offer->version)->toBe(1)->and($offer->terms_version)->toBe(1)
        ->and(StockMovement::where('offer_id', $offer->id)->first()->only(['kind', 'free_after']))->toBe(['kind' => 'opening', 'free_after' => 7]);

    $data = new OfferData($loc->id, Area::Fauna, $id, 'Nowy', 2500, $offer->valid_from, $offer->valid_to, 99);
    $offer = app(SaveOffer::class)->handle($owner, $shop, $data, $offer);

    expect($offer->price_grosze)->toBe(2500)->and($offer->terms_version)->toBe(2)->and($offer->version)->toBe(2)
        ->and($offer->free_qty)->toBe(7); // ilość zmienia tylko osobna operacja
});

it('publishes and withdraws offers and republishes a withdrawn one', function () {
    [$shop, $owner, $loc] = openShop();
    [$id] = faunaIds(1);
    $offer = draftOffer($owner, $shop, $loc, $id);

    $offer = app(PublishOffer::class)->handle($owner, $offer);
    expect($offer->state)->toBe(OfferState::Published);
    expect(fn () => app(PublishOffer::class)->handle($owner, $offer))->toThrow(ValidationException::class);

    $offer = app(WithdrawOffer::class)->handle($owner, $offer);
    expect($offer->state)->toBe(OfferState::Withdrawn);
    expect(fn () => app(WithdrawOffer::class)->handle($owner, $offer))->toThrow(ValidationException::class);

    expect(app(PublishOffer::class)->handle($owner, $offer)->state)->toBe(OfferState::Published);
});

it('refuses to publish an offer past its validity or while the shop is suspended', function () {
    [$shop, $owner, $loc] = openShop();
    [$id1, $id2] = faunaIds(2);
    $old = app(SaveOffer::class)->handle($owner, $shop, new OfferData($loc->id, Area::Fauna, $id1, '', 100, now()->subDays(3), now()->subDay(), 1));
    expect(fn () => app(PublishOffer::class)->handle($owner, $old))->toThrow(ValidationException::class);

    $fresh = draftOffer($owner, $shop, $loc, $id2);
    app(SuspendShop::class)->handle(User::factory()->admin()->create(), $shop, 'kontrola');
    expect(fn () => app(PublishOffer::class)->handle($owner, $fresh))->toThrow(ValidationException::class);
});

it('expires published offers after valid_to and the read model hides them before that', function () {
    [$shop, $owner, $loc] = openShop();
    [$id] = faunaIds(1);
    $offer = publishedOffer($owner, $shop, $loc, $id);
    app(Shop::class)->whereKey($shop->id)->update(['state' => 'active']);

    Carbon::setTestNow(now()->addDays(31));
    expect(app(PublicOffers::class)->forRecords(Area::Fauna, [$id]))->toBe([]); // jeszcze przed harmonogramem
    expect(app(ExpireOffers::class)->handle())->toBe(1)->and($offer->refresh()->state)->toBe(OfferState::Expired);
    Carbon::setTestNow();
});

it('changes the available quantity with history, keeping the reserved quantity', function () {
    [$shop, $owner, $loc] = openShop();
    [$id] = faunaIds(1);
    $offer = draftOffer($owner, $shop, $loc, $id, 5);
    $offer->forceFill(['reserved_qty' => 3])->save();

    $offer = app(SetAvailableQuantity::class)->handle($owner, $offer, 1, 10, true, 'dostawa');

    expect($offer->free_qty)->toBe(10)->and($offer->reserved_qty)->toBe(3)->and($offer->version)->toBe(2);
    $m = StockMovement::where('offer_id', $offer->id)->latest('id')->first();
    expect($m->kind)->toBe('adjust')->and($m->delta_free)->toBe(5)->and($m->free_after)->toBe(10)->and($m->reserved_after)->toBe(3)
        ->and($m->reason)->toBe('dostawa')->and($m->actor_id)->toBe($owner->id);

    $offer = app(SetAvailableQuantity::class)->handle($owner, $offer, 2, 0, true);
    expect($offer->free_qty)->toBe(0)->and(StockMovement::where('offer_id', $offer->id)->count())->toBe(3)
        ->and(StockMovement::where('offer_id', $offer->id)->latest('id')->first()->delta_free)->toBe(-10);
});

it('rejects stale, unconfirmed and out-of-range quantity changes without writing history', function (int $version, int $qty, bool $confirmed) {
    [$shop, $owner, $loc] = openShop();
    [$id] = faunaIds(1);
    $offer = draftOffer($owner, $shop, $loc, $id, 5);
    $offer->forceFill(['reserved_qty' => 10])->save();

    expect(fn () => app(SetAvailableQuantity::class)->handle($owner, $offer, $version, $qty, $confirmed))->toThrow(ValidationException::class);
    expect($offer->refresh()->free_qty)->toBe(5)->and(StockMovement::where('offer_id', $offer->id)->count())->toBe(1);
})->with([
    'stale version' => [9, 6, true],
    'not confirmed' => [1, 6, false],
    'negative' => [1, -1, true],
    'free + reserved above max' => [1, 991, true],
]);

it('locks the offer row when changing quantity', function () {
    [$shop, $owner, $loc] = openShop();
    [$id] = faunaIds(1);
    $offer = draftOffer($owner, $shop, $loc, $id);
    $queries = [];
    DB::listen(function ($q) use (&$queries) {
        $queries[] = $q->sql;
    });

    app(SetAvailableQuantity::class)->handle($owner, $offer, 1, 8, true);

    expect(collect($queries)->contains(fn ($sql) => str_contains($sql, 'from `shop_offers`') && str_contains($sql, 'for update')))->toBeTrue();
});

it('activates a shop in preparation at the threshold and keeps it active after a drop', function () {
    shopSetting(SettingKey::ShopActivation, ['threshold' => 2, 'offer_limit' => 5]);
    [$shop, $owner, $loc] = openShop();
    [$a, $b, $c] = faunaIds(3);

    $first = publishedOffer($owner, $shop, $loc, $a, 0); // zerowa ilość też się liczy
    expect($shop->refresh()->state)->toBe(ShopState::Preparation)->and(app(EvaluateShop::class)->qualifying($shop))->toBe(1);

    publishedOffer($owner, $shop, $loc, $b);
    expect($shop->refresh()->state)->toBe(ShopState::Active)->and($shop->activated_at)->not->toBeNull();

    app(WithdrawOffer::class)->handle($owner, $first);
    app(EvaluateShop::class)->handle($shop);
    expect($shop->refresh()->state)->toBe(ShopState::Active)->and(app(EvaluateShop::class)->qualifying($shop))->toBe(1);
});

it('does not count drafts, expired or inactive-location offers towards the threshold', function () {
    shopSetting(SettingKey::ShopActivation, ['threshold' => 2, 'offer_limit' => 5]);
    [$shop, $owner, $loc] = openShop();
    $second = app(SaveLocation::class)->handle($owner, $shop, new LocationData('Druga', 'Ul. 2', 'Łódź'));
    [$a, $b, $c] = faunaIds(3);

    draftOffer($owner, $shop, $loc, $a);
    publishedOffer($owner, $shop, $second, $b);
    app(SaveLocation::class)->handle($owner, $shop, new LocationData('Druga', 'Ul. 2', 'Łódź', active: false), $second);
    $past = publishedOffer($owner, $shop, $loc, $c);
    $past->forceFill(['valid_to' => now()->subMinute(), 'valid_from' => now()->subDay()])->save();

    expect(app(EvaluateShop::class)->qualifying($shop))->toBe(0)->and($shop->refresh()->state)->toBe(ShopState::Preparation);
});

it('needs an active location even at the threshold', function () {
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 5]);
    $owner = User::factory()->shop()->create();
    $shop = app(OpenShop::class)->handle($owner, 'Sklep', '5260250274');

    expect(app(EvaluateShop::class)->handle($shop)->state)->toBe(ShopState::Preparation);
    $loc = app(SaveLocation::class)->handle($owner, $shop, new LocationData('Główna', 'Rynek 1', 'Poznań'));
    publishedOffer($owner, $shop, $loc, faunaIds(1)[0]);
    expect($shop->refresh()->state)->toBe(ShopState::Active);
});

it('re-evaluates shops in preparation when the threshold setting changes', function () {
    shopSetting(SettingKey::ShopActivation, ['threshold' => 3, 'offer_limit' => 5]);
    [$shop, $owner, $loc] = openShop();
    publishedOffer($owner, $shop, $loc, faunaIds(1)[0]);
    expect($shop->refresh()->state)->toBe(ShopState::Preparation);

    app(ChangeSetting::class)->handle(new ChangeSettingData(User::factory()->admin()->create(), SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 5], 'test'));

    expect($shop->refresh()->state)->toBe(ShopState::Active);
});

it('grants an activation exception only for admins, with a reason and without overlaps', function () {
    [$shop, $owner] = openShop();
    $admin = User::factory()->admin()->create();
    $from = now()->subHour();
    $to = now()->addDays(30);

    expect(fn () => app(GrantActivationException::class)->handle($owner, $shop, $from, $to, 'x'))->toThrow(AuthorizationException::class);
    expect(fn () => app(GrantActivationException::class)->handle($admin, $shop, $from, $to, ' '))->toThrow(ValidationException::class);
    expect(fn () => app(GrantActivationException::class)->handle($admin, $shop, $to, $from, 'x'))->toThrow(ValidationException::class);

    app(GrantActivationException::class)->handle($admin, $shop, $from, $to, 'pilot');
    expect($shop->refresh()->state)->toBe(ShopState::Active);
    expect(fn () => app(GrantActivationException::class)->handle($admin, $shop, now(), now()->addDay(), 'x'))->toThrow(ValidationException::class);
});

it('keeps the exception from replacing the active location requirement and a future exception from activating', function () {
    $owner = User::factory()->shop()->create();
    $shop = app(OpenShop::class)->handle($owner, 'Sklep', '5260250274');
    $admin = User::factory()->admin()->create();

    app(GrantActivationException::class)->handle($admin, $shop, now()->subHour(), now()->addDay(), 'pilot');
    expect($shop->refresh()->state)->toBe(ShopState::Preparation); // brak placówki

    [$other] = openShop('7740001454', 'Drugi');
    app(GrantActivationException::class)->handle($admin, $other, now()->addDay(), now()->addDays(2), 'przyszłe');
    expect($other->refresh()->state)->toBe(ShopState::Preparation);
});

it('suspends and resumes shops with a reason', function () {
    [$shop] = openShop();
    $admin = User::factory()->admin()->create();
    app(GrantActivationException::class)->handle($admin, $shop, now()->subHour(), now()->addDays(2), 'pilot');

    expect(fn () => app(SuspendShop::class)->handle($admin, $shop, ''))->toThrow(ValidationException::class);
    expect(app(SuspendShop::class)->handle($admin, $shop, 'kontrola')->state)->toBe(ShopState::Suspended);
    expect(fn () => app(SuspendShop::class)->handle($admin, $shop, 'jeszcze raz'))->toThrow(ValidationException::class);
    expect(app(ResumeShop::class)->handle($admin, $shop, 'ok')->state)->toBe(ShopState::Active);
});

it('limits live offers per shop and does not count drafts', function () {
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 2]);
    [$shop, $owner, $loc] = openShop();
    [$a, $b, $c, $d] = faunaIds(4);

    publishedOffer($owner, $shop, $loc, $a);
    $second = publishedOffer($owner, $shop, $loc, $b);
    draftOffer($owner, $shop, $loc, $d); // szkice nie zajmują limitu
    $third = draftOffer($owner, $shop, $loc, $c);

    expect(fn () => app(PublishOffer::class)->handle($owner, $third))->toThrow(ValidationException::class);

    app(WithdrawOffer::class)->handle($owner, $second);
    expect(app(PublishOffer::class)->handle($owner, $third)->state)->toBe(OfferState::Published);
});

it('reads only visible offers of active shops through the public contract', function () {
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 10]);
    [$shop, $owner, $loc] = openShop();
    [$suspended, $suspendedOwner, $suspendedLoc] = openShop('7740001454', 'Wstrzymany');
    [$a, $b, $c, $d, $e] = faunaIds(5);
    $admin = User::factory()->admin()->create();

    $visible = publishedOffer($owner, $shop, $loc, $a, 4);
    publishedOffer($owner, $shop, $loc, $e, 0);                       // zerowa ilość: widoczna, niedostępna
    draftOffer($owner, $shop, $loc, $b);                               // szkic
    app(WithdrawOffer::class)->handle($owner, publishedOffer($owner, $shop, $loc, $c)); // wycofana
    $future = publishedOffer($owner, $shop, $loc, $d);
    $future->forceFill(['valid_from' => now()->addDay(), 'valid_to' => now()->addDays(5)])->save(); // jeszcze nie ważna
    publishedOffer($suspendedOwner, $suspended, $suspendedLoc, $a, 1);
    app(SuspendShop::class)->handle($admin, $suspended, 'kontrola');

    $result = app(PublicOffers::class)->forRecords(Area::Fauna, [$a, $b, $c, $d, $e, 'NIE-MA']);

    expect(array_keys($result))->toEqualCanonicalizing([$a, $e])
        ->and($result[$a])->toHaveCount(1)
        ->and($result[$a][0]->id)->toBe($visible->id)->and($result[$a][0]->freeQty)->toBe(4)->and($result[$a][0]->priceGrosze)->toBe(1500)
        ->and($result[$a][0]->shopName)->toBe('Sklep')->and($result[$a][0]->locationCity)->toBe('Poznań')
        ->and($result[$e][0]->freeQty)->toBe(0);

    app(SaveLocation::class)->handle($owner, $shop, new LocationData('Główna', 'Rynek 1', 'Poznań', active: false), $loc);
    expect(app(PublicOffers::class)->forRecords(Area::Fauna, [$a]))->toBe([]);
    expect(app(PublicOffers::class)->forRecords(Area::Fauna, []))->toBe([]);
});

it('sorts public offers from the cheapest', function () {
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 10]);
    [$shop, $owner, $loc] = openShop();
    [$id] = faunaIds(1);
    publishedOffer($owner, $shop, $loc, $id);
    app(PublishOffer::class)->handle($owner, app(SaveOffer::class)->handle($owner, $shop, new OfferData($loc->id, Area::Fauna, $id, 'Tania', 900, now()->subHour(), now()->addDay(), 1)));

    $prices = array_map(fn ($o) => $o->priceGrosze, app(PublicOffers::class)->forRecords(Area::Fauna, [$id])[$id]);
    expect($prices)->toBe([900, 1500]);
});

it('shows shop offers for the advisor winner only', function () {
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 10]);
    [$shop, $owner, $loc] = openShop();
    $user = User::factory()->create();
    app(CreateTank::class)->handle($user, new TankData('Nano', 'Ogólne / Towarzyskie', 'nano', 'medium', 'cool', 'neutral'));

    $page = $this->actingAs($user)->get('/doradca')->assertSee('Polecany gatunek:')->assertDontSee('Gdzie kupić');
    preg_match('/Polecany gatunek: (.+?) \(/u', html_entity_decode($page->getContent()), $m);
    $winner = collect(app(Catalog::class)->snapshot(Area::Fauna))->first(fn ($e) => in_array($m[1], [$e->name, $e->nameLatin], true));
    publishedOffer($owner, $shop, $loc, $winner->id, 3);

    $this->actingAs($user)->get('/doradca')->assertSee('Gdzie kupić')->assertSee('Sklep, Główna (Poznań)')->assertSee('15,00 zł')->assertSee('dostępna ilość: 3');
});
