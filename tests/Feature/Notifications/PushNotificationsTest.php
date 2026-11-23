<?php

use App\Domain\Aquarium\Actions\CreateReminder;
use App\Domain\Aquarium\Actions\SendDueReminders;
use App\Domain\Aquarium\Contracts\LowStockNotifier;
use App\Domain\Aquarium\Data\ReminderData;
use App\Domain\Notifications\Actions\SaveNotificationPreference;
use App\Domain\Notifications\Actions\SavePushSubscription;
use App\Domain\Notifications\Contracts\Preferences;
use App\Domain\Notifications\Contracts\PushTransport;
use App\Domain\Notifications\Data\PushDeliveryResult;
use App\Domain\Notifications\Data\PushPayload;
use App\Domain\Notifications\Data\PushSubscriptionData;
use App\Domain\Notifications\Models\PushSubscription;
use App\Domain\Notifications\Topic;
use App\Domain\Settings\SettingKey;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

/**
 * @param  callable(PushSubscription, PushPayload): PushDeliveryResult|null  $responder
 */
function fakePushTransport(?callable $responder = null): object
{
    $transport = new class($responder) implements PushTransport
    {
        /** @var list<array{endpoint: string, payload: PushPayload}> */
        public array $sent = [];

        /** @var (callable(PushSubscription, PushPayload): PushDeliveryResult)|null */
        private $responder;

        /** @param (callable(PushSubscription, PushPayload): PushDeliveryResult)|null $responder */
        public function __construct(?callable $responder)
        {
            $this->responder = $responder;
        }

        public function send(PushSubscription $subscription, PushPayload $payload): PushDeliveryResult
        {
            $this->sent[] = ['endpoint' => $subscription->endpoint, 'payload' => $payload];

            if ($this->responder !== null) {
                return ($this->responder)($subscription, $payload);
            }

            return new PushDeliveryResult(true, false, 201, 'Created');
        }
    };

    app()->instance(PushTransport::class, $transport);

    return $transport;
}

it('saves, updates and deletes push subscriptions with ownership and validation checks', function () {
    $user = aquarist();
    $other = aquarist();
    $endpoint = 'https://push.example.test/sub/alpha';

    $this->postJson(route('push.store'), [
        'endpoint' => $endpoint,
        'keys' => ['p256dh' => 'key-1', 'auth' => 'auth-1'],
    ])->assertUnauthorized();

    $this->actingAs($user)->postJson(route('push.store'), [
        'endpoint' => 'http://insecure.example.test/sub',
        'keys' => ['p256dh' => 'key-1', 'auth' => 'auth-1'],
    ])->assertUnprocessable()->assertJsonValidationErrors('endpoint');

    $this->postJson(route('push.store'), [
        'endpoint' => $endpoint,
        'keys' => ['p256dh' => 'key-1', 'auth' => 'auth-1'],
        'content_encoding' => 'aes128gcm',
    ])->assertCreated()->assertJson(['subscribed' => true, 'push_enabled' => true]);

    $subscription = PushSubscription::sole();
    expect($subscription->user_id)->toBe($user->id)
        ->and($subscription->endpoint_hash)->toBe(PushSubscription::hashEndpoint($endpoint))
        ->and($subscription->p256dh)->toBe('key-1')
        ->and(app(Preferences::class)->allowsPush($user->id, Topic::Reminders))->toBeTrue();

    // Ponowny zapis tego samego endpointu aktualizuje rekord bez duplikatu.
    $this->postJson(route('push.store'), [
        'endpoint' => $endpoint,
        'keys' => ['p256dh' => 'key-2', 'auth' => 'auth-2'],
    ])->assertCreated();

    expect(PushSubscription::count())->toBe(1)
        ->and($subscription->fresh()->p256dh)->toBe('key-2');

    // Inny użytkownik nie może usunąć cudzej subskrypcji.
    $this->actingAs($other)->deleteJson(route('push.destroy'), ['endpoint' => $endpoint])->assertOk();
    expect(PushSubscription::count())->toBe(1);

    // Właściciel usuwa subskrypcję i wyłącza preferencję push.
    $this->actingAs($user)->deleteJson(route('push.destroy'), ['endpoint' => $endpoint])
        ->assertOk()->assertJson(['subscribed' => false, 'push_enabled' => false]);

    expect(PushSubscription::count())->toBe(0)
        ->and(app(Preferences::class)->allowsPush($user->id, Topic::Reminders))->toBeFalse();
});

