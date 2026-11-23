<?php

use App\Domain\Aquarium\Actions\ArchiveSupply;
use App\Domain\Aquarium\Actions\RecordSupplyEvent;
use App\Domain\Aquarium\Actions\SaveSupply;
use App\Domain\Aquarium\Data\SupplyData;
use App\Domain\Aquarium\Data\SupplyEventData;
use App\Domain\Aquarium\Models\SupplyEventKind;
use App\Domain\Aquarium\Models\TankSupply;
use App\Domain\Aquarium\Models\TankSupplyEvent;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Actions\AdvanceOrder;
use App\Domain\Shops\Actions\ApproveOrder;
use App\Domain\Shops\Actions\ChangeCart;
use App\Domain\Shops\Actions\PublishOffer;
use App\Domain\Shops\Actions\SaveOffer;
use App\Domain\Shops\Actions\SubmitCart;
use App\Domain\Shops\Data\OfferData;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderState;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    app(ImportPackage::class)->handle(kbPackage());
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 20]);
});

afterEach(fn () => Carbon::setTestNow());

it('searches care catalogue, adds per-liter and fixed-portion supplies and displays forecast', function () {
    Carbon::setTestNow(Carbon::parse('2026-12-01 08:00:00', 'UTC'));
    $user = aquarist();
    $tank = tankFor($user);
    $tank->update(['volume_liters' => 63]);
    $tab = ['tank' => $tank->id, 'karta' => 'zapasy'];
    $this->actingAs($user);

    $this->get(route('aquarium.tank', $tab + ['szukaj' => 'Acid Buffer']))->assertOk()
        ->assertSee('CH0089')->assertSee('Acid Buffer');
    $this->get(route('aquarium.tank', $tab + ['szukaj' => 'FN0001']))->assertOk()->assertSee('Brak wyników.');

    // Nieakceptowany rekord spoza pielęgnacji
    $this->post(route('aquarium.supplies.store'), [
        'tank' => $tank->id,
        'catalog_id' => 'FN0001',
        'unit' => 'ml',
        'package_amount' => '250',
        'dose_amount' => '5',
    ])->assertSessionHasErrors('catalog_id', null, 'supply');

    // Preparat na litry: 250 ml, 5 ml / 100 l, zbiornik 63 l, co 7 dni -> 79 * 7 = 553 dni
    $this->post(route('aquarium.supplies.store'), [
        'tank' => $tank->id,
        'catalog_id' => 'CH0089',
        'unit' => 'ml',
        'package_amount' => '250',
        'dose_amount' => '5',
        'dose_per_liters' => '100',
        'interval_days' => 7,
    ])->assertRedirect(route('aquarium.tank', $tab));

    // Karma o stałej porcji: 100 g, 2 g, co 1 dzień -> 50 dni
    $this->post(route('aquarium.supplies.store'), [
        'tank' => $tank->id,
        'catalog_id' => 'CH0021',
        'unit' => 'g',
        'package_amount' => '100',
        'dose_amount' => '2',
        'interval_days' => 1,
    ])->assertRedirect(route('aquarium.tank', $tab));

    $this->get(route('aquarium.tank', $tab))->assertOk()
        ->assertSee('Acid Buffer')
        ->assertSee('zostało około 553 dni')
        ->assertSee('TetraMin Flakes')
        ->assertSee('zostało około 50 dni')
        ->assertSee('Szacunek z harmonogramu użytkownika');

    Carbon::setTestNow(Carbon::parse('2026-12-15 08:00:00', 'UTC'));
    $this->get(route('aquarium.tank', $tab))->assertOk()
        ->assertSee('243,7 ml')
        ->assertSee('zostało około 539 dni')
        ->assertSee('72 g')
        ->assertSee('zostało około 36 dni');
});

it('shows missing tank volume for per-liter supply without guessing from tank band', function () {
    $user = aquarist();
    $tank = tankFor($user);
    expect($tank->volume_liters)->toBeNull();

    $supply = app(SaveSupply::class)->handle($user, $tank->id, new SupplyData(
        catalogId: 'CH0089',
        unit: 'ml',
        packageAmount: '250',
        doseAmount: '5',
        dosePerLiters: '100',
        intervalDays: 7,
    ));

    $this->actingAs($user)->get(route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'zapasy']))->assertOk()
        ->assertSee('brak danych: uzupełnij litraż zbiornika')
        ->assertDontSee('zostało około');

    $this->post(route('aquarium.supplies.event', [$tank->id, $supply->id]), [
        'kind' => 'use',
        'idempotency_key' => 'brak-litrazu-1',
    ])->assertSessionHasErrors('volume_liters', null, 'supply-'.$supply->id);

    expect(TankSupplyEvent::count())->toBe(0);
});

