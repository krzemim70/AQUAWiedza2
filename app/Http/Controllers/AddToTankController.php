<?php

namespace App\Http\Controllers;

use App\Domain\Aquarium\Actions\AddToLivestock;
use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\KnowledgeBase\Models\Area;
use App\Http\AdvisorReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AddToTankController
{
    public function __invoke(Request $request, AddToLivestock $add): RedirectResponse
    {
        $returnTo = AdvisorReturn::card($request->input('return_to'));

        try {
            $data = $request->validate([
                'tank' => ['required', 'integer'],
                'area' => ['required', Rule::enum(Area::class)->only([Area::Fauna, Area::Flora])],
                'species' => ['required', 'string', 'max:64'],
                'quantity' => ['required', 'integer', 'min:1', 'max:'.LivestockData::MAX_QUANTITY],
                'request_key' => ['required', 'uuid'],
            ], attributes: ['tank' => 'zbiornik', 'area' => 'obszar', 'species' => 'gatunek', 'quantity' => 'ilość', 'request_key' => 'klucz formularza']);

            $add->handle(
                $request->user(),
                (int) $data['tank'],
                new LivestockData(Area::from($data['area']), $data['species'], (int) $data['quantity']),
                $data['request_key'],
            );
        } catch (ValidationException $exception) {
            throw $exception->errorBag('livestock')->redirectTo($returnTo ?? route('advisor.tank'));
        }

        return redirect()->to($returnTo ?? route('catalog.show', [$data['area'], $data['species']]))
            ->with('livestock_status', [$data['area'], $data['species']]);
    }
}
