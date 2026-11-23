<?php

use App\Domain\Aquarium\Actions\BuildMeasurementTrends;
use App\Domain\Aquarium\Actions\BuildWaterHints;
use App\Domain\Aquarium\Actions\SaveMeasurement;
use App\Domain\Aquarium\Data\MeasurementData;
use App\Domain\Aquarium\MeasurementWarnings;
use App\Domain\Aquarium\Models\CalibrationStatus;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\Aquarium\Models\MeasurementMethod;
use App\Domain\Aquarium\Models\MeasurementSource;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Livewire\Aquarium\MeasurementHistory;
use App\Livewire\Aquarium\TankMeasurements;
use App\Livewire\Aquarium\TankShow;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

describe('idempotency and device metadata', function () {
    it('saves measurement metadata and deduplicates repeats by idempotency key per tank', function () {
        $user = aquarist();
        $tank = tankFor($user, 'Pierwszy');
        $save = app(SaveMeasurement::class);
        $at = now()->subHour();

        $first = $save->handle($user, $tank->id, new MeasurementData(
            measuredAt: $at,
            values: ['ph' => '7,20', 'ec_us_cm' => '345,5'],
            method: MeasurementMethod::Sensor,
            idempotencyKey: 'dev-1-read-100',
            source: MeasurementSource::Device,
            calibrationStatus: CalibrationStatus::Ok,
            deviceSensorId: 'zigbee-ph-01',
        ));

        $duplicate = $save->handle($user, $tank->id, new MeasurementData(
            measuredAt: $at->copy()->addMinutes(5),
            values: ['ph' => '7,90'],
            method: MeasurementMethod::Sensor,
            idempotencyKey: 'dev-1-read-100',
            source: MeasurementSource::Device,
        ));

        expect($duplicate->id)->toBe($first->id)
            ->and(Measurement::count())->toBe(1)
            ->and($first->fresh()->source)->toBe(MeasurementSource::Device)
            ->and($first->fresh()->method)->toBe(MeasurementMethod::Sensor)
            ->and($first->fresh()->calibration_status)->toBe(CalibrationStatus::Ok)
            ->and($first->fresh()->device_sensor_id)->toBe('zigbee-ph-01')
            ->and($first->fresh()->ec_us_cm)->toBe('345.5');

        $fishUser = aquarist(true);
        $otherTank = tankFor($fishUser, 'Drugi');
        $otherMeasurement = $save->handle($fishUser, $otherTank->id, new MeasurementData(
            measuredAt: $at,
            values: ['ph' => '7,10'],
            idempotencyKey: 'dev-1-read-100',
        ));

        expect($otherMeasurement->id)->not->toBe($first->id)->and(Measurement::count())->toBe(2);
    });

    it('warns on uncalibrated readings and automatically blocks water-hint product recommendations', function (CalibrationStatus $status) {
        app(ImportPackage::class)->handle(kbPackage());
        $user = aquarist();
        $tank = tankFor($user);
        Livestock::create(['tank_id' => $tank->id, 'area' => Area::Fauna, 'catalog_id' => 'FN0029', 'quantity' => 1]);

        $save = app(SaveMeasurement::class);
        $m = $save->handle($user, $tank->id, new MeasurementData(
            measuredAt: now()->subMinutes(10),
            values: ['ph' => '7.9'],
            method: MeasurementMethod::Sensor,
            source: MeasurementSource::Device,
            calibrationStatus: $status,
        ));

        $warnings = app(MeasurementWarnings::class)->for($m);
        $report = app(BuildWaterHints::class)->handle($user, $tank->id);

        expect($warnings)->not->toBeEmpty()
            ->and($report)->not->toBeNull()
            ->and($report->product)->toBeNull()
            ->and($report->productBlockReason)->toBe('odczyt pH ma ostrzeżenie');
    })->with([CalibrationStatus::Due, CalibrationStatus::Uncalibrated]);
});

