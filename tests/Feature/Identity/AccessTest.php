<?php

use App\Domain\IdentityAccess\Actions\ActivateAccount;
use App\Domain\IdentityAccess\Actions\ApproveShopApplication;
use App\Domain\IdentityAccess\Actions\ChangeAccess;
use App\Domain\IdentityAccess\Actions\ExpireFishroom;
use App\Domain\IdentityAccess\Actions\RejectShopApplication;
use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Data\ChangeAccessData;
use App\Domain\IdentityAccess\Models\AccessChange;
use App\Domain\IdentityAccess\Models\AccessChangeKind as Kind;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\IdentityAccess\Models\ShopApplication;
use App\Domain\IdentityAccess\Models\Tier;
use App\Domain\Settings\SettingKey;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

afterEach(fn () => Carbon::setTestNow());

/** Macierz z modelu domenowego (sekcja 2): [gość, Tank, Fishroom]. */
const MATRIX = [
    'login' => [true, true, true],
    'drop_selection' => [true, false, false],
    'tank_ranking' => [false, true, true],
    'tank_hit_description' => [false, true, true],
    'guided_advisor' => [false, true, true], // domyślnie kreator jest otwarty dla Tank (ustawienie advisor.guided.tank_access)
    'tank_view' => [false, true, true],
    'tank_add' => [false, true, true],
    'measurement_write' => [false, true, true],
    'measurement_history' => [false, false, true],
    'redfield' => [false, true, true],
    'public_offers' => [true, true, true],
    'shop_cart' => [false, true, true],
    'shop_panel' => [false, false, false],
    'admin_panel' => [false, false, false],
    'own_data' => [false, true, true],
    'delete_account' => [false, true, true],
    'compatibility_check' => [false, true, true],
    'water_hints' => [false, true, true],
    'record_watch' => [false, true, true],
    'tank_plan' => [false, true, true],
    'reminder_suggestions' => [false, false, true],
    'calculators' => [true, true, true],
    'tank_supplies' => [false, true, true],
    'quick_measurement' => [false, true, true],
    'push_notifications' => [false, true, true],
    'measurement_trends' => [false, false, true],
    'public_together' => [true, true, true],
    'creator_offers' => [true, true, true],
    'catalog_spec_edit' => [false, false, false],
];

function fishroomUser(): User
{
    return User::factory()->create(['fishroom_ends_at' => now()->addDay()]);
}

function actor(string $who): ?User
{
    return match ($who) {
        'guest' => null,
        'tank' => User::factory()->create(),
        'fishroom' => fishroomUser(),
        'suspended' => User::factory()->create(['suspended_at' => now()]),
        'admin' => User::factory()->admin()->create(),
    };
}

/** @param array<string, mixed> $context */
function decide(?User $user, Feature $feature, array $context = [])
{
    return app(ResolveAccess::class)->handle($user, $feature, $context);
}

it('covers every Feature in the matrix', function () {
    expect(array_keys(MATRIX))->toEqualCanonicalizing(array_map(fn ($f) => $f->value, Feature::cases()));
});

it('follows the access matrix for guest, Tank and Fishroom', function (string $feature, int $col, string $who) {
    $d = decide(actor($who), Feature::from($feature));

    expect($d->allowed)->toBe(MATRIX[$feature][$col])
        ->and($d->message === null)->toBe($d->allowed);
})->with(fn () => collect(MATRIX)->keys()->flatMap(fn ($f) => [
    "$f guest" => [$f, 0, 'guest'], "$f tank" => [$f, 1, 'tank'], "$f fishroom" => [$f, 2, 'fishroom'],
])->all());

it('allows admin features except self-service deletion and blocks suspended accounts', function (string $feature) {
    expect(decide(actor('admin'), Feature::from($feature))->allowed)->toBe($feature !== 'delete_account');

    $d = decide(actor('suspended'), Feature::from($feature));
    expect($d->allowed)->toBeFalse()->and($d->reason)->toBe('suspended')
        ->and($d->message)->toBe('Konto jest zawieszone. Skontaktuj się z administratorem.');
})->with(array_keys(MATRIX));

it('opens the shop panel only for an approved shop', function () {
    $shop = User::factory()->shop()->create();
    ShopApplication::create(['user_id' => $shop->id, 'company_name' => 'X', 'nip' => '5260250274', 'address' => 'A', 'phone' => '1']);
    expect(decide($shop, Feature::ShopPanel)->allowed)->toBeFalse()
        ->and($shop->tier())->toBe(Tier::Tank);

    app(ApproveShopApplication::class)->handle(actor('admin'), $shop->shopApplication, 'ok');
    expect(decide($shop->refresh(), Feature::ShopPanel)->allowed)->toBeTrue()
        ->and($shop->tier())->toBe(Tier::Tank); // członkostwo niezależne od Fishroom
});

