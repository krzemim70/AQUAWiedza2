<?php

use App\Domain\Aquarium\Models\ReminderType;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\IdentityAccess\Actions\PurgeUnverifiedAccounts;
use App\Domain\IdentityAccess\Models\Consent;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Actions\ChangeCart;
use App\Domain\Shops\Actions\SubmitCart;
use App\Domain\Shops\Models\MemberRole;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderState;
use App\Domain\Shops\Models\ShopState;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

function privacyTank(User $user, string $name, string $entry): Tank
{
    $tank = tankFor($user, $name);
    $tank->livestock()->create(['area' => Area::Fauna, 'catalog_id' => 'FN0001', 'quantity' => 4]);
    measure($user, $tank->id, ['ph' => '7.20']);
    $tank->journalEntries()->create(['entry_date' => today(), 'content' => $entry]);
    $reminder = $tank->reminders()->create(['type' => ReminderType::WaterChange, 'label' => 'Podmiana', 'starts_on' => today()]);
    $reminder->occurrences()->create(['due_on' => today(), 'state' => 'done', 'completed_at' => now(), 'completed_by' => $user->id]);

    return $tank;
}

function privacyOffer(): Offer
{
    Notification::fake();
    app(ImportPackage::class)->handle(kbPackage());
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 20]);
    [$shop, $owner, $location] = openShop();

    return publishedOffer($owner, $shop, $location, faunaIds(1)[0]);
}

function privacyOrder(User $customer, Offer $offer, string $phone): Order
{
    app(ChangeCart::class)->handle($customer, $offer->id, 1);

    return app(SubmitCart::class)->handle($customer, 'zamowienie-01', $phone)[0];
}

it('requires authentication for every privacy operation', function () {
    $this->get(route('account.privacy'))->assertRedirect(route('login'));
    $this->post(route('account.privacy.newsletter'))->assertRedirect(route('login'));
    $this->post(route('account.privacy.export'))->assertRedirect(route('login'));
    $this->delete(route('account.privacy.delete'))->assertRedirect(route('login'));
});

it('shows the version and date and appends a single newsletter withdrawal to the history', function () {
    $user = aquarist();
    $grant = Consent::create([
        'user_id' => $user->id, 'document' => 'newsletter', 'document_version' => '1',
        'granted' => true, 'created_at' => now()->subDay(),
    ]);

    $this->actingAs($user)->get(route('account'))->assertSee(route('account.privacy'));
    shopSetting(SettingKey::ConsentVersion, '2');
    $this->get(route('consents'))->assertSee(route('account.privacy'));
    $this->get(route('account.privacy'))->assertOk()->assertSee('wersja 1')
        ->assertSee($grant->created_at->timezone(config('app.display_timezone'))->format('d.m.Y, H:i'))
        ->assertSee('Cofnij zgodę na newsletter');

    $this->post(route('account.privacy.newsletter'))->assertRedirect(route('account.privacy'))->assertSessionHas('status');
    $this->post(route('account.privacy.newsletter'))->assertRedirect(route('account.privacy'));
    $this->get(route('account.privacy'))->assertDontSee('Cofnij zgodę na newsletter')->assertSee('Wycofano');

    $history = Consent::forUser($user)->where('document', 'newsletter')->orderBy('id')->get();
    expect($history->pluck('granted')->all())->toBe([false, true, false])
        ->and($history->last()->document_version)->toBe('1')
        ->and($history->last()->created_at->gt($grant->created_at))->toBeTrue()
        ->and($grant->fresh()->granted)->toBeTrue();
});

