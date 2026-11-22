<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Actions\AdvanceOrder;
use App\Domain\Shops\Actions\ApproveOrder;
use App\Domain\Shops\Actions\BuildDailyReports;
use App\Domain\Shops\Actions\ChangeCart;
use App\Domain\Shops\Actions\CloseOrder;
use App\Domain\Shops\Actions\PseudonymizeOrders;
use App\Domain\Shops\Actions\RejectOrder;
use App\Domain\Shops\Actions\SaveOffer;
use App\Domain\Shops\Actions\SetAvailableQuantity;
use App\Domain\Shops\Actions\SubmitCart;
use App\Domain\Shops\Data\OfferData;
use App\Domain\Shops\EanNumber;
use App\Domain\Shops\Models\DailyReport;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderEvent;
use App\Domain\Shops\Models\OrderState;
use App\Domain\Shops\Models\Reservation;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\StockMovement;
use App\Domain\Shops\Notifications\DailyReportNotice;
use App\Domain\Shops\Notifications\OrderNotice;
use App\Filament\Resources\Orders\Pages\ListOrders as AdminListOrders;
use App\Filament\Shop\Resources\Orders\Pages\ListOrders;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    app(ImportPackage::class)->handle(kbPackage());
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 20]);
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Sklep z opublikowanymi ofertami na kolejne rekordy fauny (od `$skip`).
 *
 * @return array{0: Shop, 1: User, 2: list<Offer>}
 */
function ordShop(string $nip, int $offers = 2, int $skip = 0, int $qty = 5): array
{
    [$shop, $owner, $location] = openShop($nip, "Sklep $nip");
    $ids = array_slice(faunaIds($skip + $offers), $skip);

    return [$shop, $owner, array_map(fn ($id) => publishedOffer($owner, $shop, $location, $id, $qty), $ids)];
}

/**
 * @param  array<int, int>  $lines  id oferty => ilość
 * @return list<Order>
 */
function ordPlace(User $customer, array $lines, string $key = 'klucz-0001'): array
{
    foreach ($lines as $offerId => $qty) {
        app(ChangeCart::class)->handle($customer, $offerId, $qty);
    }

    return app(SubmitCart::class)->handle($customer, $key, '600 100 200');
}

function ordFree(Offer $offer): array
{
    $offer->refresh();

    return [$offer->free_qty, $offer->reserved_qty];
}

it('submits a two-shop basket atomically as one order per shop without reserving', function () {
    Notification::fake();
    [$a, $ownerA, [$a1, $a2]] = ordShop('5260250274');
    [$b, $ownerB, [$b1]] = ordShop('7740001454');
    $customer = aquarist();

    $orders = ordPlace($customer, [$a1->id => 2, $a2->id => 1, $b1->id => 3]);

    expect($orders)->toHaveCount(2)
        ->and(Order::where('shop_id', $a->id)->first())->state->toBe(OrderState::New)->ean13->toBeNull()->total_grosze->toBe(3 * 1500)
        ->and(Order::where('shop_id', $b->id)->first()->items)->toHaveCount(1)
        ->and(Order::where('shop_id', $a->id)->first()->items->pluck('qty')->all())->toBe([2, 1])
        ->and(Order::where('shop_id', $a->id)->first()->items[0])->name->not->toBe('')->price_grosze->toBe(1500)
        ->and(Order::first()->contact)->nick->toBe($customer->name)->email->toBe($customer->email)->phone->toBe('600 100 200')
        ->and(DB::table('shop_cart_items')->count())->toBe(0)
        ->and(Reservation::count())->toBe(0)
        ->and(ordFree($a1))->toBe([5, 0]);
    Notification::assertSentTo($ownerA, OrderNotice::class, fn ($n) => $n->kind === 'submitted');
    Notification::assertSentTo($ownerB, OrderNotice::class, fn ($n) => $n->kind === 'submitted');
});

