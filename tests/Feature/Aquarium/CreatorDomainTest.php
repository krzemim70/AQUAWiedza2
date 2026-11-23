<?php

use App\Domain\Advisor\Engine\Compatibility\Check;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Domain\Aquarium\Actions\BuildPlanReport;
use App\Domain\Aquarium\Actions\BuildShoppingList;
use App\Domain\Aquarium\Actions\ConvertPlanToTank;
use App\Domain\Aquarium\Actions\SaveCreatorPlan;
use App\Domain\Aquarium\Actions\SplitShoppingList;
use App\Domain\Aquarium\Data\CreatorLine;
use App\Domain\Aquarium\Data\PlanData;
use App\Domain\Aquarium\Models\Equipment;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\Aquarium\Models\TankPlan;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Actions\AddLocationOffersToCart;
use App\Domain\Shops\Actions\PublishOffer;
use App\Domain\Shops\Actions\SaveLocation;
use App\Domain\Shops\Actions\SaveOffer;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Domain\Shops\Data\LocationData;
use App\Domain\Shops\Data\OfferData;
use App\Domain\Shops\Models\Location;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app(ImportPackage::class)->handle(kbPackage());
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 50]);
});

function creatorOffer(
    User $actor,
    Shop $shop,
    Location $location,
    Area $area,
    string $legacyId,
    int $priceGrosze = 1500,
    int $freeQty = 5,
    string $variant = '',
): Offer {
    $offer = app(SaveOffer::class)->handle(
        $actor,
        $shop,
        new OfferData($location->id, $area, $legacyId, $variant, $priceGrosze, now()->subHour(), now()->addDays(30), $freeQty),
    );

    return app(PublishOffer::class)->handle($actor, $offer);
}

describe('creator plan saving', function () {
    it('saves a plan with all four areas and optional bands, enforces Tank limit and replaces own draft', function () {
        $tankUser = aquarist();
        $otherUser = aquarist();

        $plan = app(SaveCreatorPlan::class)->handle(
            $tankUser,
            new PlanData('Kreator 1', 'Roślinne', '', '120', TemperatureBand::Warm->value, PhBand::Neutral->value),
            [
                new CreatorLine(Area::Fauna, 'FN0001', 4),
                new CreatorLine(Area::Flora, 'FL0001', 2),
                new CreatorLine(Area::Equipment, 'SP0001', 1),
                new CreatorLine(Area::Care, 'CH0007', 1),
            ],
        );

        expect($plan->size_band)->toBe(TankBand::Medium)
            ->and($plan->liters)->toBe(120)
            ->and($plan->temp_band)->toBe(TemperatureBand::Warm)
            ->and($plan->ph_band)->toBe(PhBand::Neutral)
            ->and($plan->items()->count())->toBe(4);

        expect(fn () => app(SaveCreatorPlan::class)->handle(
            $tankUser,
            new PlanData('Drugi', 'Ogólne / Towarzyskie', 'small'),
            [new CreatorLine(Area::Fauna, 'FN0001', 1)],
        ))->toThrow(AuthorizationException::class, 'Limit aktywnych planów');

        $replaced = app(SaveCreatorPlan::class)->handle(
            $tankUser,
            new PlanData('Kreator po zmianie', 'Ogólne / Towarzyskie', 'small', null, TemperatureBand::Cool->value, PhBand::Acidic->value),
            [
                new CreatorLine(Area::Fauna, 'FN0002', 6),
                new CreatorLine(Area::Care, 'CH0007', 2),
            ],
            $plan->id,
        );

        expect($replaced->id)->toBe($plan->id)
            ->and($replaced->name)->toBe('Kreator po zmianie')
            ->and($replaced->size_band)->toBe(TankBand::Small)
            ->and($replaced->temp_band)->toBe(TemperatureBand::Cool)
            ->and($replaced->ph_band)->toBe(PhBand::Acidic)
            ->and($replaced->items()->pluck('quantity', 'catalog_id')->all())->toEqualCanonicalizing(['FN0002' => 6, 'CH0007' => 2]);

        expect(fn () => app(SaveCreatorPlan::class)->handle(
            $otherUser,
            new PlanData('Obcy', 'Ogólne / Towarzyskie', 'small'),
            [new CreatorLine(Area::Fauna, 'FN0001', 1)],
            $plan->id,
        ))->toThrow(ModelNotFoundException::class);

        app(ConvertPlanToTank::class)->handle($tankUser, $plan->id);

        expect(fn () => app(SaveCreatorPlan::class)->handle(
            $tankUser,
            new PlanData('Po konwersji', 'Ogólne / Towarzyskie', 'small'),
            [new CreatorLine(Area::Fauna, 'FN0001', 1)],
            $plan->id,
        ))->toThrow(ValidationException::class);
    });

    it('validates duplicate items, catalog existence, quantity range and equipment limit', function () {
        $user = aquarist(true);

        expect(fn () => app(SaveCreatorPlan::class)->handle(
            $user,
            new PlanData('Duplikat', 'Ogólne / Towarzyskie', 'small'),
            [
                new CreatorLine(Area::Fauna, 'FN0001', 2),
                new CreatorLine(Area::Fauna, 'FN0001', 3),
            ],
        ))->toThrow(ValidationException::class);

        expect(fn () => app(SaveCreatorPlan::class)->handle(
            $user,
            new PlanData('Nieznany', 'Ogólne / Towarzyskie', 'small'),
            [new CreatorLine(Area::Care, 'CH9999', 1)],
        ))->toThrow(ValidationException::class);

        expect(fn () => app(SaveCreatorPlan::class)->handle(
            $user,
            new PlanData('Ilość', 'Ogólne / Towarzyskie', 'small'),
            [new CreatorLine(Area::Fauna, 'FN0001', 0)],
        ))->toThrow(ValidationException::class);

        shopSetting(SettingKey::EquipmentLimit, 1);
        expect(fn () => app(SaveCreatorPlan::class)->handle(
            $user,
            new PlanData('Za dużo sprzętu', 'Ogólne / Towarzyskie', 'small'),
            [
                new CreatorLine(Area::Equipment, 'SP0001', 1),
                new CreatorLine(Area::Equipment, 'SP0045', 1),
            ],
        ))->toThrow(ValidationException::class);
    });
});

