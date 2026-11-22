<?php

namespace App\Http\Controllers;

use App\Domain\Advisor\Actions\RunTank;
use App\Domain\Advisor\Engine\FishBand;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\TankProfile;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Domain\Advisor\FaunaPool;
use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ranking Tank. Do czasu modułu Akwarium profil pochodzi z formularza; później kontroler wczyta zbiornik po ID
 * i zbuduje ten sam TankProfile.
 */
class TankAdvisorController
{
    public function show(Request $request, RunTank $tank, FaunaPool $pool, Catalog $catalog, ResolveAccess $access): View
    {
        $types = $pool->tankTypes();
        $data = ['values' => $request->query(), 'tankTypes' => $types];
        if (! $request->query->has('size')) {
            return view('advisor.tank', $data);
        }

        $v = $request->validate([
            'size' => ['required', Rule::enum(TankBand::class)],
            'fish' => ['nullable', Rule::enum(FishBand::class)],
            'temperature' => ['nullable', Rule::enum(TemperatureBand::class)],
            'ph' => ['nullable', Rule::enum(PhBand::class)],
            'type' => ['nullable', Rule::in($types)],
            'card' => ['nullable', 'string', 'max:20'],
        ], [
            'size.required' => 'Wybierz wielkość akwarium.',
            'enum' => 'Wybierz jedną z dostępnych opcji.',
            'in' => 'Wybierz jedną z dostępnych opcji.',
        ]);
        $ranking = $tank->handle(new TankProfile(
            TankBand::from($v['size']),
            FishBand::tryFrom($v['fish'] ?? ''),
            TemperatureBand::tryFrom($v['temperature'] ?? ''),
            PhBand::tryFrom($v['ph'] ?? ''),
            $v['type'] ?? null,
        ));

        // Opis ma tylko wyróżniona karta: zwycięzca albo wskazana przez użytkownika karta z grupy równorzędnych.
        $group = array_map(fn ($m) => $m->record->id, $ranking->topGroup());
        $cardId = $ranking->topCount === 1 ? ($group[0] ?? null) : (in_array($v['card'] ?? null, $group, true) ? $v['card'] : null);
        $card = $cardId !== null && $access->handle($request->user(), Feature::TankHitDescription)->allowed
            ? $catalog->find(Area::Fauna, $cardId)
            : null;

        return view('advisor.tank', $data + [
            'ranking' => $ranking,
            'card' => $card,
            'query' => $request->only(['size', 'fish', 'temperature', 'ph', 'type']),
        ]);
    }
}
