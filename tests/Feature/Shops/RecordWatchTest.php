<?php

use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Actions\NotifyRecordWatchers;
use App\Domain\Shops\Actions\WatchRecord;
use App\Domain\Shops\Contracts\WatchCounts;
use App\Domain\Shops\Models\RecordWatch;
use App\Domain\Shops\Models\ShopState;
use App\Domain\Shops\Notifications\RecordAvailableNotice;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    app(ImportPackage::class)->handle(kbPackage());
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 20]);
});

it('offers account registration to guests on the product and advisor card', function () {
    $path = '/doradca/test-obserwacji';
    Route::middleware('web')->get($path, fn () => Blade::render(
        "@include('advisor._card-actions', ['area' => \$area, 'id' => 'FN0001', 'name' => 'Ryba', 'variant' => 'compact'])",
        ['area' => GuideArea::Fauna],
    ));
    $store = route('watches.store', ['fauna', 'FN0001']);

    $this->get(route('catalog.show', ['fauna', 'FN0001']))->assertOk()
        ->assertSee('Załóż konto, by otrzymać powiadomienie')->assertDontSee($store);
    $this->get($path)->assertOk()->assertSee('Załóż konto, by otrzymać powiadomienie');
    $this->get(route('watches.index'))->assertRedirect(route('login'));
    $this->post($store)->assertRedirect(route('login'));
    $this->assertDatabaseCount('record_watches', 0);

    $this->actingAs(aquarist())->get($path)->assertOk()->assertSee($store)
        ->assertSee('Daj znać, gdy będzie dostępne');
    $this->post($store, ['return_to' => $path])->assertRedirect($path);
    $this->get($path)->assertOk()->assertSee('Obserwujesz ten rekord.');
});

it('creates, lists and removes an owned watch and reuses the row on reactivation', function () {
    $user = aquarist();
    $store = route('watches.store', ['fauna', 'FN0001']);
    $product = route('catalog.show', ['fauna', 'FN0001']);
    $list = route('watches.index');

    $this->actingAs($user)->get(route('account'))->assertOk()->assertSee($list);
    $this->get($product)->assertOk()->assertSee($store);
    $this->post($store, ['return_to' => $product])->assertRedirect($product);
    $this->get($product)->assertOk()->assertSee('Obserwujesz ten rekord.')->assertDontSee($store);
    $watch = RecordWatch::sole();
    $remove = route('watches.destroy', $watch->id);

    $this->post($store)->assertRedirect($product);
    expect($watch->fresh()->activation)->toBe($watch->activation);
    $this->get($list)->assertOk()->assertSee($product)->assertSee($remove);

    $this->actingAs(aquarist())->get($list)->assertOk()->assertDontSee($product);
    $this->delete($remove)->assertNotFound();
    expect($watch->fresh()->removed_at)->toBeNull();

    $this->actingAs($user)->delete($remove)->assertRedirect($list);
    $this->get($list)->assertOk()->assertDontSee($product);
    expect($watch->fresh()->removed_at)->not->toBeNull();

    $this->post($store)->assertRedirect($product);
    $restarted = RecordWatch::sole();
    expect($restarted->id)->toBe($watch->id)
        ->and($restarted->activation)->not->toBe($watch->activation)
        ->and($restarted->removed_at)->toBeNull()
        ->and($restarted->notified_at)->toBeNull();
});

it('enforces the configured active watch limit for each tier and validates catalog records', function () {
    shopSetting(SettingKey::ShopWatch, ['tank' => 1, 'fishroom' => 2, 'retention_days' => 14]);
    $first = route('watches.store', ['fauna', 'FN0001']);
    $second = route('watches.store', ['fauna', 'FN0002']);

    $this->actingAs(aquarist())->post($first)->assertSessionHasNoErrors();
    $response = $this->post($second)->assertSessionHasErrors(['watch'], errorBag: 'recordWatch');
    $cookie = config('session.cookie');
    $this->withCookie($cookie, $response->getCookie($cookie)->getValue());
    $this->get(route('catalog.show', ['fauna', 'FN0002']))->assertOk()
        ->assertSee('Limit aktywnych obserwacji na Twoim koncie wynosi 1.');
    $this->assertDatabaseCount('record_watches', 1);

    $this->actingAs(aquarist(fishroom: true))->post($first)->assertSessionHasNoErrors();
    $this->post($second)->assertSessionHasNoErrors();
    $this->post(route('watches.store', ['fauna', 'FN9999']))
        ->assertSessionHasErrors(['watch' => 'Brak rekordu w katalogu.'], errorBag: 'recordWatch');
    $this->assertDatabaseCount('record_watches', 3);
});