describe('creator conversion and report', function () {
    it('converts a four-area plan into a tank while skipping Care items completely', function () {
        $user = aquarist();
        $plan = app(SaveCreatorPlan::class)->handle(
            $user,
            new PlanData('Docelowy z kreatora', 'Roślinne', '', '160', TemperatureBand::Warm->value, PhBand::Neutral->value),
            [
                new CreatorLine(Area::Fauna, 'FN0001', 4),
                new CreatorLine(Area::Flora, 'FL0001', 2),
                new CreatorLine(Area::Equipment, 'SP0001', 1),
                new CreatorLine(Area::Care, 'CH0007', 3),
            ],
        );

        $tank = app(ConvertPlanToTank::class)->handle($user, $plan->id);

        expect($tank->name)->toBe('Docelowy z kreatora')
            ->and($tank->size_band)->toBe(TankBand::Medium)
            ->and($tank->volume_liters)->toBe(160)
            ->and($tank->temp_band)->toBe(TemperatureBand::Warm)
            ->and($tank->ph_band)->toBe(PhBand::Neutral)
            ->and(Livestock::where('tank_id', $tank->id)->orderBy('id')->pluck('quantity', 'catalog_id')->all())->toBe([
                'FN0001' => 4,
                'FL0001' => 2,
            ])
            ->and(Equipment::where('tank_id', $tank->id)->pluck('quantity', 'catalog_id')->all())->toBe([
                'SP0001' => 1,
            ])
            ->and(Livestock::where('tank_id', $tank->id)->where('catalog_id', 'CH0007')->exists())->toBeFalse()
            ->and(Equipment::where('tank_id', $tank->id)->where('catalog_id', 'CH0007')->exists())->toBeFalse()
            ->and($plan->fresh()->state)->toBe(TankPlan::CONVERTED);
    });

    it('builds plan report without counting Equipment or Care as unknown and detects Co2 dosing', function () {
        $user = aquarist(true);

        $withoutCo2 = app(SaveCreatorPlan::class)->handle(
            $user,
            new PlanData('Bez CO2', 'Roślinne', 'medium'),
            [
                new CreatorLine(Area::Flora, 'FL0011', 2),
                new CreatorLine(Area::Care, 'CH0007', 1),
            ],
        );
        $reportWithout = app(BuildPlanReport::class)->handle($user, $withoutCo2->id);
        expect($reportWithout->compatibility->unknown)->toBe(0)
            ->and($reportWithout->compatibility->findingsFor(Check::Co2Missing))->toBe(1);

        $withCo2 = app(SaveCreatorPlan::class)->handle(
            $user,
            new PlanData('Z CO2', 'Roślinne', 'medium', null, TemperatureBand::Warm->value, PhBand::Neutral->value),
            [
                new CreatorLine(Area::Flora, 'FL0011', 2),
                new CreatorLine(Area::Equipment, 'SP0099', 1),
                new CreatorLine(Area::Care, 'CH0007', 1),
            ],
        );
        $reportWith = app(BuildPlanReport::class)->handle($user, $withCo2->id);

        expect($reportWith->compatibility->unknown)->toBe(0)
            ->and($reportWith->compatibility->findingsFor(Check::Co2Missing))->toBe(0)
            ->and($reportWith->lines)->toHaveCount(3)
            ->and(collect($reportWith->lines)->every(fn (array $line) => $line['entry'] !== null))->toBeTrue();
    });
});