it('downloads only the authenticated customer data and rate limits each account separately', function () {
    $user = User::factory()->create(['email' => 'moje@example.test']);
    $other = User::factory()->create(['email' => 'obce@example.test']);
    privacyTank($user, 'Mój zbiornik', 'Moja notatka');
    privacyTank($other, 'Obcy zbiornik', 'Obca notatka');
    $offer = privacyOffer();
    $order = privacyOrder($user, $offer, '600 100 123');
    privacyOrder($other, $offer, '700 200 456');
    $order->update(['note' => 'Prywatna notatka sklepu']);
    $order->events()->update(['reason' => 'Dane pracownika', 'actor_id' => $other->id]);
    app(ChangeCart::class)->handle($user, $offer->id, 2);
    app(ChangeCart::class)->handle($other, $offer->id, 3);

    $url = route('account.privacy.export');
    $response = $this->actingAs($user)->post($url)->assertOk()->assertDownload('aikwarium-moje-dane.json')
        ->assertJsonPath('account.email', $user->email)->assertJsonCount(4, 'consents')
        ->assertJsonCount(1, 'tanks')->assertJsonPath('tanks.0.name', 'Mój zbiornik')
        ->assertJsonPath('tanks.0.livestock.0.quantity', 4)->assertJsonPath('tanks.0.measurements.0.ph', '7.20')
        ->assertJsonPath('tanks.0.journal_entries.0.content', 'Moja notatka')
        ->assertJsonPath('tanks.0.reminders.0.occurrences.0.state', 'done')
        ->assertJsonCount(1, 'cart')->assertJsonPath('cart.0.qty', 2)
        ->assertJsonCount(1, 'orders')->assertJsonPath('orders.0.id', $order->id)
        ->assertJsonPath('orders.0.items.0.qty', 1)->assertJsonPath('orders.0.contact.phone', '600 100 123')
        ->assertJsonPath('orders.0.events.0.to_state', OrderState::New->value)
        ->assertDontSee('obce@example.test')->assertDontSee('700 200 456')->assertDontSee('Obca notatka')
        ->assertDontSee('Prywatna notatka sklepu')->assertDontSee('Dane pracownika');

    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    $response->assertJsonMissingPath('account.password')->assertJsonMissingPath('account.remember_token')
        ->assertJsonMissingPath('account.app_authentication_secret')->assertJsonMissingPath('orders.0.events.0.actor_id');
    $this->post($url)->assertTooManyRequests();
    $this->actingAs($other)->post($url)->assertOk()->assertJsonPath('account.email', $other->email);
    $this->travel(61)->minutes();
    $this->actingAs($user)->post($url)->assertOk();
});

it('requires the password and confirmation word before deleting', function () {
    $user = aquarist();
    $url = route('account.privacy.delete');

    $this->actingAs($user)->from(route('account.privacy'))->delete($url, ['current_password' => 'błędne', 'confirmation' => 'USUŃ'])
        ->assertRedirect(route('account.privacy'))->assertSessionHasErrors('current_password')->assertSessionMissing('_old_input.current_password');
    $this->delete($url, ['current_password' => 'password', 'confirmation' => 'TAK'])->assertSessionHasErrors('confirmation');
    $this->assertDatabaseHas('users', ['id' => $user->id]);
});

it('clears private data and preserves pseudonymized orders after deleting the account', function () {
    config(['session.driver' => 'database']);
    $user = aquarist();
    $other = aquarist();
    $tank = privacyTank($user, 'Mój zbiornik', 'Moja notatka');
    $otherTank = tankFor($other);
    $offer = privacyOffer();
    $order = privacyOrder($user, $offer, '600 100 123');
    $otherOrder = privacyOrder($other, $offer, '700 200 456');
    $order->update(['note' => 'Notatka z kontaktem']);
    $order->events()->update(['reason' => 'Dodatkowy kontakt']);
    app(ChangeCart::class)->handle($user, $offer->id, 2);
    app(ChangeCart::class)->handle($other, $offer->id, 2);
    Password::broker()->createToken($user);
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $url = route('account.privacy.delete');
    $this->actingAs($user)->post($url, ['_method' => 'DELETE', 'current_password' => 'password', 'confirmation' => 'USUŃ'])
        ->assertRedirect(route('login'))->assertSessionHas('status', 'Konto zostało usunięte.');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    $this->assertDatabaseMissing('consents', ['user_id' => $user->id]);
    $this->assertDatabaseMissing('tanks', ['id' => $tank->id]);
    $this->assertDatabaseEmpty('reminder_occurrences');
    foreach (['tank_livestock', 'water_measurements', 'journal_entries', 'reminders'] as $table) {
        $this->assertDatabaseMissing($table, ['tank_id' => $tank->id]);
    }
    $this->assertDatabaseMissing('shop_cart_items', ['user_id' => $user->id]);
    $this->assertDatabaseHas('shop_cart_items', ['user_id' => $other->id]);
    $this->assertDatabaseHas('tanks', ['id' => $otherTank->id]);
    $this->assertDatabaseHas('shop_checkouts', ['id' => $order->checkout_id, 'user_id' => null]);
    $this->assertDatabaseHas('shop_order_contacts', ['order_id' => $otherOrder->id, 'phone' => '700 200 456']);
    expect($order->refresh())->state->toBe(OrderState::New)->pseudonymized_at->not->toBeNull()
        ->and($order->items)->toHaveCount(1)->and($order->contact)->toBeNull()
        ->and($order->user_id)->toBeNull()->and($order->note)->toBeNull()
        ->and($order->events->first()->actor_id)->toBeNull()->and($order->events->first()->reason)->toBeNull();

    $audit = DB::table('activity_log')->where('event', 'account_deleted')->sole();
    expect($audit->causer_id)->toBeNull()->and($audit->subject_id)->toBeNull()
        ->and(json_decode($audit->properties, true))->toBe([]);
});