it('limits tanks per tier from settings', function () {
    expect(decide(actor('tank'), Feature::TankAdd, ['tank_count' => 0])->allowed)->toBeTrue()
        ->and(decide(actor('tank'), Feature::TankAdd, ['tank_count' => 1])->allowed)->toBeFalse()
        ->and(decide(fishroomUser(), Feature::TankAdd, ['tank_count' => 50])->allowed)->toBeTrue();
});

it('treats an account with no access data as Tank, and applies the Fishroom end date even before the job runs', function () {
    Carbon::setTestNow('2026-03-01 12:00:00');
    shopSetting(SettingKey::GuidedAdvisorForTank, false);
    $user = User::factory()->create();
    expect($user->tier())->toBe(Tier::Tank)->and(decide($user, Feature::TankView)->allowed)->toBeTrue()
        ->and(decide($user, Feature::GuidedAdvisor)->allowed)->toBeFalse();

    $user->forceFill(['fishroom_ends_at' => now()->addSecond()])->save();
    expect(decide($user, Feature::GuidedAdvisor)->allowed)->toBeTrue();
    Carbon::setTestNow(now()->addSecond());
    expect($user->tier())->toBe(Tier::Tank)->and(decide($user, Feature::GuidedAdvisor)->allowed)->toBeFalse(); // [od, do)
});

it('writes a transition with actor and reason for every manual change', function () {
    Carbon::setTestNow('2026-03-01 12:00:00');
    $admin = actor('admin');
    $user = User::factory()->create();
    $change = fn (Kind $k, string $reason = 'powód', ?Carbon $end = null) => app(ChangeAccess::class)->handle(new ChangeAccessData($admin, $user, $k, $reason, $end));

    $change(Kind::FishroomGranted, 'płatność', now()->addDays(30));
    expect($user->refresh()->tier())->toBe(Tier::Fishroom);
    $change(Kind::Suspended);
    expect($user->refresh()->isSuspended())->toBeTrue();
    $change(Kind::Unsuspended);
    $change(Kind::FishroomRevoked);
    expect($user->refresh()->tier())->toBe(Tier::Tank)->and($user->fishroom_ends_at)->toBeNull();

    $rows = $user->accessChanges()->get()->reverse()->values();
    expect($rows->pluck('kind')->all())->toBe([Kind::FishroomGranted, Kind::Suspended, Kind::Unsuspended, Kind::FishroomRevoked])
        ->and($rows->every(fn ($r) => $r->actor_id === $admin->id))->toBeTrue()
        ->and($rows[0]->reason)->toBe('płatność')
        ->and($rows[0]->fishroom_ends_at->toDateString())->toBe('2026-03-31');
});

it('rejects invalid changes and non-admins without writing history', function () {
    $admin = actor('admin');
    $user = User::factory()->create();
    $try = fn (User $by, Kind $k, string $reason = 'x', ?Carbon $end = null) => fn () => app(ChangeAccess::class)->handle(new ChangeAccessData($by, $user, $k, $reason, $end));

    expect($try($admin, Kind::FishroomGranted, '', now()->addDay()))->toThrow(ValidationException::class)
        ->and($try($admin, Kind::FishroomGranted, 'x', now()->subDay()))->toThrow(ValidationException::class)
        ->and($try($admin, Kind::FishroomGranted))->toThrow(ValidationException::class)
        ->and($try($admin, Kind::FishroomRevoked))->toThrow(ValidationException::class)
        ->and($try($admin, Kind::Unsuspended))->toThrow(ValidationException::class)
        ->and($try($admin, Kind::FishroomExpired))->toThrow(ValidationException::class)
        ->and($try($user, Kind::Suspended))->toThrow(AuthorizationException::class);
    expect(fn () => app(ChangeAccess::class)->handle(new ChangeAccessData($admin, $admin, Kind::Suspended, 'x')))->toThrow(ValidationException::class);
    expect(AccessChange::count())->toBe(0);
});