it('replays the first result for a repeated key and counts the limit once', function () {
    [, , [$a1]] = ordShop('5260250274');
    $customer = aquarist();

    $first = ordPlace($customer, [$a1->id => 1], 'klucz-0002');
    $second = app(SubmitCart::class)->handle($customer, 'klucz-0002', '600 100 200');

    expect(Order::count())->toBe(1)->and($second[0]->id)->toBe($first[0]->id)
        ->and((int) DB::table('shop_customer_request_days')->value('used_orders'))->toBe(1);
});

it('enforces the daily order limit for the whole basket and keeps it open', function () {
    shopSetting(SettingKey::ShopOrders, ['daily_orders' => 2, 'qty_min' => 1, 'qty_max' => 50, 'pseudonymize_days' => 90, 'report_hour' => 10]);
    [, , [$a1]] = ordShop('5260250274', 1);
    [, , [$b1]] = ordShop('7740001454', 1);
    [, , [$c1]] = ordShop('5252248481', 1);
    $customer = aquarist();

    expect(fn () => ordPlace($customer, [$a1->id => 1, $b1->id => 1, $c1->id => 1], 'klucz-0003'))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0)->and(DB::table('shop_cart_items')->count())->toBe(3);

    app(ChangeCart::class)->handle($customer, $c1->id, 0);
    expect(app(SubmitCart::class)->handle($customer, 'klucz-0004', '600 100 200'))->toHaveCount(2);

    app(ChangeCart::class)->handle($customer, $c1->id, 1);
    expect(fn () => app(SubmitCart::class)->handle($customer, 'klucz-0005', '600 100 200'))->toThrow(ValidationException::class);
});

it('blocks buying from the own shop and respects quantity bounds', function () {
    [$shop, $owner, [$a1]] = ordShop('5260250274', 1);
    $staff = staffOf($shop, $owner);
    $customer = aquarist();

    expect(fn () => app(ChangeCart::class)->handle($owner, $a1->id, 1))->toThrow(ValidationException::class)
        ->and(fn () => app(ChangeCart::class)->handle($staff, $a1->id, 1))->toThrow(ValidationException::class)
        ->and(fn () => app(ChangeCart::class)->handle($customer, $a1->id, 6))->toThrow(ValidationException::class) // dostępna ilość 5
        ->and(fn () => app(ChangeCart::class)->handle($customer, 999999, 1))->toThrow(ValidationException::class);

    shopSetting(SettingKey::ShopOrders, ['daily_orders' => 5, 'qty_min' => 2, 'qty_max' => 3, 'pseudonymize_days' => 90, 'report_hour' => 10]);
    expect(fn () => app(ChangeCart::class)->handle($customer, $a1->id, 1))->toThrow(ValidationException::class)
        ->and(fn () => app(ChangeCart::class)->handle($customer, $a1->id, 4))->toThrow(ValidationException::class);
    app(ChangeCart::class)->handle($customer, $a1->id, 2);
    app(ChangeCart::class)->handle($customer, $a1->id, 1, add: true); // zwiększa do 3
    expect((int) DB::table('shop_cart_items')->value('qty'))->toBe(3);
    expect(fn () => app(ChangeCart::class)->handle($customer, $a1->id, 1, add: true))->toThrow(ValidationException::class);
});