it('queues one factual message per matched record after commit and never repeats a successful handoff', function () {
    Queue::fake([SendQueuedNotifications::class]);
    $user = aquarist();
    $watch = app(WatchRecord::class);
    $watch->handle($user, Area::Fauna, 'FN0001');
    $watch->handle($user, Area::Fauna, 'FN0002');
    [$shop, $owner, $location] = openShop(name: 'Sklep w Poznaniu');
    $sweep = app(NotifyRecordWatchers::class);

    DB::transaction(function () use ($sweep, $shop, $owner, $location) {
        publishedOffer($owner, $shop, $location, 'FN0001');
        publishedOffer($owner, $shop, $location, 'FN0002', qty: 2);
        $sweep->handle();
        Queue::assertNothingPushed();
        expect(RecordWatch::whereNotNull('notified_at')->count())->toBe(0);
    });

    Queue::assertPushed(SendQueuedNotifications::class, 2);
    expect(RecordWatch::whereNotNull('notified_at')->count())->toBe(2);
    $sweep->handle();
    Queue::assertPushed(SendQueuedNotifications::class, 2);

    $jobs = Queue::pushed(SendQueuedNotifications::class)->keyBy(fn ($job) => $job->notification->offer->legacyId);
    $notice = $jobs['FN0001']->notification;
    expect($notice)->toBeInstanceOf(RecordAvailableNotice::class);
    expect($jobs['FN0001']->channels)->toBe(['mail']);
    $mail = $notice->toMail($user);
    expect($mail->actionUrl)->toBe(route('catalog.show', ['fauna', 'FN0001']))
        ->and($mail->introLines)->toContain('Sklep: Sklep w Poznaniu', 'Miasto: Poznań', 'Cena: 15,00 zł', 'Dostępna ilość: 5');
    config(['mail.default' => 'array']);
    $jobs['FN0001']->handle(app(ChannelManager::class));
    $email = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage();
    expect($email->getHtmlBody())->toContain('href="'.$notice->unsubscribeUrl().'"')
        ->and($email->getTextBody())->toContain($notice->unsubscribeUrl());
});

it('keeps the watch pending if the queue rejects the handoff', function () {
    $user = aquarist();
    app(WatchRecord::class)->handle($user, Area::Fauna, 'FN0001');
    [$shop, $owner, $location] = openShop();
    publishedOffer($owner, $shop, $location, 'FN0001');
    Queue::shouldReceive('connection')->once()->andThrow(new RuntimeException('Kolejka jest niedostępna.'));

    expect(fn () => app(NotifyRecordWatchers::class)->handle())->toThrow(RuntimeException::class, 'Kolejka jest niedostępna.');
    expect(RecordWatch::sole()->notified_at)->toBeNull();
});

it('uses the current normalized city for counts and notifications and accepts nationwide offers without a city', function () {
    Notification::fake();
    $local = aquarist();
    $local->update(['city' => 'Warszawa']);
    $national = aquarist();
    $watch = app(WatchRecord::class);
    $watch->handle($local, Area::Fauna, 'FN0001');
    $watch->handle($national, Area::Fauna, 'FN0001');
    [$shop, $owner, $location] = openShop();
    publishedOffer($owner, $shop, $location, 'FN0001');
    $sweep = app(NotifyRecordWatchers::class);
    $counts = app(WatchCounts::class);

    $this->actingAs($national)->get(route('catalog.show', ['fauna', 'FN0001']))
        ->assertOk()->assertDontSee('Daj znać, gdy będzie dostępne');
    $sweep->handle();
    Notification::assertSentToTimes($national, RecordAvailableNotice::class, 1);
    Notification::assertNotSentTo($local, RecordAvailableNotice::class);
    expect($counts->byRecord('Warszawa', Area::Fauna, ['FN0001', 'FN0002'])->counts)->toBe(['FN0001' => 1, 'FN0002' => 0]);

    $this->actingAs($local)->post(route('account.city'), ['city' => '  POZNAN  ']);
    expect($counts->byRecord('Poznań', Area::Fauna, ['FN0001'])->counts)->toBe(['FN0001' => 1]);
    $sweep->handle();
    Notification::assertSentToTimes($local, RecordAvailableNotice::class, 1);
    Notification::assertSentToTimes($national, RecordAvailableNotice::class, 1);
    expect($counts->byRecord(null, Area::Fauna, ['FN0001'])->counts)->toBe(['FN0001' => 0]);
});

it('ignores sold-out or expired offers and offers from suspended shops', function () {
    Notification::fake();
    app(WatchRecord::class)->handle(aquarist(), Area::Fauna, 'FN0001');
    [$shop, $owner, $location] = openShop();
    $offer = publishedOffer($owner, $shop, $location, 'FN0001', qty: 0);
    $sweep = app(NotifyRecordWatchers::class);

    $sweep->handle();
    $offer->update(['free_qty' => 4, 'valid_to' => now()]);
    $sweep->handle();
    $offer->update(['valid_to' => now()->addDay()]);
    $shop->update(['state' => ShopState::Suspended]);
    $sweep->handle();

    Notification::assertNothingSent();
    expect(RecordWatch::sole()->notified_at)->toBeNull();
});