it('deduplicates repeated Zastosowałem calls with the same idempotency key and supports restock', function () {
    $user = aquarist();
    $tank = tankFor($user);
    $tank->update(['volume_liters' => 100]);

    $supply = app(SaveSupply::class)->handle($user, $tank->id, new SupplyData(
        catalogId: 'CH0089',
        unit: 'ml',
        packageAmount: '100',
        doseAmount: '5',
        dosePerLiters: '100',
    ));

    $url = route('aquarium.supplies.event', [$tank->id, $supply->id]);
    $this->actingAs($user)
        ->post($url, ['kind' => 'use', 'idempotency_key' => 'double-click-key'])
        ->assertRedirect(route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'zapasy']));

    $this->post($url, ['kind' => 'use', 'idempotency_key' => 'double-click-key'])
        ->assertRedirect(route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'zapasy']));

    expect(TankSupplyEvent::count())->toBe(1)
        ->and((float) $supply->fresh()->remaining_amount)->toBe(95.0)
        ->and((float) TankSupplyEvent::sole()->delta_amount)->toBe(-5.0);

    // Otwórz nowe opakowanie (+100 ml) + idempotencja
    $this->post($url, ['kind' => 'restock', 'idempotency_key' => 'restock-key-1'])
        ->assertSessionHasNoErrors();
    $this->post($url, ['kind' => 'restock', 'idempotency_key' => 'restock-key-1'])
        ->assertSessionHasNoErrors();

    expect(TankSupplyEvent::count())->toBe(2)
        ->and((float) $supply->fresh()->remaining_amount)->toBe(195.0);
});

it('enforces the per-tank supply limit by plan and frees slots after archiving', function () {
    shopSetting(SettingKey::Supplies, ['tank' => 1, 'fishroom' => 3, 'low_stock_lead_days' => 14]);
    $tankUser = aquarist();
    $tank = tankFor($tankUser);

    $first = app(SaveSupply::class)->handle($tankUser, $tank->id, new SupplyData('CH0021', 'g', '100', '2'));

    expect(fn () => app(SaveSupply::class)->handle($tankUser, $tank->id, new SupplyData('CH0089', 'ml', '250', '5')))
        ->toThrow(ValidationException::class, 'Limit aktywnych pozycji zapasów');

    app(ArchiveSupply::class)->handle($tankUser, $first->id);
    expect($first->fresh()->archived_at)->not->toBeNull();

    $second = app(SaveSupply::class)->handle($tankUser, $tank->id, new SupplyData('CH0089', 'ml', '250', '5'));
    expect($second->id)->not->toBe($first->id);

    $fishroom = aquarist(true);
    $fishTank = tankFor($fishroom);
    app(SaveSupply::class)->handle($fishroom, $fishTank->id, new SupplyData('CH0021', 'g', '100', '2'));
    app(SaveSupply::class)->handle($fishroom, $fishTank->id, new SupplyData('CH0089', 'ml', '250', '5'));
    expect($fishTank->supplies()->active()->count())->toBe(2);
});

