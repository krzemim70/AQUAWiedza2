<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Actions\UpcomingReminders;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\IdentityAccess\Models\Feature;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.aquarist')]
#[Title('Moje akwarium — Aikwarium')]
class TankIndex extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        $access = app(Access::class);
        $tanks = Tank::ownedBy($user)->orderBy('id')->get();
        $add = ['tank_count' => $tanks->count()];

        return view('livewire.aquarium.tank-index', [
            'tanks' => $tanks,
            'canPlan' => $access->allows($user, Feature::TankPlan),
            'canQuickMeasure' => $access->allows($user, Feature::QuickMeasurement),
            'canAdd' => $access->allows($user, Feature::TankAdd, $add),
            'addMessage' => $access->message($user, Feature::TankAdd, $add),
            'upcoming' => $access->allows($user, Feature::MeasurementHistory) ? app(UpcomingReminders::class)->handle($user) : null,
        ]);
    }
}
