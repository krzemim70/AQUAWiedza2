<?php

use App\Domain\IdentityAccess\Notifications\ResetPasswordNotice;
use App\Domain\IdentityAccess\Notifications\VerifyEmailNotice;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Models\DailyReport;
use App\Domain\Shops\Models\Location;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderState;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Notifications\DailyReportNotice;
use App\Domain\Shops\Notifications\OrderNotice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

it('renders Polish HTML and plain text and queues each mail after commit', function (string $kind, string $subject, string $line, string $actionRoute) {
    app()->setLocale('pl');
    $user = new User(['email' => 'klient@example.test']);
    $user->id = 1;
    $shop = new Shop(['name' => 'Zielone Akwarium']);
    $shop->id = 2;
    $order = new Order(['id' => 3, 'shop_id' => 2, 'ean13' => '5900000000423', 'total_grosze' => 1250, 'rejection_code' => 'inne', 'note' => 'Brak dostawy [opis](https://example.test)']);
    $order->setRelation('shop', $shop)->setRelation('location', new Location(['name' => 'Placówka Główna', 'address' => 'Rynek 1', 'city' => 'Poznań']));

    $notice = match ($kind) {
        'verify' => new VerifyEmailNotice(3600),
        'reset' => new ResetPasswordNotice('token'),
        'report' => new DailyReportNotice($shop, new DailyReport([
            'business_date' => '2026-01-02',
            'data' => [
                'orders' => array_fill_keys(array_column(OrderState::cases(), 'value'), 1),
                'pieces' => ['reserved' => 3, 'released' => 1, 'issued' => 1],
                'payments_grosze' => 1500,
            ],
        ])),
        default => new OrderNotice($order, $kind),
    };
    $mail = $notice->toMail($user);
    $html = (string) $mail->render();
    $text = (string) app(Markdown::class)->renderText($mail->markdown, $mail->data());
    $request = Request::create($mail->actionUrl);

    expect($mail->subject)->toStartWith($subject)
        ->and($html)->toContain($mail->subject, $line, 'Ustawienia konta', 'lang="pl"', e($mail->actionUrl))
        ->not->toContain('<img', 'href="https://example.test"')
        ->and(substr_count($html, 'class="button button-primary"'))->toBe(1)
        ->and($text)->toContain($mail->subject, $line, $mail->actionUrl, route('account'), 'Ustawienia konta')
        ->not->toContain('<p>', '<a ')
        ->and(Route::getRoutes()->match($request)->getName())->toBe($actionRoute);

    Queue::fake();
    $user->notify($notice);
    Queue::assertPushed(SendQueuedNotifications::class, fn (SendQueuedNotifications $job) => $job->notification instanceof $notice
        && $job->channels === ['mail'] && $job->afterCommit === true && $job->tries === 3 && $job->backoff() === [60, 300]);
})->with([
    ['verify', 'Potwierdź adres e-mail', 'Link jest ważny do', 'verification.verify'],
    ['reset', 'Ustaw nowe hasło', 'od chwili wysłania prośby', 'password.reset'],
    ['submitted', 'Nowe zamówienie do rozpatrzenia', 'czeka nowe zamówienie nr 3', 'filament.shop.resources.zamowienia.view'],
    ['confirmed', 'Zamówienie potwierdzone', '12,50 zł', 'orders.show'],
    ['rejected', 'Zamówienie odrzucone', 'Brak dostawy', 'orders.show'],
    ['ready', 'Zamówienie gotowe do odbioru', 'Placówka Główna, Rynek 1, Poznań', 'orders.show'],
    ['report', 'Raport dobowy', 'Potwierdzone wpływy: 15,00 zł', 'filament.shop.resources.zamowienia.index'],
]);

it('issues a signed verification link with the configured lifetime', function () {
    $this->freezeSecond();
    shopSetting(SettingKey::ActivationLifetime, 3600);
    Queue::fake();
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();
    $notice = Queue::pushed(SendQueuedNotifications::class)->sole()->notification;
    $request = Request::create($notice->toMail($user)->actionUrl);

    expect(URL::hasValidSignature($request))->toBeTrue()
        ->and((int) $request->query('expires'))->toBe(now()->addHour()->timestamp);

    $this->get($request->fullUrl())->assertRedirect(route('login'));
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('enqueues auth mail in the database only after commit and discards it on rollback', function () {
    config(['queue.default' => 'database']);
    $user = User::factory()->unverified()->create();
    $token = Password::createToken($user);

    DB::transaction(function () use ($user, $token) {
        $user->sendEmailVerificationNotification();
        $user->sendPasswordResetNotification($token);
        expect(DB::table('jobs')->count())->toBe(0);
    });

    $payloads = DB::table('jobs')->pluck('payload');
    expect($payloads)->toHaveCount(2)
        ->and($payloads->implode(' '))->not->toContain($token);

    DB::beginTransaction();
    try {
        $user->sendEmailVerificationNotification();
        expect(DB::table('jobs')->count())->toBe(2);
    } finally {
        DB::rollBack();
    }
    expect(DB::table('jobs')->count())->toBe(2);
});

it('only exposes sample mail in the local environment, even if the route was registered earlier', function () {
    $url = '/_podglad-poczty/weryfikacja';
    $this->get($url)->assertNotFound();

    app()->instance('env', 'local');
    Route::middleware('web')->group(base_path('routes/web.php'));
    $this->get($url)->assertOk()->assertSee('Potwierdź adres e-mail');
    $this->get('/_podglad-poczty/nieznana')->assertNotFound();

    app()->instance('env', 'production');
    $this->get($url)->assertNotFound();
});