it('blocks the active shop owner until ownership is transferred', function () {
    [$shop, $owner] = openShop(name: 'Aktywny sklep');
    $shop->update(['state' => ShopState::Active]);
    $staff = staffOf($shop, $owner);
    $url = route('account.privacy.delete');
    $data = ['current_password' => 'password', 'confirmation' => 'USUŃ'];

    $response = $this->actingAs($owner)->from(route('account.privacy'))->delete($url, $data)
        ->assertRedirect(route('account.privacy'))->assertSessionHasErrors('deletion');
    $cookie = $response->getCookie(config('session.cookie'), false);
    $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())->get(route('account.privacy'))
        ->assertSee('Jesteś jedynym właścicielem aktywnego sklepu')->assertSee('Aktywny sklep');
    $this->assertDatabaseHas('users', ['id' => $owner->id]);
    DB::transaction(function () use ($shop, $owner, $staff) {
        $shop->members()->where('user_id', $owner->id)->update(['role' => MemberRole::Staff]);
        $shop->members()->where('user_id', $staff->id)->update(['role' => MemberRole::Owner]);
    });

    $this->delete($url, $data)->assertRedirect(route('login'));
    $this->assertDatabaseMissing('shop_members', ['shop_id' => $shop->id, 'user_id' => $owner->id]);
    $this->assertDatabaseHas('shop_members', ['shop_id' => $shop->id, 'user_id' => $staff->id, 'role' => MemberRole::Owner->value]);
    expect($shop->refresh()->state)->toBe(ShopState::Active);
});

it('keeps administrator accounts outside self-service deletion', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('account.privacy'))->assertOk()->assertSee('Konto administratora może usunąć tylko operator serwisu')
        ->assertDontSee('Wpisz słowo USUŃ');
    $this->delete(route('account.privacy.delete'), ['current_password' => 'password', 'confirmation' => 'USUŃ'])->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

it('purges only unverified accounts past the configured retention and can be repeated', function () {
    $this->freezeSecond();
    shopSetting(SettingKey::PendingRetentionDays, 10);
    shopSetting(SettingKey::PendingCleanupBatch, 1);
    $old = User::factory()->unverified()->count(2)->create(['created_at' => now()->subDays(11)]);
    $atCutoff = User::factory()->unverified()->create(['created_at' => now()->subDays(10)]);
    $recent = User::factory()->unverified()->create(['created_at' => now()->subDays(9)]);
    $verified = User::factory()->create(['created_at' => now()->subDays(11)]);

    $purge = app(PurgeUnverifiedAccounts::class);
    expect($purge->handle())->toBe(2)->and($purge->handle())->toBe(0);
    expect(User::whereKey($old->modelKeys())->exists())->toBeFalse();
    expect(User::whereKey([$atCutoff->id, $recent->id, $verified->id])->count())->toBe(3);
    $this->assertDatabaseCount('consents', 12);
});

it('discloses the functional cookies and the configured lifetime on the privacy page', function () {
    shopSetting(SettingKey::DropCookieDays, 21);
    $sessionCookie = config('session.cookie');
    $rememberCookie = Auth::guard('web')->getRecallerName();

    $this->get('/prywatnosc')->assertOk()->assertSee('Ciasteczka')->assertSee($sessionCookie)->assertSee('XSRF-TOKEN')
        ->assertSee('drop_allowance')->assertSee('Do 21 dni')->assertSee($rememberCookie)->assertSee('Do 400 dni')
        ->assertCookie($sessionCookie)->assertCookie('XSRF-TOKEN')->assertCookieMissing('drop_allowance')->assertCookieMissing($rememberCookie);
    $this->get('/regulamin')->assertOk()->assertDontSee('drop_allowance');

    $user = aquarist();
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => 1])
        ->assertRedirect('/moje-akwarium')->assertCookie($rememberCookie);
});
