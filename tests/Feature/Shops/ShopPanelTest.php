<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use App\Domain\Shops\Models\ShopState;
use App\Domain\Shops\Models\StockMovement;
use App\Filament\Resources\Shops\Pages\ListShops;
use App\Filament\Shop\Resources\Locations\LocationResource;
use App\Filament\Shop\Resources\Locations\Pages\ListLocations;
use App\Filament\Shop\Resources\Members\MemberResource;
use App\Filament\Shop\Resources\Members\Pages\ListMembers;
use App\Filament\Shop\Resources\Offers\Pages\ListOffers;
use App\Filament\Shop\Widgets\ActivationProgress;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

it('shows only the tenant shop offers and teams to its members', function () {
    [$shopA, $ownerA, $locA] = openShop();
    [$shopB, $ownerB, $locB] = openShop('7740001454', 'Drugi');
    [$id1, $id2] = faunaIds(2);
    $a = draftOffer($ownerA, $shopA, $locA, $id1);
    $b = draftOffer($ownerB, $shopB, $locB, $id2);
    $staffA = staffOf($shopA, $ownerA);

    inShopPanel($shopA, $staffA);
    Livewire::test(ListOffers::class)->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
    expect(Filament::auth()->user()->canAccessTenant($shopB))->toBeFalse();

    // pracownik nie widzi placówek ani zespołu, właściciel tak
    expect(MemberResource::canViewAny())->toBeFalse()
        ->and(LocationResource::canViewAny())->toBeFalse();
    inShopPanel($shopA, $ownerA);
    expect(MemberResource::canViewAny())->toBeTrue();
});

it('creates an offer from the catalogue search, publishes it and changes the quantity from the panel', function () {
    [$shop, $owner, $loc] = openShop();
    [$id] = faunaIds(1);
    inShopPanel($shop, $owner);

    Livewire::test(ListOffers::class)
        ->callAction('create', ['record' => "fauna:$id", 'location_id' => $loc->id, 'variant' => 'M', 'price' => '12.50', 'valid_from' => now()->subHour(), 'valid_to' => now()->addDays(10), 'free_qty' => 4])
        ->assertHasNoActionErrors();
    $offer = Offer::firstOrFail();
    expect($offer->price_grosze)->toBe(1250)->and($offer->state)->toBe(OfferState::Draft);

    Livewire::test(ListOffers::class)
        ->callTableAction('publish', $offer)
        ->callTableAction('quantity', $offer, ['version' => $offer->refresh()->version, 'qty' => 9, 'confirmed' => true, 'reason' => 'dostawa'])
        ->assertHasNoTableActionErrors();
    expect($offer->refresh()->state)->toBe(OfferState::Published)->and($offer->free_qty)->toBe(9);

    Livewire::test(ListOffers::class)->callTableAction('quantity', $offer, ['version' => 1, 'qty' => 2, 'confirmed' => true]);
    expect($offer->refresh()->free_qty)->toBe(9)->and(StockMovement::where('offer_id', $offer->id)->count())->toBe(2);

    expect(view('shops.offer-history', ['movements' => $offer->movements()->with('actor')->get()])->render())->toContain('dostawa', 'Stan otwarcia', '+5');
});

it('requires the physical confirmation checkbox in the quantity form', function () {
    [$shop, $owner, $loc] = openShop();
    $offer = draftOffer($owner, $shop, $loc, faunaIds(1)[0]);
    inShopPanel($shop, $owner);

    Livewire::test(ListOffers::class)
        ->callTableAction('quantity', $offer, ['version' => 1, 'qty' => 3, 'confirmed' => false])
        ->assertHasTableActionErrors(['confirmed']);
    expect($offer->refresh()->free_qty)->toBe(5);
});

it('manages locations and the team from the panel and shows activation progress', function () {
    shopSetting(SettingKey::ShopActivation, ['threshold' => 3, 'offer_limit' => 9]);
    [$shop, $owner] = openShop();
    $newcomer = User::factory()->create();
    inShopPanel($shop, $owner);

    Livewire::test(ListLocations::class)->callAction('create', ['name' => 'Filia', 'address' => 'Ul. 5', 'city' => 'Gdańsk', 'active' => true]);
    expect($shop->locations()->count())->toBe(2);

    Livewire::test(ListMembers::class)->callAction('add', ['email' => $newcomer->email])->assertHasNoActionErrors();
    $member = $shop->members()->where('user_id', $newcomer->id)->firstOrFail();
    Livewire::test(ListMembers::class)->callTableAction('remove', $member);
    expect($shop->members()->count())->toBe(1);

    Livewire::test(ActivationProgress::class)->assertSee('0 / 3')->assertSee('W przygotowaniu');
});

it('lets an admin grant an exception, suspend and resume a shop', function () {
    [$shop] = openShop();
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(ListShops::class)->assertCanSeeTableRecords([$shop])
        ->callTableAction('exception', $shop, ['from' => now()->subHour(), 'to' => now()->addDays(14), 'reason' => 'pilot'])
        ->assertHasNoTableActionErrors();
    expect($shop->refresh()->state)->toBe(ShopState::Active);

    Livewire::test(ListShops::class)->callTableAction('suspend', $shop, ['reason' => 'kontrola']);
    expect($shop->refresh()->state)->toBe(ShopState::Suspended);
    Livewire::test(ListShops::class)->callTableAction('resume', $shop, ['reason' => 'ok']);
    expect($shop->refresh()->state)->toBe(ShopState::Active);
});

it('opens the shop panel dashboard for a member and keeps outsiders out', function () {
    [$shop, $owner] = openShop();

    $this->actingAs($owner)->get("/panel-sklepu/{$shop->id}")->assertOk();
    $this->actingAs(User::factory()->create())->get("/panel-sklepu/{$shop->id}")->assertForbidden();
    [$other, $otherOwner] = openShop('7740001454', 'Drugi');
    $this->actingAs($otherOwner)->get("/panel-sklepu/{$shop->id}")->assertNotFound();
});
