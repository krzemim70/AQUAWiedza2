<?php

namespace App\Http\Controllers;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdvisorController
{
    public function __invoke(Request $request, ResolveAccess $access): View|RedirectResponse
    {
        $query = $request->query();
        if (array_key_exists('tank', $query)) {
            return redirect()->route('advisor.tank', array_intersect_key($query, ['tank' => true, 'card' => true]));
        }

        return view('advisor.index', [
            'guidedAccess' => $access->handle($request->user(), Feature::GuidedAdvisor),
            'tankAccess' => $access->handle($request->user(), Feature::TankRanking),
            'quickAccess' => $access->handle($request->user(), Feature::DropSelection),
            'togetherAccess' => $access->handle($request->user(), Feature::PublicTogether),
        ]);
    }
}