it('sends due reminder push notifications only once after the configured hour', function () {
    $transport = fakePushTransport();
    shopSetting(SettingKey::PushNotifications, ['enabled' => true, 'reminder_hour' => 8, 'low_stock_default' => false]);
    Carbon::setTestNow(Carbon::parse('2026-06-05 07:30:00', 'Europe/Warsaw')->utc());

    $user = aquarist(fishroom: true);
    $tank = tankFor($user, 'Salon 120');
    app(CreateReminder::class)->handle($user, $tank->id, new ReminderData('water_change', 'Podmiana 20%', '2026-06-05'));
    app(CreateReminder::class)->handle($user, $tank->id, new ReminderData('filter_cleaning', 'Jutro', '2026-06-06'));
    app(CreateReminder::class)->handle($user, $tank->id, new ReminderData('fertilization', 'Wczoraj', '2026-06-04'));

    $subscription = app(SavePushSubscription::class)->handle($user, new PushSubscriptionData(
        endpoint: 'https://push.example.test/sub/reminders',
        p256dh: 'p256dh-value',
        authToken: 'auth-value',
    ));
    app(SaveNotificationPreference::class)->handle($user, Topic::Reminders, push: true);

    // Przed godziną wysyłki z ustawień nic nie wychodzi.
    expect(app(SendDueReminders::class)->handle())->toBe(0)
        ->and($transport->sent)->toHaveCount(0);
    $this->assertDatabaseCount('reminder_notices', 0);

    // O 08:00 czasu wyświetlania wychodzi dokładnie jedno powiadomienie za przypomnienie z terminem na dziś.
    Carbon::setTestNow(Carbon::parse('2026-06-05 08:00:00', 'Europe/Warsaw')->utc());
    expect(app(SendDueReminders::class)->handle())->toBe(1)
        ->and($transport->sent)->toHaveCount(1);

    $delivered = $transport->sent[0];
    expect($delivered['endpoint'])->toBe('https://push.example.test/sub/reminders')
        ->and($delivered['payload']->title)->toBe('Przypomnienie — Salon 120')
        ->and($delivered['payload']->body)->toBe('Podmiana 20%')
        ->and($delivered['payload']->url)->toBe(route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'przypomnienia'], false))
        ->and($subscription->fresh()->last_used_at)->not->toBeNull();
    $this->assertDatabaseCount('reminder_notices', 1);

    // Kolejne uruchomienie tego samego dnia jest idempotentne i nie dubluje wysyłki.
    Carbon::setTestNow(Carbon::parse('2026-06-05 09:00:00', 'Europe/Warsaw')->utc());
    expect(app(SendDueReminders::class)->handle())->toBe(0)
        ->and($transport->sent)->toHaveCount(1);
});

it('removes expired subscriptions on 410 and 404 responses while keeping active ones', function () {
    $transport = fakePushTransport(function (PushSubscription $subscription): PushDeliveryResult {
        return match ($subscription->endpoint) {
            'https://push.example.test/sub/gone' => new PushDeliveryResult(false, false, 410, 'Gone'),
            'https://push.example.test/sub/missing' => new PushDeliveryResult(false, false, 404, 'Not Found'),
            default => new PushDeliveryResult(true, false, 201, 'Created'),
        };
    });

    Carbon::setTestNow(Carbon::parse('2026-06-05 08:15:00', 'Europe/Warsaw')->utc());
    $user = aquarist(fishroom: true);
    $tank = tankFor($user);
    app(CreateReminder::class)->handle($user, $tank->id, new ReminderData('water_change', null, '2026-06-05'));
    app(SaveNotificationPreference::class)->handle($user, Topic::Reminders, push: true);

    $save = app(SavePushSubscription::class);
    $gone = $save->handle($user, new PushSubscriptionData('https://push.example.test/sub/gone', 'k1', 'a1'));
    $missing = $save->handle($user, new PushSubscriptionData('https://push.example.test/sub/missing', 'k2', 'a2'));
    $active = $save->handle($user, new PushSubscriptionData('https://push.example.test/sub/active', 'k3', 'a3'));

    expect(app(SendDueReminders::class)->handle())->toBe(1)
        ->and($transport->sent)->toHaveCount(3);

    $this->assertDatabaseMissing('push_subscriptions', ['id' => $gone->id]);
    $this->assertDatabaseMissing('push_subscriptions', ['id' => $missing->id]);
    $this->assertDatabaseHas('push_subscriptions', ['id' => $active->id]);
    expect($active->fresh()->last_used_at)->not->toBeNull();
});