it('approves a multi-item order, reserves quantities and assigns a unique valid EAN-13', function () {
    Notification::fake();
    [$a, $ownerA, [$a1, $a2]] = ordShop('5260250274');
    [$b, $ownerB, [$b1]] = ordShop('7740001454');
    $customer = aquarist();
    [$orderA, $orderB] = ordPlace($customer, [$a1->id => 2, $a2->id => 3, $b1->id => 1]);

    $orderA = app(ApproveOrder::class)->handle($ownerA, $orderA);
    $orderB = app(ApproveOrder::class)->handle($ownerB, $orderB);

    expect(EanNumber::checkDigit('590123412345'))->toBe(7)
        ->and($orderA->ean13)->toMatch('/^2\d{12}$/')->and($orderB->ean13)->toMatch('/^2\d{12}$/')->not->toBe($orderA->ean13)
        ->and(EanNumber::checkDigit(substr($orderA->ean13, 0, 12)))->toBe((int) substr($orderA->ean13, 12))
        ->and(substr($orderA->ean13, 1, 4))->not->toBe(substr($orderB->ean13, 1, 4)) // numery placówek
        ->and($orderA->state)->toBe(OrderState::Confirmed)
        ->and(ordFree($a1))->toBe([3, 2])->and(ordFree($a2))->toBe([2, 3]) // oba towary jednym numerem
        ->and(Reservation::where('state', 'held')->count())->toBe(3)
        ->and(StockMovement::where('kind', 'reserve')->count())->toBe(3)
        ->and(Order::whereNotNull('ean13')->count())->toBe(2)
        ->and(OrderEvent::where('order_id', $orderA->id)->pluck('to_state')->map->value->all())->toBe(['new', 'confirmed']);
    Notification::assertSentTo($customer, OrderNotice::class, fn ($n) => $n->kind === 'confirmed' && $n->order->id === $orderA->id);
});

it('changes nothing when any item lacks quantity or terms changed', function () {
    [$a, $owner, [$a1, $a2]] = ordShop('5260250274');
    $customer = aquarist();
    [$order] = ordPlace($customer, [$a1->id => 2, $a2->id => 3]);
    $loc = $a->locations()->first();

    // drugi towar: dostępna ilość spada poniżej zamówionej
    app(SetAvailableQuantity::class)->handle($owner, $a2->refresh(), $a2->version, 1, true);
    expect(fn () => app(ApproveOrder::class)->handle($owner, $order))->toThrow(ValidationException::class);

    expect($order->refresh())->state->toBe(OrderState::New)->ean13->toBeNull()
        ->and(ordFree($a1))->toBe([5, 0])->and(ordFree($a2))->toBe([1, 0])
        ->and(Reservation::count())->toBe(0)->and(StockMovement::where('kind', 'reserve')->count())->toBe(0)
        ->and((int) DB::table('shop_ean_sequence')->value('last'))->toBe(0)
        ->and($loc->refresh()->ean_no)->toBeNull();

    // zmiana ceny po wysyłce także blokuje zatwierdzenie
    app(SetAvailableQuantity::class)->handle($owner, $a2->refresh(), $a2->version, 5, true);
    app(SaveOffer::class)->handle($owner, $a, new OfferData($loc->id, $a1->area, $a1->legacy_id, '', 1999, now()->subHour(), now()->addDays(30), 0), $a1);
    expect(fn () => app(ApproveOrder::class)->handle($owner, $order))->toThrow(ValidationException::class);
    expect($order->refresh()->ean13)->toBeNull()->and(Reservation::count())->toBe(0);
});

it('rolls the whole decision back when the EAN cannot be assigned', function () {
    Notification::fake();
    [, $owner, [$a1]] = ordShop('5260250274', 1);
    $customer = aquarist();
    [$order] = ordPlace($customer, [$a1->id => 2]);
    DB::table('shop_ean_sequence')->update(['last' => 9_999_999]);

    expect(fn () => app(ApproveOrder::class)->handle($owner, $order))->toThrow(ValidationException::class);

    expect($order->refresh())->state->toBe(OrderState::New)->ean13->toBeNull()
        ->and(ordFree($a1))->toBe([5, 0])->and(Reservation::count())->toBe(0);
    Notification::assertNotSentTo($customer, OrderNotice::class);
});

