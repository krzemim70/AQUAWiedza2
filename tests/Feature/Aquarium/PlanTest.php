<?php

use App\Domain\Advisor\Engine\Compatibility\Check;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Aquarium\Actions\BuildPlanReport;
use App\Domain\Aquarium\Actions\BuildShoppingList;
use App\Domain\Aquarium\Actions\ChangePlanItem;
use App\Domain\Aquarium\Actions\ConvertPlanToTank;
use App\Domain\Aquarium\Actions\CreatePlan;
use App\Domain\Aquarium\Actions\DeletePlan;
use App\Domain\Aquarium\Data\PlanData;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\Aquarium\Models\TankPlan;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Actions\SaveLocation;
use App\Domain\Shops\Data\LocationData;
use App\Livewire\Aquarium\PlanShow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    app(ImportPackage::class)->handle(kbPackage());
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 20]);
});

function newPlan($user, string $name = 'Salon', string $band = 'medium', ?string $liters = null): TankPlan
{
    return app(CreatePlan::class)->handle($user, new PlanData($name, 'Ogólne / Towarzyskie', $band, $liters));
}

function planItem(TankPlan $plan, $user, string $id, int $qty = 1, Area $area = Area::Fauna): void
{
    app(ChangePlanItem::class)->handle($user, $plan->id, $area, $id, $qty);
}

describe('plan', function () {
    it('saves a plan and derives the band from liters', function () {
        $user = aquarist();

        $plan = newPlan($user, 'Z litrażem', 'nano', '120');

        expect($plan->size_band)->toBe(TankBand::Medium)->and($plan->liters)->toBe(120)->and($plan->state)->toBe(TankPlan::DRAFT);
        expect(fn () => app(CreatePlan::class)->handle($user, new PlanData('Bez wielkości', 'Ogólne / Towarzyskie')))->toThrow(ValidationException::class);
    });

    it('enforces the plan limit from settings and frees it after delete or conversion', function () {
        $user = aquarist();
        $first = newPlan($user);

        expect(fn () => newPlan($user, 'Drugi'))->toThrow(AuthorizationException::class, 'Limit aktywnych planów');

        app(DeletePlan::class)->handle($user, $first->id);
        $second = newPlan($user, 'Drugi');
        app(ConvertPlanToTank::class)->handle($user, $second->id);
        expect(newPlan($user, 'Trzeci')->name)->toBe('Trzeci');

        $fishroom = aquarist(true);
        newPlan($fishroom, 'A');
        newPlan($fishroom, 'B');
        expect(TankPlan::ownedBy($fishroom)->count())->toBe(2);
    });

    it('keeps species in the plan, validates the catalog and removes at zero', function () {
        $user = aquarist();
        $plan = newPlan($user);

        planItem($plan, $user, 'FN0001', 3);
        planItem($plan, $user, 'FN0001', 5);
        planItem($plan, $user, 'FL0011', 2, Area::Flora);
        expect($plan->items()->where('catalog_id', 'FN0001')->sole()->quantity)->toBe(5)->and($plan->items()->count())->toBe(2);

        expect(fn () => planItem($plan, $user, 'FN9999'))->toThrow(ValidationException::class);
        expect(fn () => planItem($plan, $user, 'SP0001', 1, Area::Equipment))->toThrow(ValidationException::class);

        planItem($plan, $user, 'FN0001', 0);
        expect($plan->items()->count())->toBe(1);
    });

    it('hides plans of other users', function () {
        $owner = aquarist();
        $plan = newPlan($owner);
        $other = aquarist();

        expect(fn () => app(BuildPlanReport::class)->handle($other, $plan->id))->toThrow(ModelNotFoundException::class);
        $this->actingAs($other)->get(route('plans.show', $plan->id))->assertNotFound();
        $this->actingAs($owner)->get(route('plans.show', $plan->id))->assertOk()->assertSee('Wybrane przez Ciebie pozycje');
        auth()->logout();
        $this->get(route('plans.index'))->assertRedirect(route('login'));
    });
});

