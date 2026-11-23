<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\CalculatorLimits;
use App\Domain\Aquarium\Calculators\Co2Estimate;
use App\Domain\Aquarium\Data\CalculatorResult as R;
use App\Domain\Aquarium\Validation;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;

class EstimateCo2
{
    public function __construct(private Access $access, private CalculatorLimits $limits, private Settings $settings) {}

    /**
     * Przybliżenie CO₂ ze wzoru KH/pH; bez oceny stężenia.
     *
     * @param  array<string, mixed>  $input  kh, ph
     */
    public function handle(?User $actor, array $input): R
    {
        $this->access->authorize($actor, Feature::Calculators);

        $v = Validation::validate([
            'kh' => CalculatorLimits::norm($input['kh'] ?? ''), 'ph' => CalculatorLimits::norm($input['ph'] ?? ''),
        ], ['kh' => $this->limits->rules('kh'), 'ph' => $this->limits->rules('ph')], ['kh' => 'KH (°dKH)', 'ph' => 'pH']);

        [$kh, $ph] = [(float) $v['kh'], (float) $v['ph']];
        $factor = (float) $this->settings->get(SettingKey::CalculatorCo2)['factor'];
        $value = round(Co2Estimate::mgPerLiter($kh, $ph, $factor), 1);

        return new R($value, 'mg/l', [
            'CO₂ ≈ '.R::fmt($factor).' × '.R::fmt($kh).' °dKH × 10^(7 − '.R::fmt($ph).') = '.R::fmt($value, 1).' mg/l',
        ], [
            'To przybliżenie ze wzoru na zależność węglanową (KH, pH, CO₂). Współczynnik '.R::fmt($factor).' i brak korekty temperatury to uproszczenie.',
            'Wzór zawyża wynik, gdy pH obniżają kwasy, torf, garbniki lub inne bufory niż węglanowe (np. preparaty obniżające pH). Kalkulator nie ocenia, czy stężenie jest odpowiednie.',
        ]);
    }
}
