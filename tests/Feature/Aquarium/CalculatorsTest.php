<?php

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
})->with([[['kh' => '-1', 'ph' => '7']], [['kh' => '0', 'ph' => '7']], [['kh' => 'abc', 'ph' => '7']], [['kh' => '4', 'ph' => '15']], [['kh' => '', 'ph' => '7']]]);

it('computes cm of fish per litre with no assessment', function () {
    app(ImportPackage::class)->handle(kbPackage());
    $stock = app(CalculateStocking::class);

    $r = $stock->handle(null, ['volume' => '63', 'species' => ['FN0001', 'FN0060'], 'quantity' => ['10', '6'], 'length' => ['', '']]);
    expect($r->value)->toBe(1.08)->and($r->details['total_cm'])->toBe(68.0)
        ->and($r->steps[0])->toContain('10 × 5 cm = 50 cm')->and($r->steps[0])->toContain('Wielkość = Mała');

    $skipped = $stock->handle(null, ['volume' => '63', 'species' => ['FN0001', 'FN0076'], 'quantity' => ['10', '1'], 'length' => ['', '']]);
    expect($skipped->value)->toBe(0.79)->and(implode(' ', $skipped->notes))->toContain('Pominięto: Akara pomarańczowopłetwa')->toContain('brak danych');

    $own = $stock->handle(null, ['volume' => '63', 'species' => ['FN0001', 'FN0076'], 'quantity' => ['10', '1'], 'length' => ['', '20']]);
    expect($own->value)->toBe(1.11);

    expect($stock->handle(null, ['volume' => '63', 'species' => ['FN0076'], 'quantity' => ['1'], 'length' => ['']])->value)->toBeNull();
    expect(fn () => $stock->handle(null, ['volume' => '63', 'species' => ['XX'], 'quantity' => ['1']]))->toThrow(ValidationException::class);
    expect(fn () => $stock->handle(null, ['volume' => '0', 'species' => ['FN0001'], 'quantity' => ['1']]))->toThrow(ValidationException::class);
});

it('serves calculators to guests without login and renders results accessibly', function () {
    $this->get('/kalkulatory')->assertOk()->assertSee('Cm ryby na litr');
    $this->get('/kalkulatory/co2?kh=4&ph=6.8&oblicz=1')->assertOk()
        ->assertSee('aria-live="polite"', false)->assertSee('19,0 mg/l')->assertSee('Jak policzono');
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
