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
    /**
     * Zakładka => [etykieta, funkcja bramkująca, komponent]; z plików resources/tank-tabs/*.php (kolejność = nazwy plików).
     *
     * @return array<string, array{string, Feature, string}>
     */
    private static function definitions(): array
    {
        $tabs = [];
        foreach (glob(resource_path('tank-tabs/*.php')) ?: [] as $file) {
            $tabs = [...$tabs, ...require $file];
        }

        return $tabs;
    }

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
        $tab = array_key_exists($this->tab, self::definitions()) ? $this->tab : 'zbiornik';

        $tabs = [];
        foreach (self::definitions() as $key => [$label, $feature, $component]) {
            $tabs[$key] = ['label' => $label, 'allowed' => $access->allows($user, $feature), 'message' => $access->message($user, $feature), 'component' => $component];
        }

        return view('livewire.aquarium.tank-show', [
            'tank' => Tank::ownedBy($user)->findOrFail($this->tankId), 'tabs' => $tabs, 'current' => $tab,
        ]);
    }
}