it('respects the availability preference before queuing and when processing queued mail', function () {
    Notification::fake();
    $user = aquarist();
    app(WatchRecord::class)->handle($user, Area::Fauna, 'FN0001');
    [$shop, $owner, $location] = openShop();
    publishedOffer($owner, $shop, $location, 'FN0001');
    $preference = route('watches.preference');
    $sweep = app(NotifyRecordWatchers::class);

    $this->actingAs($user)->post($preference, ['availability_email' => '0'])->assertRedirect(route('watches.index'));
    $sweep->handle();
    Notification::assertNothingSent();
    expect(RecordWatch::sole()->notified_at)->toBeNull();

    $this->post($preference, ['availability_email' => '1'])->assertRedirect(route('watches.index'));
    $sweep->handle();
    $notice = Notification::sent($user, RecordAvailableNotice::class)->sole();
    expect($notice->shouldSend($user, 'mail'))->toBeTrue();
    $this->post($preference, ['availability_email' => '0'])->assertRedirect(route('watches.index'));
    expect($notice->shouldSend($user, 'mail'))->toBeFalse();
});

it('unsubscribes without signing in and makes old signed links harmless after reactivation', function () {
    Notification::fake();
    $user = aquarist();
    $watch = app(WatchRecord::class);
    $watch->handle($user, Area::Fauna, 'FN0001');
    [$shop, $owner, $location] = openShop();
    $offer = publishedOffer($owner, $shop, $location, 'FN0001');
    app(NotifyRecordWatchers::class)->handle();
    $notice = Notification::sent($user, RecordAvailableNotice::class)->sole();
    $url = $notice->unsubscribeUrl();

    $this->get($url.'&change=1')->assertForbidden();
    $this->call('HEAD', $url)->assertMethodNotAllowed();
    expect(RecordWatch::sole()->removed_at)->toBeNull();
    $this->get($url)->assertOk()->assertSee('Obserwacja została zakończona.');
    expect(RecordWatch::sole()->removed_at)->not->toBeNull()
        ->and($notice->shouldSend($user, 'mail'))->toBeFalse();

    $offer->update(['free_qty' => 0]);
    $watch->handle($user, Area::Fauna, 'FN0001');
    $this->get($url)->assertOk()->assertSee('Link dotyczy nieaktywnej obserwacji.');
    expect(RecordWatch::sole()->id)->toBe($notice->watchId)
        ->and(RecordWatch::sole()->removed_at)->toBeNull()
        ->and(RecordWatch::sole()->notified_at)->toBeNull();
});

it('retires notified watches after the configured period and can restart them in place', function () {
    Notification::fake();
    shopSetting(SettingKey::ShopWatch, ['tank' => 10, 'fishroom' => 100, 'retention_days' => 2]);
    $user = aquarist();
    $start = app(WatchRecord::class);
    $start->handle($user, Area::Fauna, 'FN0001');
    [$shop, $owner, $location] = openShop();
    $offer = publishedOffer($owner, $shop, $location, 'FN0001');
    $sweep = app(NotifyRecordWatchers::class);
    $sweep->handle();
    $initial = RecordWatch::sole();
    $offer->update(['free_qty' => 0]);
    $start->handle($user, Area::Fauna, 'FN0001');
    expect(RecordWatch::sole()->id)->toBe($initial->id)
        ->and(RecordWatch::sole()->notified_at)->toBeNull();

    $offer->update(['free_qty' => 2]);
    $sweep->handle();
    $this->travel(1)->day();
    $sweep->handle();
    expect(RecordWatch::sole()->removed_at)->toBeNull();
    $this->travel(1)->day();
    $sweep->handle();
    expect(RecordWatch::sole()->removed_at)->not->toBeNull();
    Notification::assertSentToTimes($user, RecordAvailableNotice::class, 2);
});

it('exports the account watches and email preference and erases both when the account is deleted', function () {
    $user = aquarist();
    $other = aquarist();
    app(WatchRecord::class)->handle($other, Area::Fauna, 'FN0002');
    $this->actingAs($user)->post(route('watches.store', ['fauna', 'FN0001']));
    $this->post(route('watches.preference'), ['availability_email' => '0']);

    $this->post(route('account.privacy.export'))->assertOk()
        ->assertJsonCount(1, 'record_watches')->assertJsonPath('record_watches.0.legacy_id', 'FN0001')
        ->assertJsonPath('notification_preferences.availability', false);
    $this->delete(route('account.privacy.delete'), ['current_password' => 'password', 'confirmation' => 'USUŃ'])
        ->assertRedirect(route('login'));

    $this->assertDatabaseMissing('record_watches', ['user_id' => $user->id]);
    $this->assertDatabaseMissing('notification_preferences', ['user_id' => $user->id]);
    $this->assertDatabaseHas('record_watches', ['user_id' => $other->id, 'legacy_id' => 'FN0002']);
});