it('rejects a whole order with a reason code and a required text for other', function () {
    Notification::fake();
    [, $owner, [$a1, $a2]] = ordShop('5260250274');
    $customer = aquarist();
    [$order] = ordPlace($customer, [$a1->id => 2, $a2->id => 1]);

    expect(fn () => app(RejectOrder::class)->handle($owner, $order, 'inne'))->toThrow(ValidationException::class)
        ->and(fn () => app(RejectOrder::class)->handle($owner, $order, 'zly_kod'))->toThrow(ValidationException::class);
    expect($order->refresh()->state)->toBe(OrderState::New);

    $rejected = app(RejectOrder::class)->handle($owner, $order, 'inne', 'Towar zniszczony');
    expect($rejected)->state->toBe(OrderState::Rejected)->rejection_code->toBe('inne')->note->toBe('Towar zniszczony')
        ->and($rejected->ean13)->toMatch('/^2\d{12}$/')
        ->and(ordFree($a1))->toBe([5, 0])->and(Reservation::count())->toBe(0);
    Notification::assertSentTo($customer, OrderNotice::class, fn ($n) => $n->kind === 'rejected');

    [$second] = ordPlace($customer, [$a1->id => 1], 'klucz-0006');
    expect(app(RejectOrder::class)->handle($owner, $second, 'brak_aktualnej_oferty'))->rejection_code->toBe('brak_aktualnej_oferty');
});

it('treats a repeated decision as a no-op and refuses a conflicting one', function () {
    [, $owner, [$a1]] = ordShop('5260250274', 1);
    $customer = aquarist();
    [$order] = ordPlace($customer, [$a1->id => 2]);

    $first = app(ApproveOrder::class)->handle($owner, $order);
    $again = app(ApproveOrder::class)->handle($owner, $order);

    expect($again->ean13)->toBe($first->ean13)->and(ordFree($a1))->toBe([3, 2])
        ->and(Reservation::count())->toBe(1)->and(OrderEvent::where('order_id', $order->id)->count())->toBe(2)
        ->and((int) DB::table('shop_ean_sequence')->value('last'))->toBe(1);
    expect(fn () => app(RejectOrder::class)->handle($owner, $order, 'brak_aktualnej_oferty'))->toThrow(ValidationException::class);

    [$second] = ordPlace($customer, [$a1->id => 1], 'klucz-0007');
    app(RejectOrder::class)->handle($owner, $second, 'brak_aktualnej_oferty');
    app(RejectOrder::class)->handle($owner, $second, 'brak_aktualnej_oferty');
    expect(fn () => app(ApproveOrder::class)->handle($owner, $second))->toThrow(ValidationException::class)
        ->and((int) DB::table('shop_ean_sequence')->value('last'))->toBe(2);
});

it('walks the full status path and records who and when', function () {
    Notification::fake();
    [, $owner, [$a1]] = ordShop('5260250274', 1);
    $customer = aquarist();
    [$order] = ordPlace($customer, [$a1->id => 2]);
    app(ApproveOrder::class)->handle($owner, $order);

    expect(fn () => app(AdvanceOrder::class)->handle($owner, $order, OrderState::Preparing))->toThrow(ValidationException::class)
        ->and(fn () => app(AdvanceOrder::class)->handle($owner, $order, OrderState::PickedUp))->toThrow(ValidationException::class);

    foreach ([OrderState::Paid, OrderState::Preparing, OrderState::Ready] as $to) {
        app(AdvanceOrder::class)->handle($owner, $order, $to);
    }
    expect(ordFree($a1))->toBe([3, 2]); // do wydania rezerwacja trwa
    Notification::assertSentTo($customer, OrderNotice::class, fn ($n) => $n->kind === 'ready');

    app(AdvanceOrder::class)->handle($owner, $order, OrderState::PickedUp);
    app(AdvanceOrder::class)->handle($owner, $order, OrderState::PickedUp); // powtórka bez skutku

    expect($order->refresh())->state->toBe(OrderState::PickedUp)->finished_at->not->toBeNull()->pseudonymize_at->not->toBeNull()
        ->and(ordFree($a1))->toBe([3, 0])
        ->and(Reservation::first()->state)->toBe('consumed')
        ->and(StockMovement::where('kind', 'issue')->count())->toBe(1)
        ->and((int) DB::table('shop_order_payments')->where('order_id', $order->id)->value('amount_grosze'))->toBe(3000)
        ->and(OrderEvent::where('order_id', $order->id)->pluck('to_state')->map->value->all())->toBe(['new', 'confirmed', 'paid', 'preparing', 'ready', 'picked_up'])
        ->and(OrderEvent::where('order_id', $order->id)->whereNotNull('actor_id')->count())->toBe(6);
    expect(fn () => app(CloseOrder::class)->handle($owner, $order, 'za późno'))->toThrow(ValidationException::class);
});

