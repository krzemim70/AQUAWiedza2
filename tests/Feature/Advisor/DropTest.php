<?php

use App\Domain\Advisor\DropAllowance;
use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Batch;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Settings\SettingVersion;
use App\Models\User;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

const NANO = ['size' => 'nano'];
const HUGE_FISH_IN_NANO = ['size' => 'nano', 'fish' => 'huge', 'temperature' => 'cold', 'ph' => 'alkaline'];

function setSetting(SettingKey $key, mixed $value): void
{
    SettingVersion::create(['key' => $key->value, 'value' => $value, 'effective_from' => now()->subMinute(), 'reason' => 'test']);
    app(Settings::class)->forget($key);
}

/** Wysyła formularz Drop z ciasteczkiem poprzedniej odpowiedzi (jak przeglądarka). */
function drop(array $input, ?TestResponse $previous = null, ?string $key = null): TestResponse
{
    $test = test();
    $cookie = $previous?->getCookie('drop_allowance', false);
    if ($cookie) {
        $test = $test->withUnencryptedCookie('drop_allowance', $cookie->getValue());
    }

    return $test->post('/dobor', $input + ['request_key' => $key ?? bin2hex(random_bytes(4))]);
}

/** @return list<string> */
function usedKeys(TestResponse $response): array
{
    return DropAllowance::fromCookie($response->getCookie('drop_allowance')->getValue(), time(), 86400)->keys;
}

it('shows the short form to a guest with labelled fields and private headers', function () {
    $response = $this->get('/dobor')->assertOk()
        ->assertSee('Wielkość akwarium (wymagane)')->assertSee('Wielkość ryby')->assertSee('Temperatura')->assertSee('pH')
        ->assertSee('Nano (&lt; 40L)', false);

    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

it('round-trips "Nano (< 40L)" from the form through the server to the result', function () {
    $this->get('/dobor')->assertSee('Nano (&lt; 40L)</option>', false);

    drop(NANO)->assertOk()->assertSee('9 gatunków z wynikiem 100%');
});

it('requires a known tank band and rejects unknown codes', function () {
    drop([])->assertSessionHasErrors('size');
    drop(['size' => 'Nano (< 40L)'])->assertSessionHasErrors('size');
    drop(NANO + ['ph' => 'nope'])->assertSessionHasErrors('ph');
});

it('presents a tie as a group, keeps the free selection and reveals one example card on request', function () {
    $tie = drop(NANO, key: 'tie-key')->assertOk()
        ->assertSee('9 gatunków z wynikiem 100%')->assertSee('Pokaż przykład')->assertSee('Pozostałe wyniki: 10')
        ->assertDontSee('Kardynałek Wietnamski');
    expect($tie->getCookie('drop_allowance', false))->toBeNull();

    $card = drop(NANO + ['reveal' => 1], $tie, 'tie-key')->assertOk()
        ->assertSee('Kardynałek Wietnamski')->assertSee('To jeden z 9 równorzędnych gatunków z wynikiem 100%')
        ->assertSee('Załóż bezpłatne konto');
    expect(usedKeys($card))->toHaveCount(1)
        ->and($card->headers->get('Cache-Control'))->toContain('no-store');

    drop(NANO, $card)->assertSee('Wykorzystałeś bezpłatny dobór')->assertDontSee('Kardynałek Wietnamski');
});

it('reveals a single winner at once, teases the rest and consumes the selection', function () {
    $first = drop(HUGE_FISH_IN_NANO, key: 'k1')->assertOk()
        ->assertSee('Bojownik Syjamski')->assertSee('83%')->assertSee('Pozostałe wyniki: 10')
        ->assertSee('nie ma ryb o wybranej wielkości', false)->assertDontSee('Gupik Endlera');
    $cookie = $first->getCookie('drop_allowance', false);
    expect($cookie->isHttpOnly())->toBeTrue()->and($cookie->getSameSite())->toBe('lax');

    drop(HUGE_FISH_IN_NANO, $first)->assertSee('Wykorzystałeś bezpłatny dobór')->assertDontSee('Bojownik Syjamski');
});

it('does not consume the selection twice for the same request key and profile', function () {
    $first = drop(HUGE_FISH_IN_NANO, key: 'same');
    $again = drop(HUGE_FISH_IN_NANO, $first, 'same')->assertSee('Bojownik Syjamski');
    expect(usedKeys($again))->toHaveCount(1);

    // ten sam klucz z innym profilem to nowe żądanie
    drop(NANO + ['reveal' => 1], $first, 'same')->assertSee('Wykorzystałeś bezpłatny dobór');
});

it('does not consume the selection on an empty result', function () {
    Batch::query()->update(['state' => 'superseded', 'active_slot' => null]);

    $empty = drop(NANO)->assertOk()->assertSee('Nie znaleźliśmy gatunków');

    expect($empty->getCookie('drop_allowance', false))->toBeNull();
});

it('honours the number of free selections from settings', function () {
    setSetting(SettingKey::DropFreeSelections, 2);

    $one = drop(HUGE_FISH_IN_NANO, key: 'a');
    $two = drop(NANO + ['reveal' => 1], $one, 'b')->assertSee('Kardynałek Wietnamski');
    drop(NANO + ['reveal' => 1], $two, 'c')->assertSee('Wykorzystałeś bezpłatny dobór');
});

it('counts selections per IP only when the setting is enabled', function () {
    drop(HUGE_FISH_IN_NANO, key: 'a');
    drop(HUGE_FISH_IN_NANO, key: 'b')->assertSee('Bojownik Syjamski');

    setSetting(SettingKey::DropIpEnabled, true);
    setSetting(SettingKey::DropIpLimit, 1);
    drop(HUGE_FISH_IN_NANO, key: 'c')->assertSee('Bojownik Syjamski');
    drop(HUGE_FISH_IN_NANO, key: 'd')->assertSee('Wykorzystałeś bezpłatny dobór')->assertDontSee('Bojownik Syjamski');
});

it('limits attempts per IP even when the IP counter is off', function () {
    setSetting(SettingKey::DropAttemptRate, ['max' => 2, 'window_seconds' => 3600]);

    drop([]);
    drop([]);
    drop(NANO)->assertStatus(429)->assertSee('Zbyt wiele prób');
});

it('points logged-in users to the Tank view', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/dobor')->assertOk()->assertSee('Masz konto')->assertSee(route('advisor.tank'), false);
    $this->actingAs($user)->post('/dobor', NANO)->assertRedirect(route('advisor.tank'));
    expect(app(ResolveAccess::class)->handle($user, Feature::DropSelection)->allowed)->toBeFalse();
});

it('starts a new window when the cookie is missing, broken or expired', function () {
    $now = 1_000_000;
    $valid = (new DropAllowance('id', $now - 10, ['k']))->toCookie();

    expect(DropAllowance::fromCookie($valid, $now, 100)->keys)->toBe(['k'])
        ->and(DropAllowance::fromCookie($valid, $now, 5)->keys)->toBe([])
        ->and(DropAllowance::fromCookie('{broken', $now, 100)->keys)->toBe([])
        ->and(DropAllowance::fromCookie(null, $now, 100)->startedAt)->toBe($now);
});
