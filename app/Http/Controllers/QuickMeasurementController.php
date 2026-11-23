<?php

namespace App\Http\Controllers;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Actions\RecentMeasurements;
use App\Domain\Aquarium\Actions\SaveMeasurement;
use App\Domain\Aquarium\Data\MeasurementData;
use App\Domain\Aquarium\MeasurementWarnings;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\Aquarium\Models\MeasurementMethod;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\IdentityAccess\Models\Feature;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Szybki wpis pomiaru wody z telefonu, działający bez JavaScript. */
class QuickMeasurementController
{
    public function show(Request $request, Access $access, RecentMeasurements $recent): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $tanks = Tank::ownedBy($user)->orderBy('id')->get();
        if ($tanks->isEmpty()) {
            return view('aquarium.quick-measurement', [
                'tanks' => $tanks,
                'selectedTank' => null,
                'lastValues' => [],
                'labels' => Measurement::LABELS,
                'methods' => MeasurementMethod::cases(),
                'defaultMeasuredAt' => now()->timezone(config('app.display_timezone'))->format('Y-m-d\TH:i'),
                'idempotencyKey' => (string) Str::uuid(),
            ]);
        }

        $requestedTankId = filter_var($request->query('zbiornik'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $selectedTank = $requestedTankId !== false
            ? $access->tank($user, $requestedTankId, Feature::QuickMeasurement)
            : $tanks->first();

        $history = $recent->handle($user, $selectedTank->id, 20, Feature::QuickMeasurement);
        $lastValues = [];
        foreach (array_keys(Measurement::LABELS) as $param) {
            foreach ($history as $item) {
                if ($item->$param !== null) {
                    $lastValues[$param] = [
                        'value' => (string) $item->$param,
                        'measured_at' => $item->measured_at,
                    ];
                    break;
                }
            }
        }

        return view('aquarium.quick-measurement', [
            'tanks' => $tanks,
            'selectedTank' => $selectedTank,
            'lastValues' => $lastValues,
            'labels' => Measurement::LABELS,
            'methods' => MeasurementMethod::cases(),
            'defaultMeasuredAt' => now()->timezone(config('app.display_timezone'))->format('Y-m-d\TH:i'),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, SaveMeasurement $save, MeasurementWarnings $warnings, Access $access): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $access->authorize($user, Feature::QuickMeasurement);

        $validated = $request->validate([
            'tank_id' => ['required', 'integer', 'min:1'],
            'method' => ['nullable', 'string', Rule::in(['', ...array_column(MeasurementMethod::cases(), 'value')])],
            'measured_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'values' => ['nullable', 'array'],
        ], [
            'tank_id.required' => 'Wybierz zbiornik.',
            'method.in' => 'Wybierz poprawną metodę pomiaru.',
            'measured_at.date_format' => 'Podaj poprawną datę i godzinę pomiaru.',
        ]);

        $tankId = (int) $validated['tank_id'];
        $access->tank($user, $tankId, Feature::QuickMeasurement);

        $tz = config('app.display_timezone');
        $measuredAt = isset($validated['measured_at']) && $validated['measured_at'] !== ''
            ? (Carbon::createFromFormat('Y-m-d\TH:i', (string) $validated['measured_at'], $tz) ?: now())
            : now();

        $rawValues = is_array($request->input('values')) ? $request->input('values') : [];
        $values = [];
        foreach (array_keys(Measurement::LABELS) as $param) {
            $raw = $rawValues[$param] ?? null;
            $values[$param] = is_scalar($raw) ? (string) $raw : null;
        }

        $method = isset($validated['method']) && $validated['method'] !== ''
            ? MeasurementMethod::from((string) $validated['method'])
            : null;
        $idempotencyKey = isset($validated['idempotency_key']) && $validated['idempotency_key'] !== ''
            ? (string) $validated['idempotency_key']
            : null;

        try {
            $measurement = $save->handle($user, $tankId, new MeasurementData(
                measuredAt: $measuredAt,
                values: $values,
                method: $method,
                idempotencyKey: $idempotencyKey,
            ));
        } catch (ValidationException $exception) {
            return redirect()
                ->route('aquarium.quick-measurement', ['zbiornik' => $tankId])
                ->withErrors($exception->validator)
                ->withInput();
        }

        return redirect()
            ->to(route('aquarium.tank', ['tank' => $tankId, 'karta' => 'pomiary']))
            ->with('measurement_status', 'Pomiar zapisany.')
            ->with('measurement_warnings', $warnings->for($measurement));
    }
}
