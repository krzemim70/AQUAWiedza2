<?php

use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Aquarium\Actions\CalculateDosage;
use App\Domain\Aquarium\Actions\CalculateStocking;
use App\Domain\Aquarium\Actions\CalculateTankVolume;
use App\Domain\Aquarium\Actions\EstimateCo2;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Livewire\Aquarium\TankShow;
use Illuminate\Validation\ValidationException;

function volume(array $in, $user = null)
{
    return app(CalculateTankVolume::class)->handle($user, $in);
}

it('computes tank volume from dimensions (golden)', function () {
    expect(volume(['shape' => 'cuboid', 'length' => '60', 'width' => '30', 'height' => '35'])->value)->toBe(63.0)
        ->and(volume(['shape' => 'cylinder', 'diameter' => '30', 'height' => '40'])->value)->toBe(28.27)
        ->and(volume(['length' => '60,5', 'width' => '30', 'height' => '35'])->value)->toBe(63.53);
});

it('shows net volume only from the user-supplied deduction', function () {
    $r = volume(['length' => '60', 'width' => '30', 'height' => '35', 'deduct' => '5']);
    expect($r->details['net'])->toBe(58.0)->and($r->details['save'])->toBe(58.0);
    expect(volume(['length' => '60', 'width' => '30', 'height' => '35'])->details['net'])->toBeNull();
    expect(fn () => volume(['length' => '60', 'width' => '30', 'height' => '35', 'deduct' => '63']))->toThrow(ValidationException::class);
});

it('rounds the calculated volume down to classify its band', function (string $height, string $deduct, int $liters, TankBand $band) {
    $input = ['length' => '100', 'width' => '10', 'height' => $height, 'deduct' => $deduct];
    $result = volume($input);

    expect($result->details['band'])->toBe($band)
        ->and($result->details['band_liters'])->toBe($liters);

    $this->get(route('calculators.volume', $input + ['oblicz' => '1']))->assertOk()
        ->assertSee($band->label())->assertSee("w dół do pełnych litrów ($liters l)");
})->with([
    '39,99 l' => ['39,99', '', 39, TankBand::Nano],
    '100,5 l' => ['100,5', '', 100, TankBand::Small],
    '250,5 l' => ['250,5', '', 250, TankBand::Medium],
    '100,5 l net' => ['105,5', '5', 100, TankBand::Small],
    'before display rounding' => ['39,999', '', 39, TankBand::Nano],
]);

it('estimates CO2 from KH and pH (golden, approximation)', function (string $kh, string $ph, float $mg) {
    $r = app(EstimateCo2::class)->handle(null, ['kh' => $kh, 'ph' => $ph]);
    expect($r->value)->toBe($mg)->and($r->unit)->toBe('mg/l')->and(implode(' ', $r->notes))->toContain('przybliżenie');
})->with([['4', '6.8', 19.0], ['4', '7,2', 7.6], ['6', '7', 18.0]]);

it('scales the label dose to the tank volume', function () {
    $r = app(CalculateDosage::class)->handle(null, ['dose' => '5', 'unit' => 'ml', 'label_volume' => '100', 'volume' => '63']);
    expect($r->value)->toBe(3.15)->and($r->steps[0])->toContain('5 ml × 63 l ÷ 100 l = 3,15 ml')
        ->and($r->notes[0])->toContain('Sprawdź dawkowanie na opakowaniu producenta');
    expect(fn () => app(CalculateDosage::class)->handle(null, ['dose' => '5', 'unit' => 'litry', 'label_volume' => '100', 'volume' => '63']))
        ->toThrow(ValidationException::class);
});

it('rejects negative, zero, non-numeric and out-of-range input', function (array $in) {
    expect(fn () => app(EstimateCo2::class)->handle(null, $in))->toThrow(ValidationException::class);
})->with([[['kh' => '-1', 'ph' => '7']], [['kh' => '0', 'ph' => '7']], [['kh' => 'abc', 'ph' => '7']], [['kh' => '4', 'ph' => '15']]]);

it('does not estimate CO2 with incomplete measurements or other buffers', function (array $input) {
    expect(app(EstimateCo2::class)->handle(null, $input)->value)->toBeNull();

    $this->get(route('calculators.co2', $input + ['oblicz' => '1']))->assertOk()
        ->assertSee('Brak danych do oszacowania')->assertDontSee('mg/l')->assertDontSee('Jak policzono');
})->with([
    'missing KH' => [['kh' => '', 'ph' => '7']],
    'missing pH' => [['kh' => '4']],
    'other buffers' => [['kh' => '4', 'ph' => '6.8', 'other_buffers' => '1']],
]);