it('expires Fishroom once and idempotently with a system transition', function () {
    Carbon::setTestNow('2026-03-01 12:00:00');
    $due = User::factory()->create(['fishroom_ends_at' => now()->addDay()]);
    $later = User::factory()->create(['fishroom_ends_at' => now()->addDays(5)]);
    Carbon::setTestNow('2026-03-03 12:00:00');

    expect(app(ExpireFishroom::class)->handle())->toBe(1)->and(app(ExpireFishroom::class)->handle())->toBe(0);

    expect($due->refresh()->fishroom_ends_at)->toBeNull()->and($due->tier())->toBe(Tier::Tank)
        ->and($due->accessChanges)->toHaveCount(1)
        ->and($due->accessChanges->first()->kind)->toBe(Kind::FishroomExpired)
        ->and($due->accessChanges->first()->actor_id)->toBeNull()
        ->and($later->refresh()->tier())->toBe(Tier::Fishroom);
});

it('gives Tank on e-mail verification without writing access data', function () {
    $user = User::factory()->unverified()->create();

    $this->get(URL::temporarySignedRoute('verification.verify', now()->addDay(), ['id' => $user->id, 'hash' => hash('sha256', $user->email)]))
        ->assertRedirect('/logowanie');

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue()->and($user->tier())->toBe(Tier::Tank)
        ->and($user->fishroom_ends_at)->toBeNull()->and(AccessChange::count())->toBe(0);
    app(ActivateAccount::class)->handle($user); // idempotent
});

it('rejects forged, tampered and expired verification links', function () {
    $user = User::factory()->unverified()->create();

    $this->get("/weryfikacja/{$user->id}/".hash('sha256', $user->email))->assertForbidden();
    $this->get(URL::temporarySignedRoute('verification.verify', now()->addDay(), ['id' => $user->id, 'hash' => 'zly']))->assertForbidden();
    $this->get(URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => hash('sha256', $user->email)]))->assertForbidden();
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects a shop application only with a reason, and only once', function () {
    $admin = actor('admin');
    $shop = User::factory()->shop()->create();
    ShopApplication::create(['user_id' => $shop->id, 'company_name' => 'X', 'nip' => '5260250274', 'address' => 'A', 'phone' => '1']);

    expect(fn () => app(RejectShopApplication::class)->handle($admin, $shop->shopApplication, ' '))->toThrow(ValidationException::class);
    app(RejectShopApplication::class)->handle($admin, $shop->shopApplication, 'Brak firmy w rejestrze');
    expect($shop->shopApplication->refresh()->status)->toBe(ShopApplication::REJECTED);
    expect(fn () => app(ApproveShopApplication::class)->handle($admin, $shop->shopApplication))->toThrow(ValidationException::class);
});

it('gates the aquarist area through the one access decision', function () {
    $this->get('/moje-akwarium')->assertRedirect('/logowanie');
    $this->actingAs(actor('tank'))->get('/moje-akwarium')->assertOk();
    $this->actingAs(actor('admin'))->get('/moje-akwarium')->assertOk();
    $this->actingAs(actor('suspended'))->get('/moje-akwarium')->assertForbidden();
});

it('authorizes manual activation and leaves already verified accounts unchanged', function () {
    $user = User::factory()->unverified()->create();
    $admin = User::factory()->admin()->create();
    $activate = app(ActivateAccount::class);

    expect(fn () => $activate->handle($user, $user))->toThrow(AuthorizationException::class);
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();

    $activate->handle($user, $admin, 'Powód');
    $verifiedAt = $user->refresh()->email_verified_at->copy();
    $activate->handle($user, $admin, 'Inny powód');

    expect($user->refresh()->email_verified_at->equalTo($verifiedAt))->toBeTrue()
        ->and($user->accessChanges()->count())->toBe(1)
        ->and($user->accessChanges()->first()->reason)->toBe('Powód');
});

it('opens the guided advisor for Tank by setting, never for a guest, and keeps Fishroom and admin in both cases', function (bool $forTank) {
    shopSetting(SettingKey::GuidedAdvisorForTank, $forTank);

    $tank = decide(actor('tank'), Feature::GuidedAdvisor);
    expect($tank->allowed)->toBe($forTank)
        ->and($tank->reason)->toBe($forTank ? 'tank' : 'fishroom_required')
        ->and($tank->message === null)->toBe($forTank)
        ->and(decide(null, Feature::GuidedAdvisor)->allowed)->toBeFalse()
        ->and(decide(null, Feature::GuidedAdvisor)->reason)->toBe('login_required')
        ->and(decide(fishroomUser(), Feature::GuidedAdvisor)->allowed)->toBeTrue()
        ->and(decide(actor('admin'), Feature::GuidedAdvisor)->allowed)->toBeTrue()
        ->and(decide(actor('suspended'), Feature::GuidedAdvisor)->allowed)->toBeFalse();
})->with([true, false]);