describe('access and quick measurement', function () {
    it('allows Tank to use quick measurement without JS, denies trends, and grants Fishroom both', function () {
        $tankUser = aquarist();
        $tank = tankFor($tankUser, 'Główne');
        measure($tankUser, $tank->id, ['ph' => '7.10'], now()->subDays(5));
        measure($tankUser, $tank->id, ['ph' => '7.20'], now()->subDays(3));
        measure($tankUser, $tank->id, ['ph' => '7.30'], now()->subDay());

        $this->get('/moje-akwarium/szybki-pomiar')->assertRedirect('/logowanie');

        $this->actingAs($tankUser)->get(route('aquarium.quick-measurement'))
            ->assertOk()
            ->assertSee('Szybki pomiar')
            ->assertSee('Ostatnio: 7,30')
            ->assertSee('method="POST"', false)
            ->assertDontSee('wire:submit', false);

        $this->actingAs($tankUser)->post(route('aquarium.quick-measurement.store'), [
            'tank_id' => $tank->id,
            'method' => MeasurementMethod::Drop->value,
            'values' => ['ph' => '7,40', 'ec_us_cm' => '290'],
            'idempotency_key' => 'qm-test-1',
        ])->assertRedirect(route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'pomiary']));

        expect(Measurement::count())->toBe(4)
            ->and(Measurement::latest('id')->first()->method)->toBe(MeasurementMethod::Drop);

        expect(fn () => app(BuildMeasurementTrends::class)->handle($tankUser, $tank->id))
            ->toThrow(AuthorizationException::class);

        Livewire::actingAs($tankUser)->test(TankShow::class, ['tank' => $tank->id, 'tab' => 'historia'])
            ->assertSee('dostępna w planie Fishroom')
            ->assertDontSee('Trendy pomiarów');

        $fishUser = aquarist(true);
        $fishTank = tankFor($fishUser, 'Roślinne');
        measure($fishUser, $fishTank->id, ['ph' => '6.8'], now()->subDays(4));
        measure($fishUser, $fishTank->id, ['ph' => '6.9'], now()->subDays(2));
        measure($fishUser, $fishTank->id, ['ph' => '7.0'], now()->subDay());

        $this->actingAs($fishUser)->get(route('aquarium.quick-measurement', ['zbiornik' => $fishTank->id]))
            ->assertOk()
            ->assertSee('Ostatnio: 7,00');

        Livewire::actingAs($fishUser)->test(MeasurementHistory::class, ['tankId' => $fishTank->id])
            ->assertOk()
            ->assertSee('Trendy pomiarów')
            ->assertSee('Historia zmian, nie prognoza.');
    });

    it('refuses access to another user tank on quick measurement and renders a 320px-safe responsive layout', function () {
        $owner = aquarist(true);
        $tankA = tankFor($owner, 'Akwarium A');
        $tankB = tankFor($owner, 'Akwarium B');
        $foreign = tankFor(aquarist(), 'Obce');

        measure($owner, $tankA->id, ['ph' => '7.0'], now()->subDays(3));
        measure($owner, $tankA->id, ['ph' => '7.1'], now()->subDays(2));
        measure($owner, $tankA->id, ['ph' => '7.2'], now()->subDay());

        $this->actingAs($owner)->get(route('aquarium.quick-measurement', ['zbiornik' => $foreign->id]))->assertNotFound();
        $this->actingAs($owner)->post(route('aquarium.quick-measurement.store'), [
            'tank_id' => $foreign->id,
            'values' => ['ph' => '7.0'],
        ])->assertNotFound();

        $quickHtml = $this->actingAs($owner)->get(route('aquarium.quick-measurement', ['zbiornik' => $tankB->id]))
            ->assertOk()
            ->assertSee('Akwarium A')
            ->assertSee('Akwarium B')
            ->getContent();

        expect($quickHtml)->toContain('grid-cols-1')
            ->toContain('min-w-0')
            ->toContain('min-h-12')
            ->toContain('min-h-11')
            ->not->toMatch('/style="[^"]*width:\s*\d{4,}px/i');

        $historyView = Livewire::actingAs($owner)->test(MeasurementHistory::class, ['tankId' => $tankA->id]);
        $historyHtml = $historyView->html();

        expect($historyHtml)->toContain('viewBox="0 0 600 220"')
            ->toContain('class="block h-auto w-full max-w-full')
            ->toContain('table-scroll')
            ->toContain('min-w-0');
    });
});

