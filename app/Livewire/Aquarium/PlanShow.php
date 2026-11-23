<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Actions\BuildPlanReport;
use App\Domain\Aquarium\Actions\BuildShoppingList;
use App\Domain\Aquarium\Actions\ChangePlanItem;
use App\Domain\Aquarium\Actions\ConvertPlanToTank;
use App\Domain\Aquarium\Models\TankPlan;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Plan nowego akwarium: pozycje, porównanie katalogowe, sprzęt do przeglądania, lista zakupów i zamiana w zbiornik. */
#[Layout('layouts.aquarist')]
#[Title('Plan nowego akwarium — Aikwarium')]
class PlanShow extends Component
{
    #[Locked]
    public int $planId;

    public string $phrase = '';

    public string $quantity = '1';

    #[Url(as: 'kategoria')]
    public string $category = '';

    public function mount(int $plan): void
    {
        $this->planId = $plan;
        app(Access::class)->plan(auth()->user(), $plan);
    }

    public function add(ChangePlanItem $change, string $area, string $catalogId): void
    {
        $area = Area::from($area);
        $current = app(Access::class)->plan(auth()->user(), $this->planId)->items()->where(['area' => $area->value, 'catalog_id' => $catalogId])->value('quantity') ?? 0;
        $change->handle(auth()->user(), $this->planId, $area, $catalogId, $current + (int) $this->quantity);
        $this->phrase = '';
    }

    public function remove(ChangePlanItem $change, string $area, string $catalogId): void
    {
        $change->handle(auth()->user(), $this->planId, Area::from($area), $catalogId, 0);
    }

    public function convert(ConvertPlanToTank $convert): void
    {
        try {
            $tank = $convert->handle(auth()->user(), $this->planId);
        } catch (AuthorizationException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }
        $this->redirectRoute('aquarium.tank', ['tank' => $tank->id], navigate: true);
    }

    public function render(Catalog $catalog, BuildPlanReport $report, BuildShoppingList $shopping): View
    {
        $user = auth()->user();
        $access = app(Access::class);
        $plan = $access->plan($user, $this->planId);

        return view('livewire.aquarium.plan-show', [
            'plan' => $plan,
            'report' => $report->handle($user, $this->planId, $this->category ?: null),
            'shopping' => $access->allows($user, Feature::PublicOffers) ? $shopping->handle($user, $this->planId) : null,
            'canCart' => $access->allows($user, Feature::ShopCart),
            'results' => $plan->state === TankPlan::DRAFT ? $catalog->search($this->phrase, [Area::Fauna, Area::Flora]) : [],
            'tank' => $plan->converted_tank_id,
        ]);
    }
}