it('closes without fulfilment releasing reservations exactly once', function () {
    [, $owner, [$a1, $a2]] = ordShop('5260250274');
    $customer = aquarist();
    [$order] = ordPlace($customer, [$a1->id => 2, $a2->id => 3]);

    // klient może wycofać tylko nowe zamówienie; bez decyzji zamknięcie nie nadaje numeru
    $draft = app(CloseOrder::class)->handle($customer, $order, 'Rezygnuję');
    expect($draft)->state->toBe(OrderState::Closed)->ean13->toBeNull()->and(ordFree($a1))->toBe([5, 0]);

    [$second] = ordPlace($customer, [$a1->id => 2, $a2->id => 3], 'klucz-0008');
    app(ApproveOrder::class)->handle($owner, $second);
    expect(fn () => app(CloseOrder::class)->handle($customer, $second, 'Rezygnuję'))->toThrow(AuthorizationException::class);
    app(AdvanceOrder::class)->handle($owner, $second, OrderState::Paid);
    expect(fn () => app(CloseOrder::class)->handle($owner, $second, 'Brak towaru'))->toThrow(ValidationException::class); // brak rozliczenia po wpływie
    expect(ordFree($a1))->toBe([3, 2]);

    app(CloseOrder::class)->handle($owner, $second, 'Brak towaru', 'Zwrot przelewu 30 zł');
    app(CloseOrder::class)->handle($owner, $second, 'Brak towaru', 'Zwrot przelewu 30 zł'); // powtórka

    expect(ordFree($a1))->toBe([5, 0])->and(ordFree($a2))->toBe([5, 0])
        ->and(StockMovement::where('kind', 'release')->count())->toBe(2)
        ->and(Reservation::where('state', 'released')->count())->toBe(2)
        ->and($second->refresh())->state->toBe(OrderState::Closed)->ean13->not->toBeNull()
        ->and(OrderEvent::where('order_id', $second->id)->latest('id')->first()->reason)->toContain('Zwrot przelewu');
});

it('keeps shops isolated from each other orders', function () {
    [$a, $ownerA, [$a1]] = ordShop('5260250274', 1);
    [$b, $ownerB, [$b1]] = ordShop('7740001454', 1);
    $customer = aquarist();
    [$orderA, $orderB] = ordPlace($customer, [$a1->id => 1, $b1->id => 1]);

    foreach ([fn () => app(ApproveOrder::class)->handle($ownerB, $orderA),
        fn () => app(RejectOrder::class)->handle($ownerB, $orderA, 'brak_aktualnej_oferty'),
        fn () => app(AdvanceOrder::class)->handle($ownerB, $orderA, OrderState::Paid),
        fn () => app(CloseOrder::class)->handle($ownerB, $orderA, 'x'),
        fn () => app(ApproveOrder::class)->handle(aquarist(), $orderA)] as $call) {
        expect($call)->toThrow(AuthorizationException::class);
    }
    expect($orderA->refresh()->state)->toBe(OrderState::New);

    inShopPanel($a, $ownerA);
    Livewire::test(ListOrders::class)->assertCanSeeTableRecords([$orderA])->assertCanNotSeeTableRecords([$orderB])
        ->assertTableActionVisible('approve', $orderA)->assertTableActionHidden('confirmPayment', $orderA)
        ->callTableAction('approve', $orderA)->assertHasNoTableActionErrors();
    expect($orderA->refresh()->state)->toBe(OrderState::Confirmed);
    Livewire::test(ListOrders::class)->filterTable('state', 'new')->assertCanNotSeeTableRecords([$orderA])
        ->filterTable('state', 'confirmed')->assertCanSeeTableRecords([$orderA]);

    $this->get("/panel-sklepu/{$a->id}/zamowienia/{$orderA->id}")->assertOk()->assertSee($customer->email)->assertSee('600 100 200');
    Filament::setTenant($b);
    $this->get("/panel-sklepu/{$b->id}/zamowienia/{$orderA->id}")->assertNotFound();
});

