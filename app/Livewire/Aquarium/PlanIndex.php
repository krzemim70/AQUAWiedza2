<?php

namespace App\Livewire\Aquarium;

use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Aquarium\Actions\CreatePlan;
use App\Domain\Aquarium\Actions\DeletePlan;
use App\Domain\Aquarium\Data\PlanData;
use App\Domain\Aquarium\Models\TankPlan;
use App\Domain\Aquarium\Models\TankType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Lista planów nowego akwarium i formularz nowego planu; limit i walidacja w akcjach domeny. */
#[Layout('layouts.aquarist')]
#[Title('Plan nowego akwarium — Aikwarium')]
class PlanIndex extends Component
{
    public string $name = '';

    public string $type = '';

    public string $size_band = '';

    public string $liters = '';

    public function create(CreatePlan $create): void
    {
        try {
            $plan = $create->handle(auth()->user(), new PlanData($this->name, $this->type, $this->size_band, $this->liters));
        } catch (AuthorizationException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }
        $this->redirectRoute('plans.show', ['plan' => $plan->id], navigate: true);
    }

    public function delete(DeletePlan $delete, int $planId): void
    {
        $delete->handle(auth()->user(), $planId);
    }

    public function render(): View
    {
        return view('livewire.aquarium.plan-index', [
            'plans' => TankPlan::ownedBy(auth()->user())->withCount('items')->orderBy('id')->get(),
            'types' => TankType::cases(),
            'bands' => TankBand::cases(),
        ]);
    }
}