it('computes cm of fish per litre with no assessment', function () {
    app(ImportPackage::class)->handle(kbPackage());
    $stock = app(CalculateStocking::class);

    $r = $stock->handle(null, ['volume' => '63', 'species' => ['FN0001', 'FN0060'], 'quantity' => ['10', '6'], 'length' => ['4', '2,5']]);
    expect($r->value)->toBe(0.87)->and($r->details['total_cm'])->toBe(55.0)
        ->and($r->steps[0])->toContain('10 × 4 cm = 40 cm')->and($r->steps[0])->toContain('Gupik Pawie Oczko');

    $incomplete = $stock->handle(null, ['volume' => '63', 'species' => ['FN0001', 'FN0076'], 'quantity' => ['10', '1'], 'length' => ['4', '']]);
    expect($incomplete->value)->toBeNull()->and($incomplete->details)->toBeEmpty()->and($incomplete->steps)->toBeEmpty();

    $own = $stock->handle(null, ['volume' => '63', 'species' => ['FN0001', 'FN0076'], 'quantity' => ['10', '1'], 'length' => ['4', '20']]);
    expect($own->value)->toBe(0.95);

    expect($stock->handle(null, ['volume' => '63', 'species' => ['FN0076'], 'quantity' => ['1'], 'length' => ['']])->value)->toBeNull();
    expect(fn () => $stock->handle(null, ['volume' => '63', 'species' => ['XX'], 'quantity' => ['1']]))->toThrow(ValidationException::class);
    expect(fn () => $stock->handle(null, ['volume' => '0', 'species' => ['FN0001'], 'quantity' => ['1']]))->toThrow(ValidationException::class);
});

it('does not show a total for incomplete stocking inputs', function (array $input) {
    $this->get(route('calculators.stocking', $input + ['oblicz' => '1']))->assertOk()
        ->assertSee('Brak danych')->assertDontSee('Jak policzono')->assertDontSee('cm/l</h2>', false);
})->with([
    'missing length in one of two rows' => [['volume' => '63', 'quantity' => ['2', '1'], 'length' => ['4', '']]],
    'missing quantity' => [['volume' => '63', 'length' => ['4']]],
    'missing volume' => [['quantity' => ['2'], 'length' => ['4']]],
]);

it('serves calculators to guests without login and renders results accessibly', function () {
    $this->get('/kalkulatory')->assertOk()->assertSee('Cm ryby na litr');
    $this->get('/kalkulatory/co2?kh=4&ph=6.8&oblicz=1')->assertOk()
        ->assertSee('aria-live="polite"', false)->assertSee('19,0 mg/l')->assertSee('Jak policzono')
        ->assertSee('słodką wodę')->assertSee('zasadowość pochodzi głównie z wodorowęglanów');
    $this->get('/kalkulatory/co2?kh=-4&ph=6.8&oblicz=1')->assertOk()
        ->assertSee('role="alert"', false)->assertSee('aria-invalid="true"', false)->assertSee('c-kh-error', false)->assertDontSee('mg/l</h2>', false);
    $this->get('/kalkulatory/litraz?length=60&width=30&height=35&oblicz=1')->assertOk()->assertSee('63')->assertSee('Małe (40L - 100L)');
    $this->get('/kalkulatory/dawkowanie?dose=5&unit=ml&label_volume=100&volume=63&oblicz=1')->assertOk()->assertSee('3,15 ml');
    $this->get('/kalkulatory/obsada')->assertOk();
});

it('saves the volume into an own tank only', function () {
    $user = aquarist();
    $tank = tankFor($user);
    $other = tankFor(aquarist());
    $in = ['shape' => 'cuboid', 'length' => '60', 'width' => '30', 'height' => '35', 'height_x' => ''];

    $this->post('/kalkulatory/litraz/zapisz', $in + ['tank' => $tank->id])->assertRedirect('/logowanie');
    $this->actingAs($user)->get(route('calculators.volume', $in + ['oblicz' => '1']))->assertOk()
        ->assertSee('<form method="POST" action="'.route('calculators.volume.save').'">', false);
    $this->actingAs($user)->post('/kalkulatory/litraz/zapisz', $in + ['tank' => $tank->id])->assertRedirect();
    expect($tank->fresh()->volume_liters)->toBe(63);
    $this->actingAs($user)->post('/kalkulatory/litraz/zapisz', $in + ['tank' => $other->id])->assertNotFound();
    expect($other->fresh()->volume_liters)->toBeNull();
});

it('prefills stocking and dosage from an own tank and offers the tank tab', function () {
    app(ImportPackage::class)->handle(kbPackage());
    $user = aquarist();
    $tank = tankFor($user);
    $tank->update(['volume_liters' => 63]);
    $tank->livestock()->create(['area' => 'fauna', 'catalog_id' => 'FN0001', 'quantity' => 4]);

    $this->actingAs($user)->get('/kalkulatory/obsada?tank='.$tank->id)->assertOk()->assertSee('value="63"', false)->assertSee('value="4"', false);
    $this->actingAs($user)->get('/kalkulatory/dawkowanie?tank='.$tank->id)->assertOk()->assertSee('value="63"', false);
    $this->actingAs($user)->get('/kalkulatory/obsada?tank='.tankFor(aquarist())->id)->assertNotFound();
    Livewire\Livewire::actingAs($user)->test(TankShow::class, ['tank' => $tank->id, 'tab' => 'kalkulatory'])->assertSee('Cm ryby na litr');
});