describe('report', function () {
    it('compares the planned stock through the compatibility contract with evidence and lists equipment categories as a catalogue filter', function () {
        $user = aquarist();
        $plan = newPlan($user, 'Nano', 'nano');
        planItem($plan, $user, 'FN0001');
        planItem($plan, $user, 'FN0165');

        $report = app(BuildPlanReport::class)->handle($user, $plan->id);
        $finding = collect($report->compatibility->findings)->firstWhere('check', Check::TemperaturePair);

        expect($finding)->not->toBeNull()->and($finding->evidence)->not->toBeEmpty()->and($report->lines)->toHaveCount(2);

        $expected = collect(app(Catalog::class)->snapshot(Area::Equipment))
            ->filter(fn ($e) => (in_array(TankBand::Nano->label(), $e->values['WielkośćAkwarium'] ?? [], true) || in_array('Wszystkie', $e->values['WielkośćAkwarium'] ?? [], true))
                && in_array('Ogólne / Towarzyskie', $e->values['TypAkwarium'] ?? [], true))
            ->groupBy(fn ($e) => $e->fields['kategoria_glowna'])->map->count()->sortKeys()->all();
        expect($report->categories)->toBe($expected)->and($expected)->not->toBeEmpty();

        $category = array_key_first($expected);
        $listed = app(BuildPlanReport::class)->handle($user, $plan->id, $category);
        expect($listed->equipment)->toHaveCount($expected[$category])
            ->and(app(BuildPlanReport::class)->handle($user, $plan->id, 'Nieistniejąca')->category)->toBeNull();
    });

    it('renders the plan page without the word "potrzebne"', function () {
        $user = aquarist();
        $plan = newPlan($user);
        planItem($plan, $user, 'FN0001');

        $this->actingAs($user)->get(route('plans.show', $plan->id))->assertOk()
            ->assertSee('Porównanie z katalogiem')->assertSee('Sprzęt w katalogu do przejrzenia')
            ->assertDontSee('potrzebn');
    });
});

describe('shopping list', function () {
    function shoppingFixture(): array
    {
        [$shopA, $ownerA, $locationA] = openShop(name: 'Sklep Poznań');
        [$shopB, $ownerB, $locationB] = openShop('7740001454', 'Sklep Warszawa');
        $locationB->update(['city' => 'Warszawa']);
        $branch = app(SaveLocation::class)->handle($ownerA, $shopA, new LocationData('Filia', 'Półwiejska 1', 'Poznań'));

        $first = publishedOffer($ownerA, $shopA, $locationA, 'FN0001', 5);
        $short = publishedOffer($ownerA, $shopA, $locationA, 'FN0002', 2);
        $branchOffer = publishedOffer($ownerA, $shopA, $branch, 'FN0001', 9);
        $far = publishedOffer($ownerB, $shopB, $locationB, 'FN0002', 7);

        return compact('first', 'short', 'branchOffer', 'far', 'locationA', 'branch', 'locationB');
    }

    it('groups offers by shop location with variant, quantity and price and reports items without offers', function () {
        $f = shoppingFixture();
        $user = aquarist();
        $user->update(['city' => 'POZNAN']);
        $plan = newPlan($user);
        planItem($plan, $user, 'FN0001', 3);
        planItem($plan, $user, 'FN0002', 4);
        planItem($plan, $user, 'FN0165', 1);

        $list = app(BuildShoppingList::class)->handle($user, $plan->id);

        expect($list->city)->toBe('POZNAN')->and($list->groups)->toHaveCount(2)
            ->and($list->groups[0]['location'])->toBe('Główna')->and($list->groups[0]['lines'])->toHaveCount(2)
            ->and($list->groups[1]['location'])->toBe('Filia')->and($list->groups[1]['lines'])->toHaveCount(1);
        $line = collect($list->groups[0]['lines'])->firstWhere('id', 'FN0002');
        expect($line['offerId'])->toBe($f['short']->id)->and($line['freeQty'])->toBe(2)->and($line['wanted'])->toBe(4)
            ->and($line['priceGrosze'])->toBe(1500)->and($line)->toHaveKey('variant')
            ->and(collect($list->missing)->pluck('id')->all())->toBe(['FN0165']);
        expect(collect($list->groups)->flatMap(fn ($g) => collect($g['lines'])->pluck('offerId'))->contains($f['far']->id))->toBeFalse();

        $this->actingAs($user)->get(route('plans.show', $plan->id))->assertOk()
            ->assertSee('Sklep Poznań')->assertDontSee('Sklep Warszawa')
            ->assertSee('dostępne 2 z 4')->assertSee('brak ofert w okolicy')->assertSee('15,00 zł');
    });

    it('shows offers from every city when the account has no city, and filters when it has one', function () {
        $f = shoppingFixture();
        $user = aquarist();
        $plan = newPlan($user);
        planItem($plan, $user, 'FN0002', 1);

        $all = app(BuildShoppingList::class)->handle($user, $plan->id);
        expect($all->city)->toBeNull()->and(collect($all->groups)->pluck('shop')->sort()->values()->all())->toBe(['Sklep Poznań', 'Sklep Warszawa']);

        $user->update(['city' => 'Warszawa']);
        $warsaw = app(BuildShoppingList::class)->handle($user->fresh(), $plan->id);
        expect($warsaw->groups)->toHaveCount(1)->and($warsaw->groups[0]['lines'][0]['offerId'])->toBe($f['far']->id);

        $user->update(['city' => 'Gdańsk']);
        $none = app(BuildShoppingList::class)->handle($user->fresh(), $plan->id);
        expect($none->groups)->toBe([])->and($none->missing)->toHaveCount(1);
    });

    it('adds a single line to the cart through the cart action, limited to the available quantity', function () {
        $f = shoppingFixture();
        $user = aquarist();
        $user->update(['city' => 'Poznań']);
        $plan = newPlan($user);
        planItem($plan, $user, 'FN0002', 4);

        $this->actingAs($user)->get(route('plans.show', $plan->id))->assertOk()
            ->assertSee('name="offer_id" value="'.$f['short']->id.'"', false)->assertSee('name="qty" value="2"', false);
        $this->post(route('cart.add'), ['offer_id' => $f['short']->id, 'qty' => 2])->assertRedirect(route('cart.show'));

        $this->assertDatabaseHas('shop_cart_items', ['user_id' => $user->id, 'offer_id' => $f['short']->id, 'qty' => 2]);
    });
});