it('reorders a supply using the preferred shop first, otherwise nearby offers', function () {
    [$shopPreferred, $ownerA, $locA] = openShop('5260250274', 'Sklep Preferowany');
    [$shopLocal, $ownerB, $locB] = openShop('7740001454', 'Sklep Lokalny');
    $locA->update(['city' => 'Warszawa']);
    $locB->update(['city' => 'Poznań']);

    $offerPreferred = app(PublishOffer::class)->handle($ownerA, app(SaveOffer::class)->handle(
        $ownerA,
        $shopPreferred,
        new OfferData($locA->id, Area::Care, 'CH0089', '250 ml', 2900, now()->subHour(), now()->addDays(30), 4),
    ));
    $offerLocal = app(PublishOffer::class)->handle($ownerB, app(SaveOffer::class)->handle(
        $ownerB,
        $shopLocal,
        new OfferData($locB->id, Area::Care, 'CH0089', '250 ml', 2500, now()->subHour(), now()->addDays(30), 4),
    ));

    $user = aquarist();
    $user->update(['city' => 'Poznań']);
    $tank = tankFor($user);

    $withPreferred = app(SaveSupply::class)->handle($user, $tank->id, new SupplyData(
        catalogId: 'CH0089',
        unit: 'ml',
        packageAmount: '250',
        doseAmount: '5',
        preferredShopId: $shopPreferred->id,
    ));

    $this->actingAs($user)->get(route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'zapasy']))->assertOk()
        ->assertSee('Zamów ponownie (Sklep Preferowany)')
        ->assertSee('name="offer_id" value="'.$offerPreferred->id.'"', false);

    // Gdy preferowany sklep ma stan 0, wraca do oferty z okolicy
    $offerPreferred->update(['free_qty' => 0]);
    $this->get(route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'zapasy']))->assertOk()
        ->assertSee('Zamów ponownie (Sklep Lokalny)')
        ->assertSee('name="offer_id" value="'.$offerLocal->id.'"', false);

    // Zapis bez preferowanego sklepu wybiera ofertę z okolicy i dodaje do koszyka
    app(ArchiveSupply::class)->handle($user, $withPreferred->id);
    app(SaveSupply::class)->handle($user, $tank->id, new SupplyData('CH0089', 'ml', '250', '5'));

    $this->post(route('cart.add'), ['offer_id' => $offerLocal->id, 'qty' => 1])->assertRedirect(route('cart.show'));
    $this->assertDatabaseHas('shop_cart_items', ['user_id' => $user->id, 'offer_id' => $offerLocal->id, 'qty' => 1]);
});

it('reorders a picked-up order handling price changes and unavailable items', function () {
    [$shop, $owner, $loc] = openShop('5260250274', 'Sklep Zamówień');
    $offerAvailable = app(PublishOffer::class)->handle($owner, app(SaveOffer::class)->handle(
        $owner,
        $shop,
        new OfferData($loc->id, Area::Care, 'CH0089', '250 ml', 2500, now()->subHour(), now()->addDays(30), 5),
    ));
    $offerOut = app(PublishOffer::class)->handle($owner, app(SaveOffer::class)->handle(
        $owner,
        $shop,
        new OfferData($loc->id, Area::Care, 'CH0021', '100 g', 1500, now()->subHour(), now()->addDays(30), 2),
    ));

    $customer = aquarist();
    app(ChangeCart::class)->handle($customer, $offerAvailable->id, 2);
    app(ChangeCart::class)->handle($customer, $offerOut->id, 1);
    [$order] = app(SubmitCart::class)->handle($customer, 'klucz-reorder-1', '600 100 200');

    app(ApproveOrder::class)->handle($owner, $order);
    foreach ([OrderState::Paid, OrderState::Preparing, OrderState::Ready, OrderState::PickedUp] as $state) {
        app(AdvanceOrder::class)->handle($owner, $order, $state);
    }

    // Zmiana ceny pierwszej oferty oraz wyzerowanie dostępności drugiej
    app(SaveOffer::class)->handle(
        $owner,
        $shop,
        new OfferData($loc->id, Area::Care, 'CH0089', '250 ml', 3100, now()->subHour(), now()->addDays(30), 0),
        $offerAvailable->fresh(),
    );
    $offerOut->fresh()->update(['free_qty' => 0]);

    $this->actingAs($customer)->get(route('orders.show', $order->id))->assertOk()->assertSee('Zamów ponownie');

    $this->post(route('orders.reorder', $order->id))->assertRedirect(route('cart.show'));
    $this->assertDatabaseHas('shop_cart_items', [
        'user_id' => $customer->id,
        'offer_id' => $offerAvailable->id,
        'qty' => 2,
        'price_seen_grosze' => 3100,
    ]);
    $this->assertDatabaseMissing('shop_cart_items', [
        'user_id' => $customer->id,
        'offer_id' => $offerOut->id,
    ]);

    $this->get(route('cart.show'))->assertOk()
        ->assertSee('31,00 zł')
        ->assertSee('zmieniła się cena (poprzednio 25,00 zł, obecnie 31,00 zł)')
        ->assertSee('brak towaru lub oferta nie jest już dostępna');

    // Gdy wszystkie pozycje niedostępne — wraca do zamówienia z komunikatem
    $offerAvailable->fresh()->update(['free_qty' => 0]);
    DB::table('shop_cart_items')->where('user_id', $customer->id)->delete();

    $this->post(route('orders.reorder', $order->id))
        ->assertRedirect(route('orders.show', $order->id))
        ->assertSessionHasErrors('reorder');
    $this->assertDatabaseCount('shop_cart_items', 0);

    // Zamówienie w trakcie realizacji nie pokazuje ani nie wykonuje ponownego zamówienia
    $other = aquarist();
    $this->actingAs($other)->post(route('orders.reorder', $order->id))->assertNotFound();
    expect(Order::find($order->id))->not->toBeNull();
});