describe('split shopping list and active cities', function () {
    it('returns sorted unique cities of active locations in active shops', function () {
        [$shopA, $ownerA] = openShop('5260250274', 'Sklep A');
        $shopA->update(['state' => ShopState::Active]);
        app(SaveLocation::class)->handle($ownerA, $shopA, new LocationData('Gdańsk', 'Długa 1', 'Gdańsk'));
        app(SaveLocation::class)->handle($ownerA, $shopA, new LocationData('Kraków nieaktywna', 'Rynek 2', 'Kraków', active: false));

        [$shopB, $ownerB] = openShop('7740001454', 'Sklep B');
        app(SaveLocation::class)->handle($ownerB, $shopB, new LocationData('Warszawa', 'Marszałkowska 1', 'Warszawa'));
        $shopB->update(['state' => ShopState::Preparation]);

        expect(app(PublicOffers::class)->activeCities())->toBe(['Gdańsk', 'Poznań']);
    });

    it('splits lines across all four areas, sorts by coverage then price and enforces guest settings', function () {
        [$shopA, $ownerA, $locA] = openShop('5260250274', 'Sklep Alfa');
        [$shopB, $ownerB, $locB] = openShop('7740001454', 'Sklep Beta');
        $locC = app(SaveLocation::class)->handle($ownerB, $shopB, new LocationData('Filia Taniej', 'Głogowska 5', 'Poznań'));
        $locOtherCity = app(SaveLocation::class)->handle($ownerB, $shopB, new LocationData('Warszawa', 'Prosta 1', 'Warszawa'));

        // locA: covers 2 items (1 fully, 1 partial) -> covered=2, fully=1
        creatorOffer($ownerA, $shopA, $locA, Area::Fauna, 'FN0001', 1000, 2);
        creatorOffer($ownerA, $shopA, $locA, Area::Equipment, 'SP0001', 20000, 1);

        // locB: covers 3 items (all 3 fully) -> covered=3, fully=3, higher price
        creatorOffer($ownerB, $shopB, $locB, Area::Fauna, 'FN0001', 1200, 5);
        creatorOffer($ownerB, $shopB, $locB, Area::Flora, 'FL0001', 900, 5);
        creatorOffer($ownerB, $shopB, $locB, Area::Care, 'CH0007', 2500, 3);

        // locC: covers 3 items (all 3 fully) -> covered=3, fully=3, lower price than locB
        creatorOffer($ownerB, $shopB, $locC, Area::Fauna, 'FN0001', 1000, 5);
        creatorOffer($ownerB, $shopB, $locC, Area::Flora, 'FL0001', 800, 5);
        creatorOffer($ownerB, $shopB, $locC, Area::Care, 'CH0007', 2000, 3);

        // Warsaw location should be filtered out for Poznań
        creatorOffer($ownerB, $shopB, $locOtherCity, Area::Equipment, 'SP0001', 15000, 3);

        $lines = [
            new CreatorLine(Area::Fauna, 'FN0001', 3),
            new CreatorLine(Area::Flora, 'FL0001', 2),
            new CreatorLine(Area::Equipment, 'SP0001', 1),
            new CreatorLine(Area::Care, 'CH0007', 2),
            new CreatorLine(Area::Care, 'CH0008', 1),
        ];

        $splitAction = app(SplitShoppingList::class);

        expect(fn () => $splitAction->forLines(null, $lines, null, guestRequiresCity: true))
            ->toThrow(ValidationException::class);

        $guestSplit = $splitAction->forLines(null, $lines, 'Poznań', guestRequiresCity: true);
        expect($guestSplit->city)->toBe('Poznań')
            ->and($guestSplit->groups)->toHaveCount(3)
            ->and($guestSplit->groups[0]->locationId)->toBe($locC->id)
            ->and($guestSplit->groups[0]->coveredCount)->toBe(3)
            ->and($guestSplit->groups[0]->fullyCoveredCount)->toBe(3)
            ->and($guestSplit->groups[0]->totalGrosze)->toBe(3 * 1000 + 2 * 800 + 2 * 2000)
            ->and($guestSplit->groups[1]->locationId)->toBe($locB->id)
            ->and($guestSplit->groups[2]->locationId)->toBe($locA->id)
            ->and($guestSplit->groups[2]->coveredCount)->toBe(2)
            ->and($guestSplit->groups[2]->fullyCoveredCount)->toBe(1)
            ->and(collect($guestSplit->missing)->pluck('id')->all())->toBe(['CH0008']);

        shopSetting(SettingKey::CreatorGuestOffers, false);

        expect(fn () => $splitAction->forLines(null, $lines, 'Poznań', guestRequiresCity: true))
            ->toThrow(AuthorizationException::class);

        $tankUser = aquarist();
        $tankUser->update(['city' => 'Poznań']);
        $tankSplit = $splitAction->forLines($tankUser, $lines, 'Poznań');
        expect($tankSplit->groups)->toHaveCount(3);

        // BuildShoppingList also includes Equipment and Care
        $plan = app(SaveCreatorPlan::class)->handle(
            $tankUser,
            new PlanData('Zakupy z planu', 'Ogólne / Towarzyskie', 'medium'),
            [
                new CreatorLine(Area::Equipment, 'SP0001', 1),
                new CreatorLine(Area::Care, 'CH0007', 2),
            ],
        );
        $list = app(BuildShoppingList::class)->handle($tankUser, $plan->id);
        expect($list->groups)->toHaveCount(3)
            ->and($list->missing)->toBe([]);
    });
});

