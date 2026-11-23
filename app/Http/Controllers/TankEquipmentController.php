<?php

namespace App\Http\Controllers;

use App\Domain\Aquarium\Actions\ChangeEquipment;
use App\Domain\Aquarium\Actions\SaveEquipment;
use App\Domain\Aquarium\Data\EquipmentData;
use App\Http\AdvisorReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TankEquipmentController
{
    private const QUANTITY_RULES = ['required', 'integer', 'min:1', 'max:'.EquipmentData::MAX_QUANTITY];

    public function store(Request $request, SaveEquipment $save): RedirectResponse
    {
        $tank = filter_var($request->input('tank'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $returnTo = AdvisorReturn::card($request->input('return_to'));
        $phrase = $request->query('szukaj');
        $tab = $tank === false ? route('aquarium.index') : $this->tabUrl($tank);
        $search = $tank !== false && is_string($phrase) && mb_strlen($phrase) <= 100
            ? $this->tabUrl($tank, $phrase).'#equipment-search'
            : $tab;

        try {
            $data = $request->validate([
                'tank' => ['required', 'integer', 'min:1'],
                'catalog_id' => ['required', 'string', 'max:64'],
                'quantity' => self::QUANTITY_RULES,
            ], attributes: ['tank' => 'zbiornik', 'catalog_id' => 'sprzęt', 'quantity' => 'ilość']);

            $save->handle($request->user(), (int) $data['tank'], new EquipmentData($data['catalog_id'], (int) $data['quantity']));
        } catch (ValidationException $exception) {
            throw $exception->errorBag('equipment')->redirectTo($returnTo ?? $search);
        }

        return redirect()->to($returnTo ?? $tab)
            ->with('equipment_status', 'Dodano sprzęt do zbiornika.')
            ->with('equipment_catalog_id', $data['catalog_id']);
    }

    public function update(Request $request, int $tank, int $equipment, ChangeEquipment $change): RedirectResponse
    {
        try {
            $data = $request->validate(['quantity' => self::QUANTITY_RULES], attributes: ['quantity' => 'ilość']);
            $change->handle($request->user(), $tank, $equipment, (int) $data['quantity']);
        } catch (ValidationException $exception) {
            throw $exception->errorBag('equipment-'.$equipment)->redirectTo($this->tabUrl($tank).'#equipment-'.$equipment);
        }

        return redirect()->to($this->tabUrl($tank))->with('equipment_status', 'Zapisano ilość sprzętu.');
    }

    public function destroy(Request $request, int $tank, int $equipment, ChangeEquipment $change): RedirectResponse
    {
        $change->handle($request->user(), $tank, $equipment, 0);

        return redirect()->to($this->tabUrl($tank))->with('equipment_status', 'Usunięto sprzęt ze zbiornika.');
    }

    private function tabUrl(int $tank, ?string $phrase = null): string
    {
        return route('aquarium.tank', ['tank' => $tank, 'karta' => 'wyposazenie', 'szukaj' => $phrase]);
    }
}
