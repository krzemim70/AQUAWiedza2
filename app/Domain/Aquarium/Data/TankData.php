<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\Aquarium\Models\FishSize;
use App\Domain\Aquarium\Models\PhBand;
use App\Domain\Aquarium\Models\TankSize;
use App\Domain\Aquarium\Models\TankType;
use App\Domain\Aquarium\Models\TempBand;
use App\Domain\Aquarium\Validation;
use Illuminate\Validation\Rule;

/** Surowe wejście formularza zbiornika; wartości pasm to etykiety słowników, pusty tekst = brak preferencji. */
final readonly class TankData
{
    public function __construct(
        public string $name,
        public string $type,
        public string $sizeBand,
        public ?string $fishSizeBand = null,
        public ?string $tempBand = null,
        public ?string $phBand = null,
        public ?string $notes = null,
    ) {}

    /**
     * Walidacja i kolumny do zapisu (puste pasma opcjonalne jako null).
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        $input = [
            'name' => trim($this->name), 'type' => $this->type, 'size_band' => $this->sizeBand,
            'fish_size_band' => $this->fishSizeBand ?: null, 'temp_band' => $this->tempBand ?: null,
            'ph_band' => $this->phBand ?: null, 'notes' => $this->notes === null || trim($this->notes) === '' ? null : trim($this->notes),
        ];

        return Validation::validate($input, [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(TankType::class)],
            'size_band' => ['required', Rule::enum(TankSize::class)],
            'fish_size_band' => ['nullable', Rule::enum(FishSize::class)],
            'temp_band' => ['nullable', Rule::enum(TempBand::class)],
            'ph_band' => ['nullable', Rule::enum(PhBand::class)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'name' => 'Nazwa', 'type' => 'Typ akwarium', 'size_band' => 'Wielkość zbiornika', 'fish_size_band' => 'Wielkość ryb',
            'temp_band' => 'Temperatura', 'ph_band' => 'pH', 'notes' => 'Notatka',
        ]) + $input;
    }
}
