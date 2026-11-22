<?php

use App\Domain\Aquarium\Actions\LatestMeasurement;
use App\Domain\Aquarium\Actions\ListMeasurements;
use App\Domain\Aquarium\MeasurementWarnings;
use App\Domain\Aquarium\Models\Measurement;
use App\Livewire\Aquarium\MeasurementHistory;
use App\Livewire\Aquarium\TankMeasurements;
use App\Livewire\Aquarium\TankShow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

describe('saving', function () {
    it('keeps precision, accepts a decimal comma and treats blanks as no reading', function () {
        $user = aquarist();
        $tank = tankFor($user);

        $m = measure($user, $tank->id, ['ph' => '7,25', 'temp_c' => '24.5', 'po4' => '0.035', 'no3' => '12.3', 'kh' => '', 'gh' => null]);
        $m = $m->fresh();

        expect($m->ph)->toBe('7.25')->and($m->temp_c)->toBe('24.5')->and($m->po4)->toBe('0.035')->and($m->no3)->toBe('12.3')
            ->and($m->kh)->toBeNull()->and($m->gh)->toBeNull()->and($m->no2)->toBeNull();
    });

    it('requires at least one reading and rejects invalid ones', function (array $values, string $errorKey) {
        $user = aquarist();
        $tank = tankFor($user);

        try {
            measure($user, $tank->id, $values);
            $this->fail('expected validation error');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($errorKey);
        }
        expect(Measurement::count())->toBe(0);
    })->with([
        'nothing' => [[], 'values'],
        'blanks only' => [['ph' => ' ', 'kh' => ''], 'values'],
        'text' => [['ph' => 'abc'], 'ph'],
        'negative' => [['kh' => '-1'], 'kh'],
        'too many decimals' => [['ph' => '7.123'], 'ph'],
        'po4 too precise' => [['po4' => '0.0001'], 'po4'],
        'over column' => [['temp_c' => '100'], 'temp_c'],
    ]);

    it('warns outside configured ranges without rejecting the save', function () {
        $user = aquarist();
        $tank = tankFor($user);

        $bad = measure($user, $tank->id, ['ph' => '15', 'temp_c' => '45', 'po4' => '0.5']);
        $ok = measure($user, $tank->id, ['ph' => '7.2', 'temp_c' => '24']);
        $warnings = app(MeasurementWarnings::class);

        expect(Measurement::count())->toBe(2)
            ->and($warnings->for($bad))->toHaveCount(2)->and($warnings->for($bad)[0])->toContain('pH')
            ->and($warnings->for($ok))->toBe([]);
    });

    it('shows the warning and the saved reading in the form', function () {
        $user = aquarist();
        $tank = tankFor($user);

        Livewire::actingAs($user)->test(TankMeasurements::class, ['tankId' => $tank->id])
            ->set('ph', '15')->call('save')->assertHasNoErrors()->assertSee('poza typowym zakresem')->assertSee('Pomiar zapisany')
            ->call('save')->assertHasErrors('values')->assertSee('co najmniej jeden parametr');
    });

    it('validates the timestamp in the form', function () {
        $user = aquarist();
        $tank = tankFor($user);

        Livewire::actingAs($user)->test(TankMeasurements::class, ['tankId' => $tank->id])
            ->set('measured_at', 'wczoraj')->set('ph', '7')->call('save')->assertHasErrors('measured_at');
    });
});

describe('latest and history', function () {
    it('picks the latest by time and breaks ties by id', function () {
        $user = aquarist();
        $tank = tankFor($user);
        measure($user, $tank->id, ['ph' => '7.0'], now()->subDays(2));
        $a = measure($user, $tank->id, ['ph' => '7.1'], now()->subDay());
        $b = measure($user, $tank->id, ['ph' => '7.2'], now()->subDay());
        measure($user, $tank->id, ['ph' => '6.0'], now()->subDays(5));

        expect(app(LatestMeasurement::class)->handle($user, $tank->id)->id)->toBe($b->id)->and($b->id)->toBeGreaterThan($a->id);
    });

    it('lets Tank read the latest and add, but refuses the history', function () {
        $user = aquarist();
        $tank = tankFor($user);
        measure($user, $tank->id, ['ph' => '7.1']);

        expect(app(LatestMeasurement::class)->handle($user, $tank->id)->ph)->toBe('7.10')
            ->and(fn () => app(ListMeasurements::class)->handle($user, $tank->id))->toThrow(AuthorizationException::class);
        Livewire::actingAs($user)->test(MeasurementHistory::class, ['tankId' => $tank->id])->assertForbidden();
    });

    it('gives Fishroom the history from the newest', function () {
        $user = aquarist(true);
        $tank = tankFor($user);
        measure($user, $tank->id, ['ph' => '7.0'], now()->subDays(2));
        measure($user, $tank->id, ['ph' => '7.3'], now()->subDay());

        expect(app(ListMeasurements::class)->handle($user, $tank->id)->pluck('ph')->all())->toBe(['7.30', '7.00']);
        Livewire::actingAs($user)->test(MeasurementHistory::class, ['tankId' => $tank->id])->assertSee('7.30')->assertSee('7.00');
    });

    it('shows locked tabs to Tank as an invitation without their content', function () {
        $user = aquarist();
        $tank = tankFor($user);
        measure($user, $tank->id, ['ph' => '7.77']);

        $page = Livewire::actingAs($user)->test(TankShow::class, ['tank' => $tank->id])
            ->assertSee('Historia parametrów')->assertSee('Dziennik')->assertSee('Przypomnienia')->assertSee('Fishroom');

        foreach (['historia', 'dziennik', 'przypomnienia'] as $tab) {
            $page->set('tab', $tab)->assertSee('dostępna w planie Fishroom')->assertDontSee('7.77')->assertDontSee('Dodaj wpis')->assertDontSee('Dodaj przypomnienie');
        }
        $page->set('tab', 'pomiary')->assertSee('7.77');
    });

    it('opens the Fishroom tabs with their content', function () {
        $user = aquarist(true);
        $tank = tankFor($user);

        $page = Livewire::actingAs($user)->test(TankShow::class, ['tank' => $tank->id]);
        $page->set('tab', 'dziennik')->assertSee('Dodaj wpis')->assertDontSee('dostępna w planie Fishroom');
        $page->set('tab', 'przypomnienia')->assertSee('Dodaj przypomnienie');
        $page->set('tab', 'historia')->assertSee('Brak zapisanych pomiarów');
    });
});
