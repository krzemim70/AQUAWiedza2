<?php

namespace App\Livewire\Aquarium;

use App\Domain\Advisor\Engine\FishBand;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Actions\CreateTank;
use App\Domain\Aquarium\Actions\DeleteTank;
use App\Domain\Aquarium\Actions\UpdateTank;
use App\Domain\Aquarium\Data\TankData;
use App\Domain\Aquarium\Models\TankType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Tworzenie (bez id) i edycja zbiornika; walidacja i limit w akcjach domeny. */
class TankForm extends Component
{
    #[Locked]
    public ?int $tankId = null;

    public string $name = '';

    public string $type = '';

    public string $size_band = '';

    public string $fish_size_band = '';

    public string $temp_band = '';

    public string $ph_band = '';

    public string $notes = '';

    public bool $saved = false;

    public function mount(?int $tankId = null): void
    {
        $this->tankId = $tankId;
        if ($tankId !== null) {
            $tank = app(Access::class)->tank(auth()->user(), $tankId);
            $this->fill([
                'name' => $tank->name, 'type' => $tank->type->value, 'size_band' => $tank->getRawOriginal('size_band') ?? '',
                'fish_size_band' => $tank->getRawOriginal('fish_size_band') ?? '', 'temp_band' => $tank->getRawOriginal('temp_band') ?? '',
                'ph_band' => $tank->getRawOriginal('ph_band') ?? '', 'notes' => $tank->notes ?? '',
            ]);
        }
    }

    public function save(CreateTank $create, UpdateTank $update): void
    {
        $this->saved = false;
        $data = new TankData($this->name, $this->type, $this->size_band, $this->fish_size_band, $this->temp_band, $this->ph_band, $this->notes);

        try {
            if ($this->tankId === null) {
                $tank = $create->handle(auth()->user(), $data);
                $this->redirectRoute('aquarium.tank', ['tank' => $tank->id], navigate: true);

                return;
            }
            $update->handle(auth()->user(), $this->tankId, $data);
            $this->saved = true;
        } catch (AuthorizationException $e) {
            $this->addError('form', $e->getMessage());
        }
    }

    public function delete(DeleteTank $delete): void
    {
        abort_if($this->tankId === null, 404);
        $delete->handle(auth()->user(), $this->tankId);
        $this->redirectRoute('aquarium.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.aquarium.tank-form', [
            'types' => TankType::cases(), 'sizes' => TankBand::cases(), 'fishSizes' => FishBand::cases(),
            'temps' => TemperatureBand::cases(), 'phs' => PhBand::cases(),
        ]);
    }
}
