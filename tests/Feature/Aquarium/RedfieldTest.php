<?php

use App\Domain\Aquarium\Actions\CalculateRedfield;
use App\Domain\Aquarium\Data\RedfieldResult;
use App\Domain\Aquarium\Models\RedfieldZone;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Settings\SettingVersion;
use App\Livewire\Aquarium\RedfieldCalculator;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function redfield(string $no3, string $po4, ?User $user = null): RedfieldResult
{
    return app(CalculateRedfield::class)->handle($user ?? aquarist(), $no3, $po4);
}

it('computes the atomic N:P and the mass ratio on the server', function () {
    $r = redfield('10', '1');

    expect($r->molarRatio)->toBe(15.32)->and($r->massRatio)->toBe(10.0)->and($r->zone)->toBe(RedfieldZone::Balanced)
        ->and($r->zone->signal())->toBe('green')->and($r->message)->not->toBe('');
    expect(redfield('10,5', '0,5')->massRatio)->toBe(21.0);
});

it('assigns zones from the configured edges before rounding', function (string $no3, RedfieldZone $zone) {
    expect(redfield($no3, '1')->zone)->toBe($zone);
})->with([
    'zero nitrate' => ['0', RedfieldZone::StrongPhosphateExcess],
    'below 5' => ['2', RedfieldZone::StrongPhosphateExcess],
    'between 5 and 10' => ['4', RedfieldZone::PhosphateExcess],
    'balanced' => ['10', RedfieldZone::Balanced],
    'between 20 and 30' => ['15', RedfieldZone::NitrateExcess],
    'above 30' => ['25', RedfieldZone::StrongNitrateExcess],
]);

it('treats both balanced edges as inclusive and decides before rounding', function (string $edge) {
    // granica równa dokładnie wynikowi dla 10 i 1 mg/l
    $ratio = (10 / 62.004) / (1 / 94.97);
    SettingVersion::create(['key' => SettingKey::RedfieldZoneEdges->value, 'value' => $edge === 'upper' ? [5, 10, $ratio, 30] : [5, $ratio, 20, 30], 'effective_from' => now()->subMinute(), 'reason' => 'test']);
    app(Settings::class)->forget(SettingKey::RedfieldZoneEdges);

    expect(redfield('10', '1')->zone)->toBe(RedfieldZone::Balanced);
})->with([
    'upper edge' => ['upper'],
    'lower edge' => ['lower'],
]);

it('rounds the shown ratio but not the zone decision', function () {
    $r = redfield('6.5262', '1');

    expect($r->molarRatio)->toBe(10.0)->and($r->zone)->toBe(RedfieldZone::PhosphateExcess);
});

it('validates input: PO4 must be positive, values finite, non-negative and within the maximum', function (string $no3, string $po4, string $field) {
    try {
        redfield($no3, $po4);
        $this->fail('expected validation error');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey($field);
    }
})->with([
    'po4 zero' => ['10', '0', 'po4'],
    'po4 zero decimal' => ['10', '0,0', 'po4'],
    'po4 negative' => ['10', '-1', 'po4'],
    'no3 negative' => ['-1', '1', 'no3'],
    'no3 text' => ['abc', '1', 'no3'],
    'no3 infinite' => ['INF', '1', 'no3'],
    'no3 huge exponent' => ['1e999', '1', 'no3'],
    'po4 over max' => ['10', '100,1', 'po4'],
    'no3 over max' => ['100.5', '1', 'no3'],
    'empty' => ['', '1', 'no3'],
]);

it('accepts the maximum allowed value', function () {
    expect(redfield('100', '100')->molarRatio)->toBe(1.53);
});

it('is open to Tank and Fishroom but not to suspended accounts', function () {
    expect(redfield('10', '1', aquarist())->zone)->toBe(RedfieldZone::Balanced)
        ->and(redfield('10', '1', aquarist(true))->zone)->toBe(RedfieldZone::Balanced)
        ->and(fn () => redfield('10', '1', User::factory()->create(['suspended_at' => now()])))->toThrow(AuthorizationException::class);

    $this->get('/moje-akwarium/redfield')->assertRedirect('/logowanie');
    $this->actingAs(aquarist())->get('/moje-akwarium/redfield')->assertOk()->assertSee('Kalkulator Redfielda');
});

it('shows the result in the form and the validation message', function () {
    Livewire::actingAs(aquarist())->test(RedfieldCalculator::class)
        ->set('no3', '10')->set('po4', '1')->call('calculate')->assertSee('Zbilansowany')->assertSee('15,32')
        ->set('po4', '0')->call('calculate')->assertHasErrors('po4')->assertSet('result', null);
});

it('prefills from the latest measurement of an own tank only', function () {
    $user = aquarist();
    $tank = tankFor($user);
    $empty = tankFor(aquarist(true), 'Cudzy');
    measure($user, $tank->id, ['no3' => '18', 'po4' => '1.5']);

    Livewire::actingAs($user)->test(RedfieldCalculator::class)
        ->set('tank', (string) $tank->id)->call('prefill')->assertSet('no3', '18.0')->assertSet('po4', '1.500')
        ->call('calculate')->assertSet('result.zone', 'Zbilansowany');

    Livewire::actingAs($user)->test(RedfieldCalculator::class)->set('tank', (string) $empty->id)->call('prefill')->assertNotFound();
});

it('asks for manual input when the latest measurement lacks NO3 or PO4', function () {
    $user = aquarist();
    $tank = tankFor($user);
    measure($user, $tank->id, ['ph' => '7']);

    Livewire::actingAs($user)->test(RedfieldCalculator::class)
        ->set('tank', (string) $tank->id)->call('prefill')->assertSet('no3', '')->assertSee('Wpisz wartości ręcznie');
});
