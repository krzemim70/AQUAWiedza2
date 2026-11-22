<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Actions\CalculateRedfield;
use App\Domain\Aquarium\Actions\LatestMeasurement;
use App\Domain\Aquarium\Models\Tank;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.aquarist')]
#[Title('Kalkulator Redfielda — Aikwarium')]
class RedfieldCalculator extends Component
{
    public string $no3 = '';

    public string $po4 = '';

    /** Zbiornik, z którego wczytujemy ostatni pomiar (opcjonalnie). */
    public string $tank = '';

    /** @var array{molar: float, mass: float, zone: string, signal: string, message: string}|null */
    public ?array $result = null;

    public string $notice = '';

    public function calculate(CalculateRedfield $redfield): void
    {
        $this->result = null;
        $r = $redfield->handle(auth()->user(), $this->no3, $this->po4);
        $this->result = [
            'molar' => $r->molarRatio, 'mass' => $r->massRatio, 'zone' => $r->zone->label(),
            'signal' => $r->zone->signal(), 'message' => $r->message,
        ];
    }

    public function prefill(LatestMeasurement $latest): void
    {
        $this->notice = '';
        $this->result = null;
        if ($this->tank === '') {
            $this->notice = 'Wybierz zbiornik.';

            return;
        }
        $m = $latest->handle(auth()->user(), (int) $this->tank);
        if ($m?->no3 === null || $m->po4 === null) {
            $this->notice = 'Ostatni pomiar tego zbiornika nie zawiera jednocześnie NO₃ i PO₄. Wpisz wartości ręcznie.';

            return;
        }
        $this->no3 = $m->no3;
        $this->po4 = $m->po4;
    }

    public function render(): View
    {
        return view('livewire.aquarium.redfield-calculator', ['tanks' => Tank::ownedBy(auth()->user())->orderBy('id')->get()]);
    }
}