describe('conversion', function () {
    it('creates one tank with the stock and liters, once', function () {
        $user = aquarist();
        $plan = newPlan($user, 'Docelowy', 'small', '80');
        planItem($plan, $user, 'FN0001', 4);
        planItem($plan, $user, 'FL0011', 2, Area::Flora);

        $tank = app(ConvertPlanToTank::class)->handle($user, $plan->id);
        $again = app(ConvertPlanToTank::class)->handle($user, $plan->id);

        expect($again->id)->toBe($tank->id)->and(Tank::count())->toBe(1)
            ->and($tank->name)->toBe('Docelowy')->and($tank->size_band)->toBe(TankBand::Small)->and($tank->volume_liters)->toBe(80)
            ->and(Livestock::where('tank_id', $tank->id)->orderBy('id')->pluck('quantity', 'catalog_id')->all())->toBe(['FN0001' => 4, 'FL0011' => 2])
            ->and($plan->fresh()->state)->toBe(TankPlan::CONVERTED)->and($plan->fresh()->converted_tank_id)->toBe($tank->id);
        expect(fn () => planItem($plan, $user, 'FN0002'))->toThrow(ValidationException::class);
    });

    it('refuses when the tank limit is reached and changes nothing', function () {
        $user = aquarist();
        tankFor($user);
        $plan = newPlan($user);
        planItem($plan, $user, 'FN0001');

        expect(fn () => app(ConvertPlanToTank::class)->handle($user, $plan->id))->toThrow(AuthorizationException::class, 'zbiornik');

        expect(Tank::count())->toBe(1)->and($plan->fresh()->state)->toBe(TankPlan::DRAFT)->and(Livestock::count())->toBe(0);

        Livewire::actingAs($user)->test(PlanShow::class, ['plan' => $plan->id])->call('convert')->assertHasErrors('form');
    });
});

it('includes plans in the account export and removes them with the account', function () {
    $user = aquarist();
    $plan = newPlan($user, 'Eksportowany');
    planItem($plan, $user, 'FN0001', 2);

    $this->actingAs($user)->post(route('account.privacy.export'))->assertOk()
        ->assertJsonCount(1, 'tank_plans')->assertJsonPath('tank_plans.0.name', 'Eksportowany')->assertJsonPath('tank_plans.0.items.0.quantity', 2);
    $this->delete(route('account.privacy.delete'), ['current_password' => 'password', 'confirmation' => 'USUŃ'])->assertRedirect(route('login'));

    $this->assertDatabaseMissing('tank_plans', ['user_id' => $user->id]);
    $this->assertDatabaseCount('tank_plan_items', 0);
});
