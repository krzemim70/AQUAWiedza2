<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\IdentityAccess\Models\Feature;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Strona zbiornika z zakładkami. Zakładki Fishroom są w Tank widoczne jako zaproszenie: bez ładowania zawartości.
 */
#[Layout('layouts.aquarist')]
#[Title('Zbiornik — Aikwarium')]
class TankShow extends Component
{
    /** Zakładka => [etykieta, funkcja bramkująca, komponent]. */
    private const TABS = [
        'zbiornik' => ['Zbiornik', Feature::TankView, 'aquarium.tank-form'],
        'obsada' => ['Obsada', Feature::TankView, 'aquarium.tank-livestock'],
        'pomiary' => ['Pomiary', Feature::TankView, 'aquarium.tank-measurements'],
        'historia' => ['Historia parametrów', Feature::MeasurementHistory, 'aquarium.measurement-history'],
        'dziennik' => ['Dziennik', Feature::MeasurementHistory, 'aquarium.tank-journal'],
        'przypomnienia' => ['Przypomnienia', Feature::MeasurementHistory, 'aquarium.tank-reminders'],
    ];

    #[Locked]
    public int $tankId;

    #[Url(as: 'karta')]
    public string $tab = 'zbiornik';

    public function mount(int $tank): void
    {
        $this->tankId = $tank;
        app(Access::class)->tank(auth()->user(), $tank);
    }

    public function render(): View
    {
        $user = auth()->user();
        $access = app(Access::class);
        $tab = array_key_exists($this->tab, self::TABS) ? $this->tab : 'zbiornik';

        $tabs = [];
        foreach (self::TABS as $key => [$label, $feature, $component]) {
            $tabs[$key] = ['label' => $label, 'allowed' => $access->allows($user, $feature), 'message' => $access->message($user, $feature), 'component' => $component];
        }

        return view('livewire.aquarium.tank-show', [
            'tank' => Tank::ownedBy($user)->findOrFail($this->tankId), 'tabs' => $tabs, 'current' => $tab,
        ]);
    }
}
