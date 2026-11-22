<?php

namespace App\Http\Controllers;

use App\Domain\Advisor\Actions\RunTank;
use App\Domain\Advisor\Engine\TankProfile;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Contracts\PublicOffers;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Ranking Tank dla własnego zbiornika użytkownika (`?tank=ID`, domyślnie pierwszy). Obcy zbiornik to 404. */
class TankAdvisorController
{
    public function show(Request $request, RunTank $run, Catalog $catalog, ResolveAccess $access, PublicOffers $publicOffers): View
    {
        $user = $request->user();
        $tanks = Tank::ownedBy($user)->orderBy('id')->get();
        $tank = $request->query->has('tank')
            ? Tank::ownedBy($user)->findOrFail($request->integer('tank'))
            : $tanks->first();

        if ($tank === null || $tank->size_band === null) {
            return view('advisor.tank', ['tanks' => $tanks, 'tank' => $tank]);
        }

        $ranking = $run->handle(new TankProfile($tank->size_band, $tank->fish_size_band, $tank->temp_band, $tank->ph_band, $tank->type->value));

        // Opis ma tylko wyróżniona karta: zwycięzca albo wskazana przez użytkownika karta z grupy równorzędnych.
        $group = array_map(fn ($m) => $m->record->id, $ranking->topGroup());
        $wanted = $request->query('card');
        $cardId = $ranking->topCount === 1 ? ($group[0] ?? null) : (in_array($wanted, $group, true) ? $wanted : null);
        $card = $cardId !== null && $access->handle($user, Feature::TankHitDescription)->allowed
            ? $catalog->find(Area::Fauna, $cardId)
            : null;

        // oferty sklepów tylko dla jednoznacznego zwycięzcy
        $winner = $ranking->topCount === 1 ? ($group[0] ?? null) : null;
        $offers = $winner === null ? [] : ($publicOffers->forRecords(Area::Fauna, [$winner])[$winner] ?? []);

        return view('advisor.tank', ['tanks' => $tanks, 'tank' => $tank, 'ranking' => $ranking, 'card' => $card, 'offers' => $offers]);
    }
}