describe('batch add location offers to cart', function () {
    it('adds available partial quantity to cart and reports shortfall', function () {
        [$shop, $owner, $location] = openShop('5260250274', 'Sklep Testowy');
        $offer = creatorOffer($owner, $shop, $location, Area::Fauna, 'FN0001', 1500, 2);
        $fullOffer = creatorOffer($owner, $shop, $location, Area::Care, 'CH0007', 2200, 5);
        $buyer = aquarist();

        $result = app(AddLocationOffersToCart::class)->handle($buyer, [
            ['offerId' => $offer->id, 'wanted' => 5],
            ['offerId' => $fullOffer->id, 'wanted' => 3],
        ]);

        expect($result->addedLines)->toBe(2)
            ->and($result->addedUnits)->toBe(5)
            ->and($result->shortfalls)->toHaveCount(1)
            ->and($result->shortfalls[0]['offerId'])->toBe($offer->id)
            ->and($result->shortfalls[0]['name'])->toBe('Gupik Pawie Oczko')
            ->and($result->shortfalls[0]['wanted'])->toBe(5)
            ->and($result->shortfalls[0]['added'])->toBe(2)
            ->and($result->shortfalls[0]['missing'])->toBe(3)
            ->and($result->message())->toContain('Niepełna dostępność')
            ->and($result->message())->toContain('dodano 2 z 5 szt.')
            ->and($result->message())->toContain('brakuje 3 szt.');

        $this->assertDatabaseHas('shop_cart_items', ['user_id' => $buyer->id, 'offer_id' => $offer->id, 'qty' => 2]);
        $this->assertDatabaseHas('shop_cart_items', ['user_id' => $buyer->id, 'offer_id' => $fullOffer->id, 'qty' => 3]);
    });

    it('rejects own shop and conflicting shop locations in cart or request', function () {
        [$shop, $owner, $locA] = openShop('5260250274', 'Sklep Testowy');
        $locB = app(SaveLocation::class)->handle($owner, $shop, new LocationData('Filia', 'Polna 2', 'Poznań'));
        $offerA = creatorOffer($owner, $shop, $locA, Area::Fauna, 'FN0001', 1500, 4);
        $offerB = creatorOffer($owner, $shop, $locB, Area::Flora, 'FL0001', 1200, 4);
        $buyer = aquarist();
        $action = app(AddLocationOffersToCart::class);

        expect(fn () => $action->handle($owner, [['offerId' => $offerA->id, 'wanted' => 1]]))
            ->toThrow(ValidationException::class, 'własnym sklepie');

        expect(fn () => $action->handle($buyer, [
            ['offerId' => $offerA->id, 'wanted' => 1],
            ['offerId' => $offerB->id, 'wanted' => 1],
        ]))->toThrow(ValidationException::class);

        $action->handle($buyer, [['offerId' => $offerA->id, 'wanted' => 1]]);

        expect(fn () => $action->handle($buyer, [['offerId' => $offerB->id, 'wanted' => 1]]))
            ->toThrow(ValidationException::class, 'innej placówce');

        $this->assertDatabaseMissing('shop_cart_items', ['user_id' => $buyer->id, 'offer_id' => $offerB->id]);
    });

    it('returns unavailability message when no offers can be added', function () {
        [$shop, $owner, $location] = openShop('5260250274', 'Sklep Testowy');
        $emptyOffer = creatorOffer($owner, $shop, $location, Area::Fauna, 'FN0001', 1500, 0);
        $buyer = aquarist();

        $result = app(AddLocationOffersToCart::class)->handle($buyer, [
            ['offerId' => $emptyOffer->id, 'wanted' => 2],
        ]);

        expect($result->addedLines)->toBe(0)
            ->and($result->addedUnits)->toBe(0)
            ->and($result->shortfalls)->toHaveCount(1)
            ->and($result->message())->toContain('brak dostępności');
    });
});
