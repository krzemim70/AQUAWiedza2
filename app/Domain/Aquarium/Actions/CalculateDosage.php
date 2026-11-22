<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\CalculatorLimits;
use App\Domain\Aquarium\Calculators\Dosage;
use App\Domain\Aquarium\Data\CalculatorResult as R;
use App\Domain\Aquarium\Validation;
use App\Domain\IdentityAccess\Models\Feature;
use App\Models\User;
use Illuminate\Validation\Rule;

class CalculateDosage
{
    public const UNITS = ['ml', 'g', 'kropli', 'tabletek', 'miarek'];

    public function __construct(private Access $access, private CalculatorLimits $limits) {}

    /**
     * Przelicza dawkę z etykiety producenta („X jednostek na Y litrów”) na podany litraż. Bez własnych dawek.
     *
     * @param  array<string, mixed>  $input  dose, label_volume, volume, unit
     */
    public function handle(?User $actor, array $input): R
    {
        $this->access->authorize($actor, Feature::Calculators);

        $v = Validation::validate([
            'dose' => CalculatorLimits::norm($input['dose'] ?? ''),
            'label_volume' => CalculatorLimits::norm($input['label_volume'] ?? ''),
            'volume' => CalculatorLimits::norm($input['volume'] ?? ''),
            'unit' => (string) ($input['unit'] ?? ''),
        ], [
            'dose' => $this->limits->rules('dose'), 'label_volume' => $this->limits->rules('volume_l'),
            'volume' => $this->limits->rules('volume_l'), 'unit' => ['required', Rule::in(self::UNITS)],
        ], ['dose' => 'Dawka z etykiety', 'label_volume' => 'Litry z etykiety', 'volume' => 'Litraż do przeliczenia', 'unit' => 'Jednostka']);

        [$dose, $base, $volume] = [(float) $v['dose'], (float) $v['label_volume'], (float) $v['volume']];
        $value = round(Dosage::scale($dose, $base, $volume), 2);

        return new R($value, $v['unit'], [
            R::fmt($dose).' '.$v['unit'].' × '.R::fmt($volume).' l ÷ '.R::fmt($base).' l = '.R::fmt($value, 2).' '.$v['unit'],
        ], ['Sprawdź dawkowanie na opakowaniu producenta. Kalkulator tylko przelicza podane wartości.']);
    }
}
