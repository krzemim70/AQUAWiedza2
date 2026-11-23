<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Aquarium\CalculatorLimits;
use App\Domain\Aquarium\Models\TankType;
use App\Domain\Aquarium\Validation;
use Illuminate\Validation\Rule;

/** Surowe wejście formularza planu. Podany litraż wyznacza pasmo (progi z etykiet katalogu), inaczej pasmo wybiera użytkownik. */
final readonly class PlanData
{
    public function __construct(
        public string $name,
        public string $type,
        public string $sizeBand = '',
        public ?string $liters = null,
    ) {}

    /** @return array{name: string, type: string, size_band: string, liters: int|null} */
    public function attributes(): array
    {
        $liters = CalculatorLimits::norm($this->liters);
        $v = Validation::validate(
            ['name' => trim($this->name), 'type' => $this->type, 'size_band' => $this->sizeBand ?: null, 'liters' => $liters === '' ? null : $liters],
            [
                'name' => ['required', 'string', 'max:100'],
                'type' => ['required', Rule::enum(TankType::class)],
                'liters' => app(CalculatorLimits::class)->rules('volume_l', integer: true, required: false),
                'size_band' => [$liters === '' ? 'required' : 'nullable', Rule::enum(TankBand::class)],
            ],
            ['name' => 'Nazwa', 'type' => 'Typ akwarium', 'size_band' => 'Wielkość zbiornika', 'liters' => 'Litraż'],
        );
        $liters = isset($v['liters']) ? (int) $v['liters'] : null;

        return [
            'name' => $v['name'], 'type' => $v['type'], 'liters' => $liters,
            'size_band' => $liters === null ? $v['size_band'] : TankBand::forLiters($liters)->value,
        ];
    }
}
