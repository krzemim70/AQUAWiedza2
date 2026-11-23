<?php

namespace App\Http\Controllers;

use App\Domain\Aquarium\Actions\CalculateDosage;
use App\Domain\Aquarium\Actions\CalculateStocking;
use App\Domain\Aquarium\Actions\CalculateTankVolume;
use App\Domain\Aquarium\Actions\EstimateCo2;
use App\Domain\Aquarium\Actions\ReadTankForCalculators;
use App\Domain\Aquarium\Actions\SaveTankVolume;
use App\Domain\Aquarium\Contracts\OwnTanks;
use App\Domain\Aquarium\Data\CalculatorResult;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

/** Kalkulatory: zwykłe formularze GET (wynik w adresie, działa bez JS); cała logika w akcjach domeny. */
class CalculatorController
{
    public function index(): View
    {
        return view('calculators.index');
    }

    public function volume(Request $request, CalculateTankVolume $calc, OwnTanks $own): View
    {
        $in = $request->query();
        $result = $this->run($request, fn () => $calc->handle($request->user(), $in));

        return view('calculators.volume', [
            'in' => $in, 'result' => $result,
            'tanks' => $request->user() ? $own->names($request->user()) : [],
            'saved' => $request->session()->get('saved'),
        ]);
    }

    public function saveVolume(Request $request, CalculateTankVolume $calc, SaveTankVolume $save): RedirectResponse
    {
        $tank = (int) $request->input('tank');
        $input = $request->except(['tank', '_token']);
        $back = route('calculators.volume', $input + ['oblicz' => 1]);
        try {
            $liters = $calc->handle($request->user(), $input)->details['save'];
            $save->handle($request->user(), $tank, $liters);
        } catch (ValidationException $e) {
            return redirect($back)->withErrors($e->validator);
        }

        return redirect($back)->with('saved', round($liters).' l');
    }

    public function dosage(Request $request, CalculateDosage $calc, OwnTanks $own, ReadTankForCalculators $read): View
    {
        $in = $request->query();
        $tank = $this->tank($request, $read);
        if ($tank !== null && ! isset($in['volume']) && $tank['volume']) {
            $in['volume'] = $tank['volume'];
        }
        $result = $this->run($request, fn () => $calc->handle($request->user(), $in));

        return view('calculators.dosage', [
            'in' => $in, 'result' => $result, 'units' => CalculateDosage::UNITS,
            'tanks' => $request->user() ? $own->names($request->user()) : [], 'tankVolume' => $tank['volume'] ?? null,
        ]);
    }

    public function co2(Request $request, EstimateCo2 $calc): View
    {
        $result = $this->run($request, fn () => $calc->handle($request->user(), $request->query()));

        return view('calculators.co2', ['in' => $request->query(), 'result' => $result]);
    }

    public function stocking(Request $request, CalculateStocking $calc, OwnTanks $own, ReadTankForCalculators $read): View
    {
        $in = $request->query();
        foreach (['species', 'quantity', 'length'] as $column) {
            $in[$column] = is_array($in[$column] ?? null) ? $in[$column] : [];
        }
        $species = $calc->species();
        $tank = $this->tank($request, $read);
        if ($tank !== null && ! $request->has('oblicz')) {
            $fish = array_filter($tank['fish'], fn ($qty, $id) => isset($species[$id]), ARRAY_FILTER_USE_BOTH);
            $in['species'] = array_keys($fish);
            $in['quantity'] = array_values($fish);
            $in['volume'] = $tank['volume'] ?? '';
        }
        $result = $this->run($request, fn () => $calc->handle($request->user(), $in));
        $rows = max(8, min(40, (int) ($in['wiersze'] ?? 0)), count($in['species']) + 1);

        return view('calculators.stocking', [
            'in' => $in, 'result' => $result, 'species' => array_map(fn ($e) => $e->name, $species), 'rows' => $rows,
            'tanks' => $request->user() ? $own->names($request->user()) : [],
        ]);
    }

    /** @return array{volume: int|null, fish: array<string, int>}|null */
    private function tank(Request $request, ReadTankForCalculators $read): ?array
    {
        $id = (int) $request->query('tank');

        return $request->user() && $id > 0 ? $read->handle($request->user(), $id) : null;
    }

    /** Liczy tylko po wysłaniu formularza (`oblicz`); błędy trafiają do widoku (także do komponentów) bez przekierowania. */
    private function run(Request $request, callable $calc): ?CalculatorResult
    {
        if (! $request->has('oblicz')) {
            return null;
        }
        try {
            return $calc();
        } catch (ValidationException $e) {
            view()->share('errors', (new ViewErrorBag)->put('default', $e->validator->errors()));

            return null;
        }
    }
}