it('defers notifications until the surrounding transaction commits', function () {
    Notification::fake();
    [, $owner, [$a1]] = ordShop('5260250274', 1);
    $customer = aquarist();
    [$order] = ordPlace($customer, [$a1->id => 1]);

    DB::transaction(function () use ($owner, $order, $customer) {
        app(ApproveOrder::class)->handle($owner, $order);
        Notification::assertNotSentTo($customer, OrderNotice::class);
    });
    Notification::assertSentToTimes($customer, OrderNotice::class, 1);
});

it('builds one daily report per shop and day and mails the team once', function () {
    Notification::fake();
    $day = now()->timezone('Europe/Warsaw')->addDay()->setTime(12, 0); // dzień raportowany
    Carbon::setTestNow($day);
    [$a, $ownerA, [$a1]] = ordShop('5260250274', 1);
    [$b, $ownerB] = ordShop('7740001454', 1);
    $customer = aquarist();
    [$order] = ordPlace($customer, [$a1->id => 2]);
    app(ApproveOrder::class)->handle($ownerA, $order);

    Carbon::setTestNow($day->copy()->addDay()->setTime(9, 30));
    expect(app(BuildDailyReports::class)->handle())->toBe(0); // przed 10:00

    Carbon::setTestNow($day->copy()->addDay()->setTime(10, 5));
    expect(app(BuildDailyReports::class)->handle())->toBe(2)
        ->and(app(BuildDailyReports::class)->handle())->toBe(0);

    $report = DailyReport::where('shop_id', $a->id)->first();
    expect($report->business_date->toDateString())->toBe($day->toDateString())
        ->and($report->period_from->utc()->toDateTimeString())->toBe($day->copy()->startOfDay()->utc()->toDateTimeString())
        ->and($report->data['orders']['new'])->toBe(1)->and($report->data['orders']['confirmed'])->toBe(1)
        ->and($report->data['pieces']['reserved'])->toBe(2)
        ->and(DailyReport::where('shop_id', $b->id)->first()->data['orders']['new'])->toBe(0); // zera dla sklepu bez ruchu
    Notification::assertSentToTimes($ownerA, DailyReportNotice::class, 1);
    Notification::assertSentToTimes($ownerB, DailyReportNotice::class, 1);

    // kolejny dzień: nadrabia tylko brakujący raport
    Carbon::setTestNow($day->copy()->addDays(2)->setTime(11, 0));
    expect(app(BuildDailyReports::class)->handle())->toBe(2)->and(DailyReport::count())->toBe(4);
});

it('fills a missed report date and queues mail after commit without duplicating the report', function () {
    Notification::fake();
    $now = now('Europe/Warsaw');
    $day = $now->copy()->subDays(3);
    Carbon::setTestNow($day);
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 20]);
    [$shop, $owner] = ordShop('5260250274', 1);
    Carbon::setTestNow($now);
    [, $newOwner] = ordShop('7740001454', 1);
    $date = $day->toDateString();

    DB::transaction(function () use ($owner, $date) {
        $this->artisan('shops:daily-reports', ['--date' => $date])->assertSuccessful();
        Notification::assertNotSentTo($owner, DailyReportNotice::class);
    });

    $this->artisan('shops:daily-reports', ['--date' => $date])->assertSuccessful();
    expect(DailyReport::sole()->shop_id)->toBe($shop->id)
        ->and(DailyReport::sole()->business_date->toDateString())->toBe($date);
    Notification::assertSentToTimes($owner, DailyReportNotice::class, 1);
    Notification::assertNotSentTo($newOwner, DailyReportNotice::class);
});