describe('trends view, windows, stock range background and recent summary', function () {
    it('shows SVG trends with consistent stock range background and hides it when catalogue data conflicts', function () {
        app(ImportPackage::class)->handle(kbPackage());
        $user = aquarist(true);
        $tank = tankFor($user);
        Livestock::create(['tank_id' => $tank->id, 'area' => Area::Fauna, 'catalog_id' => 'FN0029', 'quantity' => 1]);

        $save = app(SaveMeasurement::class);
        $save->handle($user, $tank->id, new MeasurementData(now()->subDays(6), ['ph' => '7.0', 'temp_c' => '24.0'], method: MeasurementMethod::Strip));
        $save->handle($user, $tank->id, new MeasurementData(now()->subDays(3), ['ph' => '7.2', 'temp_c' => '24.0'], method: MeasurementMethod::Drop));
        $save->handle($user, $tank->id, new MeasurementData(now()->subDay(), ['ph' => '7.4', 'temp_c' => '24.0'], method: MeasurementMethod::Drop));

        Livewire::actingAs($user)->test(MeasurementHistory::class, ['tankId' => $tank->id])
            ->assertSee('Historia zmian, nie prognoza.')
            ->assertSee('różnych metod pomiaru')
            ->assertSee('wspólny zakres katalogowy obsady')
            ->assertSeeHtml('<rect ')
            ->assertSeeHtml('<polyline ');

        // FN0001 ma rozbieżne pH w tabeli vs opisie (6,8–7,5 vs 7,0–8,5) -> brak tła wspólnego zakresu obsady dla pH
        $conflictTank = tankFor($user, 'Konflikt');
        Livestock::create(['tank_id' => $conflictTank->id, 'area' => Area::Fauna, 'catalog_id' => 'FN0001', 'quantity' => 1]);
        $save->handle($user, $conflictTank->id, new MeasurementData(now()->subDays(5), ['ph' => '7.1']));
        $save->handle($user, $conflictTank->id, new MeasurementData(now()->subDays(3), ['ph' => '7.2']));
        $save->handle($user, $conflictTank->id, new MeasurementData(now()->subDay(), ['ph' => '7.3']));

        Livewire::actingAs($user)->test(MeasurementHistory::class, ['tankId' => $conflictTank->id])
            ->assertSee('Brak tła zakresu obsady')
            ->assertDontSeeHtml('<rect ');
    });

    it('respects configured min_points and time windows, and shows last 3 readings in measurements tab', function () {
        $user = aquarist(true);
        $tank = tankFor($user);

        measure($user, $tank->id, ['ph' => '6.50'], now()->subDays(25));
        measure($user, $tank->id, ['ph' => '7.10'], now()->subDays(12));
        measure($user, $tank->id, ['ph' => '7.20'], now()->subDays(4));
        measure($user, $tank->id, ['ph' => '7.35'], now()->subDay());

        Livewire::actingAs($user)->test(TankMeasurements::class, ['tankId' => $tank->id])
            ->assertSee('Ostatnie 3 odczyty')
            ->assertSee('7,35')
            ->assertSee('7,20')
            ->assertSee('7,10')
            ->assertDontSee('6,50');

        $history = Livewire::actingAs($user)->test(MeasurementHistory::class, ['tankId' => $tank->id])
            ->assertSee('Odczyty: pH (ostatnie 30 dni)')
            ->assertSee('+0,85')
            ->set('windowDays', 7)
            ->assertSee('Za mało pomiarów do porównania');

        shopSetting(SettingKey::MeasurementTrends, ['min_points' => 5, 'windows_days' => [7, 30, 90]]);
        $history->set('windowDays', 30)->assertSee('Za mało pomiarów do porównania');
    });
});