it('exports tank supplies with events and removes them on account deletion', function () {
    $user = aquarist();
    $tank = tankFor($user);
    $tank->update(['volume_liters' => 100]);
    $supply = app(SaveSupply::class)->handle($user, $tank->id, new SupplyData('CH0089', 'ml', '250', '5', dosePerLiters: '100'));
    app(RecordSupplyEvent::class)->handle($user, $supply->id, new SupplyEventData(SupplyEventKind::Use, 'export-key-1'));

    $otherUser = aquarist();
    $otherSupply = app(SaveSupply::class)->handle($otherUser, tankFor($otherUser)->id, new SupplyData('CH0021', 'g', '100', '2'));

    $this->actingAs($user)->post(route('account.privacy.export'))->assertOk()
        ->assertJsonCount(1, 'tanks.0.supplies')
        ->assertJsonPath('tanks.0.supplies.0.catalog_id', 'CH0089')
        ->assertJsonCount(1, 'tanks.0.supplies.0.events')
        ->assertJsonPath('tanks.0.supplies.0.events.0.idempotency_key', 'export-key-1');

    $this->delete(route('account.privacy.delete'), ['current_password' => 'password', 'confirmation' => 'USUŃ'])
        ->assertRedirect(route('login'));

    $this->assertDatabaseMissing('tank_supplies', ['id' => $supply->id]);
    $this->assertDatabaseCount('tank_supply_events', 0);
    $this->assertDatabaseHas('tank_supplies', ['id' => $otherSupply->id]);
});

it('blocks reading and modifying supplies of a foreign tank or mismatched tank route', function () {
    $owner = aquarist(true);
    $tank = tankFor($owner);
    $ownerSecondTank = tankFor($owner, 'Drugi');
    $other = aquarist(true);
    $supply = app(SaveSupply::class)->handle($owner, $tank->id, new SupplyData('CH0021', 'g', '100', '2'));

    expect(fn () => app(SaveSupply::class)->handle($other, $tank->id, new SupplyData('CH0021', 'g', '100', '2')))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => app(RecordSupplyEvent::class)->handle($other, $supply->id, new SupplyEventData(SupplyEventKind::Use, 'k1')))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => app(ArchiveSupply::class)->handle($other, $supply->id))
        ->toThrow(ModelNotFoundException::class);

    $this->actingAs($other)->get(route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'zapasy']))->assertNotFound();
    $this->post(route('aquarium.supplies.store'), [
        'tank' => $tank->id, 'catalog_id' => 'CH0021', 'unit' => 'g', 'package_amount' => '100', 'dose_amount' => '2',
    ])->assertNotFound();
    $this->post(route('aquarium.supplies.event', [$tank->id, $supply->id]), ['kind' => 'use', 'idempotency_key' => 'k2'])->assertNotFound();
    $this->post(route('aquarium.supplies.archive', [$tank->id, $supply->id]))->assertNotFound();

    // Pozycja ze zbiornika nie może zostać zmieniona w ścieżce innego własnego zbiornika
    $this->actingAs($owner)->post(route('aquarium.supplies.event', [$ownerSecondTank->id, $supply->id]), [
        'kind' => 'use', 'idempotency_key' => 'k3',
    ])->assertNotFound();
    $this->actingAs($owner)->post(route('aquarium.supplies.archive', [$ownerSecondTank->id, $supply->id]))->assertNotFound();

    expect(TankSupply::count())->toBe(1)
        ->and(TankSupplyEvent::count())->toBe(0)
        ->and($supply->fresh()->archived_at)->toBeNull();
});