it('pseudonymises customer contact after the retention period', function () {
    Carbon::setTestNow(now()->addHour());
    [, $owner, [$a1]] = ordShop('5260250274', 1);
    $customer = aquarist();
    [$order] = ordPlace($customer, [$a1->id => 1]);
    app(RejectOrder::class)->handle($owner, $order, 'inne', 'Zadzwoń do Jana 600 600 600');
    $ean = $order->refresh()->ean13;

    Carbon::setTestNow(now()->addDays(89));
    expect(app(PseudonymizeOrders::class)->handle())->toBe(0)
        ->and($order->refresh()->contactVisible())->toBeTrue()->and($order->contact)->not->toBeNull();

    Carbon::setTestNow(now()->addDays(2));
    expect($order->refresh()->contactVisible())->toBeFalse(); // ukryty zanim ruszy zadanie
    expect(app(PseudonymizeOrders::class)->handle())->toBe(1)->and(app(PseudonymizeOrders::class)->handle())->toBe(0);

    $order->refresh();
    expect($order->contact()->exists())->toBeFalse()->and($order->user_id)->toBeNull()->and($order->note)->toBeNull()
        ->and($order->ean13)->toBe($ean)->and($order->state)->toBe(OrderState::Rejected)->and($order->items)->toHaveCount(1)
        ->and(OrderEvent::where('order_id', $order->id)->whereNotNull('reason')->where('reason', '!=', 'Pseudonimizacja danych klienta')->count())->toBe(0)
        ->and(DB::table('shop_checkouts')->whereNotNull('user_id')->count())->toBe(0);
});

it('serves the customer pages and hides foreign orders', function () {
    [, $owner, [$a1]] = ordShop('5260250274', 1);
    $customer = aquarist();
    $other = aquarist();

    $this->get('/koszyk')->assertRedirect('/logowanie');
    $this->actingAs($customer)->post('/koszyk', ['offer_id' => $a1->id, 'qty' => 2])->assertRedirect('/koszyk');
    $this->get('/koszyk')->assertOk()->assertSee('Sklep 5260250274')->assertSee('dostępna ilość');
    $this->post('/koszyk/wyslij', ['key' => 'klucz-0009', 'phone' => 'abc'])->assertSessionHasErrors('phone');
    $this->post('/koszyk/wyslij', ['key' => 'klucz-0009', 'phone' => '600 100 200'])->assertRedirect('/zamowienia');

    $order = Order::firstOrFail();
    $this->get('/zamowienia')->assertOk()->assertSee('Czeka na decyzję sklepu');
    $this->get("/zamowienia/{$order->id}")->assertOk()->assertSee('Wycofaj zamówienie');

    app(ApproveOrder::class)->handle($owner, $order);
    $this->get("/zamowienia/{$order->id}")->assertOk()->assertSee($order->refresh()->ean13)->assertSee('Kopiuj tytuł przelewu');

    $this->actingAs($other)->get("/zamowienia/{$order->id}")->assertNotFound();
    $this->post("/zamowienia/{$order->id}/zamknij")->assertNotFound();
});

it('lets an administrator list all orders read-only', function () {
    [, , [$a1]] = ordShop('5260250274', 1);
    [$order] = ordPlace(aquarist(), [$a1->id => 1]);

    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->admin()->create());
    Livewire::test(AdminListOrders::class)->assertCanSeeTableRecords([$order]);

    $this->actingAs(aquarist())->get('/admin/zamowienia')->assertForbidden();
});
