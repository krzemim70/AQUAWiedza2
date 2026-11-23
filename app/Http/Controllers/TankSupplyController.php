<?php

namespace App\Http\Controllers;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Actions\ArchiveSupply;
use App\Domain\Aquarium\Actions\RecordSupplyEvent;
use App\Domain\Aquarium\Actions\SaveSupply;
use App\Domain\Aquarium\Data\SupplyData;
use App\Domain\Aquarium\Data\SupplyEventData;
use App\Domain\Aquarium\Models\SupplyEventKind;
use App\Domain\IdentityAccess\Models\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TankSupplyController
{
    public function store(Request $request, SaveSupply $save): RedirectResponse
    {
        $tank = filter_var($request->input('tank'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $phrase = $request->query('szukaj');
        $tab = $tank === false ? route('aquarium.index') : $this->tabUrl($tank);
        $search = $tank !== false && is_string($phrase) && mb_strlen($phrase) <= 100
            ? $this->tabUrl($tank, $phrase).'#supply-search'
            : $tab;

        try {
            $data = $request->validate([
                'tank' => ['required', 'integer', 'min:1'],
                'catalog_id' => ['required', 'string', 'max:64'],
                'unit' => ['required', 'string', 'max:16'],
                'package_amount' => ['required', 'string', 'max:20'],
                'remaining_amount' => ['nullable', 'string', 'max:20'],
                'dose_amount' => ['required', 'string', 'max:20'],
                'dose_per_liters' => ['nullable', 'string', 'max:20'],
                'interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
                'preferred_shop_id' => ['nullable', 'integer', 'min:1'],
            ], attributes: [
                'tank' => 'zbiornik',
                'catalog_id' => 'preparat lub karma',
                'unit' => 'jednostka',
                'package_amount' => 'wielkość opakowania',
                'remaining_amount' => 'pozostała ilość',
                'dose_amount' => 'dawka / porcja',
                'dose_per_liters' => 'na ile litrów',
                'interval_days' => 'co ile dni',
                'preferred_shop_id' => 'preferowany sklep',
            ]);

            $save->handle($request->user(), (int) $data['tank'], new SupplyData(
                catalogId: (string) $data['catalog_id'],
                unit: (string) $data['unit'],
                packageAmount: (string) $data['package_amount'],
                doseAmount: (string) $data['dose_amount'],
                remainingAmount: isset($data['remaining_amount']) ? (string) $data['remaining_amount'] : null,
                dosePerLiters: isset($data['dose_per_liters']) ? (string) $data['dose_per_liters'] : null,
                intervalDays: isset($data['interval_days']) ? (int) $data['interval_days'] : null,
                preferredShopId: isset($data['preferred_shop_id']) ? (int) $data['preferred_shop_id'] : null,
            ));
        } catch (ValidationException $exception) {
            throw $exception->errorBag('supply')->redirectTo($search);
        }

        return redirect()->to($tab)->with('supply_status', 'Dodano pozycję do zapasów.');
    }

    public function event(Request $request, int $tank, int $supply, RecordSupplyEvent $record, Access $access): RedirectResponse
    {
        $access->tank($request->user(), $tank, Feature::TankSupplies)->supplies()->findOrFail($supply);

        try {
            $data = $request->validate([
                'kind' => ['required', 'in:use,restock'],
                'idempotency_key' => ['required', 'string', 'min:1', 'max:64'],
            ], attributes: [
                'kind' => 'zdarzenie',
                'idempotency_key' => 'klucz operacji',
            ]);

            $kind = SupplyEventKind::from((string) $data['kind']);
            $record->handle($request->user(), $supply, new SupplyEventData($kind, (string) $data['idempotency_key']));
        } catch (ValidationException $exception) {
            throw $exception->errorBag('supply-'.$supply)->redirectTo($this->tabUrl($tank).'#supply-'.$supply);
        }

        return redirect()->to($this->tabUrl($tank))->with(
            'supply_status',
            $kind === SupplyEventKind::Use ? 'Zapisano użycie dawki.' : 'Otwarto nowe opakowanie.',
        );
    }

    public function archive(Request $request, int $tank, int $supply, ArchiveSupply $archive, Access $access): RedirectResponse
    {
        $access->tank($request->user(), $tank, Feature::TankSupplies)->supplies()->findOrFail($supply);
        $archive->handle($request->user(), $supply);

        return redirect()->to($this->tabUrl($tank))->with('supply_status', 'Zarchiwizowano pozycję zapasów.');
    }

    private function tabUrl(int $tank, ?string $phrase = null): string
    {
        return route('aquarium.tank', ['tank' => $tank, 'karta' => 'zapasy', 'szukaj' => $phrase]);
    }
}
