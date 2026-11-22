<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\Shops\Models\Location;
use App\Domain\Shops\Models\Offer;
use App\Filament\Shop\Resources\Locations\LocationResource;
use App\Filament\Shop\Widgets\MissingCity;
use Livewire\Livewire;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

it('warns on the dashboard about a location without a city and links to editing', function () {
    [$shop, $owner, $location] = openShop();
    Location::whereKey($location->id)->update(['city' => '']);
    inShopPanel($shop, $owner);

    Livewire::test(MissingCity::class)->assertSee('Uzupełnij miasto placówki')->assertSee('Główna')->assertSee(LocationResource::getUrl('index'), false);
});

it('shows nothing when every location has a city', function () {
    [$shop, $owner] = openShop();
    inShopPanel($shop, $owner);

    Livewire::test(MissingCity::class)->assertDontSee('Uzupełnij miasto placówki');
});

it('keeps offers from a location without a city live', function () {
    [$shop, $owner, $location] = openShop();
    $offer = publishedOffer($owner, $shop, $location, faunaIds(1)[0]);
    Location::whereKey($location->id)->update(['city' => '']);

    expect(Offer::live()->whereKey($offer->id)->exists())->toBeTrue();
});