it('does not send push notifications when preference, global switch or Fishroom access is off', function () {
    $transport = fakePushTransport();
    Carbon::setTestNow(Carbon::parse('2026-06-05 09:00:00', 'Europe/Warsaw')->utc());

    $user = aquarist(fishroom: true);
    $tank = tankFor($user);
    app(CreateReminder::class)->handle($user, $tank->id, new ReminderData('water_change', null, '2026-06-05'));
    app(SavePushSubscription::class)->handle($user, new PushSubscriptionData('https://push.example.test/sub/pref', 'k1', 'a1'));

    // Domyślnie preferencja push jest wyłączona.
    expect(app(Preferences::class)->allowsPush($user->id, Topic::Reminders))->toBeFalse()
        ->and(app(SendDueReminders::class)->handle())->toBe(0)
        ->and($transport->sent)->toHaveCount(0);
    $this->assertDatabaseCount('reminder_notices', 0);

    // Wyłączenie globalne blokuje wysyłkę nawet przy włączonej preferencji użytkownika.
    app(SaveNotificationPreference::class)->handle($user, Topic::Reminders, push: true);
    shopSetting(SettingKey::PushNotifications, ['enabled' => false, 'reminder_hour' => 8, 'low_stock_default' => false]);
    expect(app(SendDueReminders::class)->handle())->toBe(0)
        ->and($transport->sent)->toHaveCount(0);

    // Zakończony Fishroom również blokuje wysyłkę przypomnień.
    Carbon::setTestNow(now()->addMinute());
    shopSetting(SettingKey::PushNotifications, ['enabled' => true, 'reminder_hour' => 8, 'low_stock_default' => false]);
    $user->forceFill(['fishroom_ends_at' => now()->subMinute()])->save();
    expect(app(SendDueReminders::class)->handle())->toBe(0)
        ->and($transport->sent)->toHaveCount(0);
});

it('calls the low-stock notifier extension point during the daily run', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-05 08:30:00', 'Europe/Warsaw')->utc());
    $notifier = new class implements LowStockNotifier
    {
        public ?string $calledAt = null;

        public function handle(CarbonInterface $localNow): int
        {
            $this->calledAt = $localNow->toDateString();

            return 2;
        }
    };
    app()->instance(LowStockNotifier::class, $notifier);

    expect(app(SendDueReminders::class)->handle())->toBe(2)
        ->and($notifier->calledAt)->toBe('2026-06-05');
});

it('sets no-store cache headers on private pages and exposes PWA assets and account push controls', function () {
    config(['services.webpush.public_key' => 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U']);
    $user = aquarist();

    foreach ([route('account'), route('aquarium.index'), route('cart.show'), route('orders.index')] as $url) {
        $response = $this->actingAs($user)->get($url)->assertOk();
        expect($response->headers->hasCacheControlDirective('no-store'))->toBeTrue()
            ->and($response->headers->hasCacheControlDirective('private'))->toBeTrue();
    }

    $this->actingAs($user)->get(route('account'))
        ->assertOk()
        ->assertSee('manifest.webmanifest', false)
        ->assertSee('data-push-controls hidden', false)
        ->assertSee('Włączenie powiadomień push wymaga włączonej obsługi JavaScript w przeglądarce.')
        ->assertSee('Dodaj do ekranu początkowego');

    $offline = $this->get(route('pwa.offline'))->assertOk()
        ->assertSee('Brak połączenia z internetem')
        ->assertHeader('X-Robots-Tag', 'noindex');
    expect($offline->headers->hasCacheControlDirective('no-store'))->toBeFalse();

    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);
    expect($manifest['name'])->toBe('Aikwarium')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['icons'])->not->toBeEmpty();

    $sw = (string) file_get_contents(public_path('sw.js'));
    expect($sw)->toContain('/build/')
        ->and($sw)->toContain('/offline')
        ->and($sw)->toContain("addEventListener('push'")
        ->and($sw)->toContain("addEventListener('notificationclick'");
});

it('exports push subscriptions and preferences and deletes them when the account is removed', function () {
    $user = aquarist();
    $other = aquarist();

    app(SavePushSubscription::class)->handle($user, new PushSubscriptionData('https://push.example.test/sub/user-a', 'secret-p256dh', 'secret-auth'));
    app(SaveNotificationPreference::class)->handle($user, Topic::Reminders, push: true);
    app(SaveNotificationPreference::class)->handle($user, Topic::Availability, email: false);

    app(SavePushSubscription::class)->handle($other, new PushSubscriptionData('https://push.example.test/sub/user-b', 'other-p256dh', 'other-auth'));
    app(SaveNotificationPreference::class)->handle($other, Topic::Reminders, push: true);

    $export = $this->actingAs($user)->post(route('account.privacy.export'))->assertOk();
    $export->assertJsonPath('notification_preferences.availability', false)
        ->assertJsonPath('push_preferences.reminders', true)
        ->assertJsonCount(1, 'push_subscriptions')
        ->assertJsonPath('push_subscriptions.0.endpoint', 'https://push.example.test/sub/user-a');

    expect($export->json('push_subscriptions.0'))->not->toHaveKeys(['p256dh', 'auth_token']);

    $this->delete(route('account.privacy.delete'), ['current_password' => 'password', 'confirmation' => 'USUŃ'])
        ->assertRedirect(route('login'));

    $this->assertDatabaseMissing('push_subscriptions', ['user_id' => $user->id]);
    $this->assertDatabaseMissing('notification_preferences', ['user_id' => $user->id]);
    $this->assertDatabaseHas('push_subscriptions', ['user_id' => $other->id, 'endpoint' => 'https://push.example.test/sub/user-b']);
    $this->assertDatabaseHas('notification_preferences', ['user_id' => $other->id, 'topic' => Topic::Reminders->value, 'push' => true]);
});
