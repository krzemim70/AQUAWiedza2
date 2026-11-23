<?php

namespace App\Http\Controllers;

use App\Domain\Aquarium\Actions\AddToLivestock;
use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\Aquarium\LivestockFitWarning;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Http\AdvisorReturn;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AddToTankController
{
    public function __invoke(Request $request, AddToLivestock $add, Catalog $catalog): RedirectResponse|View
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

            try {
                $add->handle(
                    $request->user(),
                    (int) $data['tank'],
                    new LivestockData(Area::from($data['area']), $data['species'], (int) $data['quantity']),
                    $data['request_key'],
                    $request->boolean('acknowledge'),
                );
            } catch (LivestockFitWarning $warning) {
                $area = Area::from($data['area']);

                return view('aquarium.confirm-add', [
                    'report' => $warning->report,
                    'name' => $catalog->find($area, $data['species'])->name,
                    'action' => route('advisor.add-to-tank'),
                    'fields' => $data + ['return_to' => $returnTo],
                    'cancelUrl' => $returnTo ?? route('catalog.show', [$data['area'], $data['species']]),
                ]);
            }
        } catch (ValidationException $exception) {
            throw $exception->errorBag('livestock')->redirectTo($returnTo ?? route('advisor.tank'));
        }

        return redirect()->to($returnTo ?? route('catalog.show', [$data['area'], $data['species']]))
            ->with('livestock_status', [$data['area'], $data['species']]);
    }
}
