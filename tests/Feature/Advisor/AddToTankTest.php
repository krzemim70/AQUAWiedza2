<?php

use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use Illuminate\Support\Str;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

function livestockAddition(int $tankId, array $overrides = []): array
{
    return $overrides + [
        'tank' => $tankId,
        'area' => 'fauna',
        'species' => 'FN0013',
        'quantity' => 2,
        'request_key' => (string) Str::uuid(),
        'return_to' => '/katalog/fauna/FN0013?wroc=%2Fdoradca',
    ];
}

it('offers only owned tanks and adds the selected quantity with a status on the same page', function () {
    $user = aquarist(fishroom: true);
    $first = tankFor($user, 'Pierwszy własny');
    $second = tankFor($user, 'Drugi własny');
    tankFor(aquarist(), 'Cudzy zbiornik');
    $payload = livestockAddition($second->id);

    $this->actingAs($user)->get($payload['return_to'])->assertOk()
        ->assertSee('select name="tank"', false)->assertSee($first->name)->assertSee($second->name)
        ->assertDontSee('Cudzy zbiornik')->assertSee('name="request_key"', false);
    $this->post(route('advisor.add-to-tank'), $payload)->assertRedirect($payload['return_to']);

    expect(Livestock::where('tank_id', $second->id)->sole()->quantity)->toBe(2);
    $this->get($payload['return_to'])->assertOk()->assertSee('role="status"', false)->assertSee('Dodano do akwarium.');
});

it('rejects a foreign tank and redirects a guest to login', function () {
    $tank = tankFor(aquarist());
    $payload = livestockAddition($tank->id);

    $this->post(route('advisor.add-to-tank'), $payload)->assertRedirect(route('login'));
    $this->actingAs(aquarist())->post(route('advisor.add-to-tank'), $payload)->assertNotFound();
    expect(Livestock::count())->toBe(0);
});

it('increments once per request key and adds again with a different key', function () {
    $user = aquarist();
    $tank = tankFor($user);
    $payload = livestockAddition($tank->id);
    $url = route('advisor.add-to-tank');

    $this->actingAs($user)->post($url, $payload)->assertRedirect($payload['return_to']);
    $this->post($url, $payload)->assertRedirect($payload['return_to']);
    expect(Livestock::sole()->quantity)->toBe(2);

    $this->post($url, livestockAddition($tank->id))->assertRedirect($payload['return_to']);
    expect(Livestock::sole()->quantity)->toBe(4);
});

it('validates catalog records and rolls back the request key when the increment fails', function () {
    $user = aquarist();
    $tank = tankFor($user);
    $url = route('advisor.add-to-tank');
    $payload = livestockAddition($tank->id);

    $this->actingAs($user)->post($url, ['species' => 'FN9999'] + $payload)->assertSessionHasErrorsIn('livestock', ['species']);
    $this->post($url, ['quantity' => LivestockData::MAX_QUANTITY] + $payload)->assertRedirect();
    expect(Livestock::sole()->quantity)->toBe(LivestockData::MAX_QUANTITY);
    $this->post($url, livestockAddition($tank->id, ['quantity' => 1]))->assertSessionHasErrorsIn('livestock', ['quantity']);
    expect(Livestock::sole()->quantity)->toBe(LivestockData::MAX_QUANTITY);
});

it('does not redirect a livestock request to another origin', function () {
    $user = aquarist();
    $tank = tankFor($user);
    $this->actingAs($user)->post(route('advisor.add-to-tank'), livestockAddition($tank->id, ['return_to' => '//example.com']))
        ->assertRedirect(route('catalog.show', ['fauna', 'FN0013']));
});
