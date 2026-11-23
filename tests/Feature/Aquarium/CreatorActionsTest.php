<?php

use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Domain\Aquarium\Models\Equipment;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\Aquarium\Models\TankPlan;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Actions\PublishOffer;
use App\Domain\Shops\Actions\SaveOffer;
use App\Domain\Shops\Data\OfferData;
use App\Domain\Shops\Models\Location;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\RecordWatch;
use App\Domain\Shops\Models\Shop;
use App\Models\User;

beforeEach(function () {
    app(ImportPackage::class)->handle(kbPackage());
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 50]);
});

function createCreatorOffer(
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

describe('step 6 offers endpoint and guest toggle', function () {
    it('requires city for guests, returns Poznań offers with prices and respects CreatorGuestOffers setting', function () {
        [$shopA, $ownerA, $locPoznan] = openShop('5260250274', 'Sklep Poznań');
        [$shopB, $ownerB, $locWarsaw] = openShop('7740001454', 'Sklep Warszawa');
        $locWarsaw->update(['city' => 'Warszawa']);

        $offerFish = createCreatorOffer($ownerA, $shopA, $locPoznan, Area::Fauna, 'FN0001', 1500, 5);
        $offerCare = createCreatorOffer($ownerA, $shopA, $locPoznan, Area::Care, 'CH0007', 2200, 2);
        createCreatorOffer($ownerB, $shopB, $locWarsaw, Area::Fauna, 'FN0001', 900, 10);

        $payload = [
            'city' => null,
            'items' => [
                ['area' => 'fauna', 'id' => 'FN0001', 'qty' => 3],
                ['area' => 'care', 'id' => 'CH0007', 'qty' => 4],
                ['area' => 'flora', 'id' => 'FL0001', 'qty' => 1],
            ],
        ];

        $this->postJson(route('creator.offers'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('city');

        $response = $this->postJson(route('creator.offers'), ['city' => 'Poznań'] + $payload)
            ->assertOk()
            ->assertJsonPath('city', 'Poznań')
            ->assertJsonCount(1, 'groups')
            ->assertJsonPath('groups.0.locationId', $locPoznan->id)
            ->assertJsonPath('groups.0.coveredCount', 2)
            ->assertJsonPath('groups.0.fullyCoveredCount', 1)
            ->assertJsonPath('groups.0.totalGrosze', 3 * 1500 + 2 * 2200)
            ->assertJsonCount(1, 'missing')
            ->assertJsonPath('missing.0.id', 'FL0001');

        $lines = collect($response->json('groups.0.lines'))->keyBy('offerId');
        expect($lines[$offerFish->id]['priceFormatted'])->toBe('15,00 zł')
            ->and($lines[$offerFish->id]['takeQty'])->toBe(3)
            ->and($lines[$offerFish->id]['isPartial'])->toBeFalse()
            ->and($lines[$offerCare->id]['takeQty'])->toBe(2)
            ->and($lines[$offerCare->id]['isPartial'])->toBeTrue();

        shopSetting(SettingKey::CreatorGuestOffers, false);

        $this->get(route('creator.show'))
            ->assertOk()
            ->assertSee('guestOffersAllowed')
            ->assertSee('"guestOffersAllowed":false', false);

        $this->postJson(route('creator.offers'), ['city' => 'Poznań'] + $payload)
            ->assertForbidden();

        $this->post(route('creator.offers'), ['city' => 'Poznań'] + $payload)
            ->assertRedirect(route('login'));

        $tankUser = aquarist();
        $this->actingAs($tankUser)
            ->postJson(route('creator.offers'), ['city' => 'Poznań'] + $payload)
            ->assertOk()
            ->assertJsonCount(1, 'groups');
    });
});

describe('guest to login, consents and saving plan', function () {
    it('preserves save intent across login and consents, saves 4-area plan and replaces existing Tank draft', function () {
        $user = aquarist();
        shopSetting(SettingKey::ConsentVersion, '2');

        $this->get(route('creator.auth'))
            ->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('creator.show', ['akcja' => 'zapisz']));

        $this->get(route('creator.show', ['akcja' => 'zapisz']))
            ->assertRedirect(route('consents'));

        $this->post(route('consents'), [
            'terms' => '1',
            'privacy' => '1',
            'adult' => '1',
        ])->assertRedirect(route('creator.show', ['akcja' => 'zapisz']));

        $this->get(route('creator.show', ['akcja' => 'zapisz']))
            ->assertOk()
            ->assertSee('"initialAction":"zapisz"', false);

        $savePayload = [
            'name' => 'Plan główny',
            'type' => 'Roślinne',
            'size_band' => 'medium',
            'liters' => 120,
            'temp_band' => TemperatureBand::Warm->value,
            'ph_band' => PhBand::Neutral->value,
            'items' => [
                ['area' => 'fauna', 'id' => 'FN0001', 'qty' => 4],
                ['area' => 'flora', 'id' => 'FL0001', 'qty' => 2],
                ['area' => 'equipment', 'id' => 'SP0001', 'qty' => 1],
                ['area' => 'care', 'id' => 'CH0007', 'qty' => 1],
            ],
        ];

        $first = $this->postJson(route('creator.save-plan'), $savePayload)
            ->assertOk()
            ->assertJsonStructure(['planId', 'planUrl', 'message']);

        $planId = (int) $first->json('planId');
        $plan = TankPlan::findOrFail($planId);
        expect($plan->items()->count())->toBe(4)
            ->and($plan->liters)->toBe(120)
            ->and($plan->size_band)->toBe(TankBand::Medium)
            ->and($plan->temp_band)->toBe(TemperatureBand::Warm)
            ->and($plan->ph_band)->toBe(PhBand::Neutral);

        $this->postJson(route('creator.save-plan'), [
            'name' => 'Drugi plan',
            'type' => 'Ogólne / Towarzyskie',
            'size_band' => 'small',
            'items' => [
                ['area' => 'fauna', 'id' => 'FN0001', 'qty' => 2],
            ],
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'plan_limit')
            ->assertJsonPath('existingDraftPlans.0.id', $planId)
            ->assertJsonPath('existingDraftPlans.0.name', 'Plan główny');

        $this->postJson(route('creator.save-plan'), [
            'name' => 'Plan zastąpiony',
            'type' => 'Ogólne / Towarzyskie',
            'size_band' => 'small',
            'replace_plan_id' => $planId,
            'items' => [
                ['area' => 'fauna', 'id' => 'FN0002', 'qty' => 6],
                ['area' => 'care', 'id' => 'CH0007', 'qty' => 2],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('planId', $planId);

        $plan->refresh();
        expect($plan->name)->toBe('Plan zastąpiony')
            ->and($plan->size_band)->toBe(TankBand::Small)
            ->and($plan->items()->pluck('quantity', 'catalog_id')->all())->toEqualCanonicalizing([
                'FN0002' => 6,
                'CH0007' => 2,
            ]);
    });

    it('blocks guest and unconsented user from saving plans', function () {
        $payload = [
            'name' => 'Gość',
            'type' => 'Ogólne / Towarzyskie',
            'size_band' => 'medium',
            'items' => [['area' => 'fauna', 'id' => 'FN0001', 'qty' => 1]],
        ];

        $this->postJson(route('creator.save-plan'), $payload)->assertUnauthorized();

        $user = aquarist();
        shopSetting(SettingKey::ConsentVersion, '2');

        $this->actingAs($user)
            ->post(route('creator.save-plan'), $payload)
            ->assertRedirect(route('consents'));
    });
});

describe('cart, watch and convert actions', function () {
    it('adds available quantities for a location to cart and reports exact shortfall', function () {
        [$shop, $owner, $location] = openShop('5260250274', 'Sklep Testowy');
        $fullOffer = createCreatorOffer($owner, $shop, $location, Area::Fauna, 'FN0001', 1500, 5);
        $partialOffer = createCreatorOffer($owner, $shop, $location, Area::Flora, 'FL0001', 1200, 2);
        $buyer = aquarist();

        $this->postJson(route('creator.cart'), [
            'lines' => [
                ['offer_id' => $fullOffer->id, 'wanted' => 5],
                ['offer_id' => $partialOffer->id, 'wanted' => 4],
            ],
        ])->assertUnauthorized();

        $response = $this->actingAs($buyer)
            ->postJson(route('creator.cart'), [
                'lines' => [
                    ['offer_id' => $fullOffer->id, 'wanted' => 5],
                    ['offer_id' => $partialOffer->id, 'wanted' => 4],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('addedLines', 2)
            ->assertJsonPath('addedUnits', 7)
            ->assertJsonPath('shortfalls.0.offerId', $partialOffer->id)
            ->assertJsonPath('shortfalls.0.missing', 2)
            ->assertJsonPath('cartUrl', route('cart.show'));

        expect($response->json('message'))->toContain('brakuje 2 szt.');

        $this->assertDatabaseHas('shop_cart_items', [
            'user_id' => $buyer->id,
            'offer_id' => $fullOffer->id,
            'qty' => 5,
        ]);
        $this->assertDatabaseHas('shop_cart_items', [
            'user_id' => $buyer->id,
            'offer_id' => $partialOffer->id,
            'qty' => 2,
        ]);
    });

    it('creates a record watch for missing item in user city', function () {
        [$shop, $owner, $locWarsaw] = openShop('5260250274', 'Sklep Warszawa');
        $locWarsaw->update(['city' => 'Warszawa']);
        createCreatorOffer($owner, $shop, $locWarsaw, Area::Fauna, 'FN0001', 1500, 5);

        $user = aquarist();
        $user->update(['city' => 'Poznań']);

        $this->postJson(route('creator.watch'), ['area' => 'fauna', 'id' => 'FN0001'])->assertUnauthorized();

        $this->actingAs($user)
            ->postJson(route('creator.watch'), [
                'area' => 'fauna',
                'id' => 'FN0001',
            ])
            ->assertOk()
            ->assertJsonPath('watched', true);

        expect(RecordWatch::where('user_id', $user->id)
            ->where('area', Area::Fauna)
            ->where('legacy_id', 'FN0001')
            ->pending()
            ->exists())->toBeTrue();
    });

    it('converts saved plan into tank with livestock and equipment while skipping Care and enforcing TankLimit', function () {
        $user = aquarist();

        $save = $this->actingAs($user)->postJson(route('creator.save-plan'), [
            'name' => 'Akwarium z kreatora',
            'type' => 'Roślinne',
            'size_band' => 'medium',
            'liters' => 140,
            'temp_band' => TemperatureBand::Warm->value,
            'ph_band' => PhBand::Neutral->value,
            'items' => [
                ['area' => 'fauna', 'id' => 'FN0001', 'qty' => 5],
                ['area' => 'flora', 'id' => 'FL0001', 'qty' => 3],
                ['area' => 'equipment', 'id' => 'SP0001', 'qty' => 1],
                ['area' => 'care', 'id' => 'CH0007', 'qty' => 2],
            ],
        ])->assertOk();

        $planId = (int) $save->json('planId');

        $converted = $this->actingAs($user)
            ->postJson(route('creator.convert', ['plan' => $planId]))
            ->assertOk()
            ->assertJsonStructure(['tankId', 'tankUrl', 'message']);

        $tankId = (int) $converted->json('tankId');
        expect($converted->json('tankUrl'))->toBe(route('aquarium.tank', $tankId));

        $tank = Tank::findOrFail($tankId);
        expect($tank->name)->toBe('Akwarium z kreatora')
            ->and($tank->volume_liters)->toBe(140)
            ->and(Livestock::where('tank_id', $tankId)->pluck('quantity', 'catalog_id')->all())->toBe([
                'FN0001' => 5,
                'FL0001' => 3,
            ])
            ->and(Equipment::where('tank_id', $tankId)->pluck('quantity', 'catalog_id')->all())->toBe([
                'SP0001' => 1,
            ])
            ->and(Livestock::where('tank_id', $tankId)->where('catalog_id', 'CH0007')->exists())->toBeFalse()
            ->and(Equipment::where('tank_id', $tankId)->where('catalog_id', 'CH0007')->exists())->toBeFalse();

        $secondSave = $this->actingAs($user)->postJson(route('creator.save-plan'), [
            'name' => 'Drugi plan po konwersji',
            'type' => 'Ogólne / Towarzyskie',
            'size_band' => 'small',
            'items' => [
                ['area' => 'fauna', 'id' => 'FN0002', 'qty' => 4],
            ],
        ])->assertOk();

        $secondPlanId = (int) $secondSave->json('planId');

        $this->actingAs($user)
            ->postJson(route('creator.convert', ['plan' => $secondPlanId]))
            ->assertForbidden();

        expect(Tank::ownedBy($user)->count())->toBe(1);
    });
});
