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
     * @param  array<string, mixed>  $input  kh, ph, other_buffers
     */
    public function handle(?User $actor, array $input): R
    {
        $this->access->authorize($actor, Feature::Calculators);

        $kh = CalculatorLimits::norm($input['kh'] ?? '');
        $ph = CalculatorLimits::norm($input['ph'] ?? '');
        $assumptions = 'Wzór opisuje słodką wodę, w której zasadowość pochodzi głównie z wodorowęglanów. Inne bufory oraz substancje zmieniające pH, np. kwasy z torfu, mogą zniekształcić oszacowanie.';
        if (in_array($input['other_buffers'] ?? null, [true, 1, '1'], true)) {
            return new R(null, 'mg/l', notes: [$assumptions]);
        }

        $v = Validation::validate([
            'kh' => $kh, 'ph' => $ph,
        ], ['kh' => $this->limits->rules('kh', required: false), 'ph' => $this->limits->rules('ph', required: false)], ['kh' => 'KH (°dKH)', 'ph' => 'pH']);
        if ($kh === '' || $ph === '') {
            return new R(null, 'mg/l', notes: ['Podaj KH i pH.']);
        }

        [$kh, $ph] = [(float) $v['kh'], (float) $v['ph']];
        $factor = (float) $this->settings->get(SettingKey::CalculatorCo2)['factor'];
        $value = round(Co2Estimate::mgPerLiter($kh, $ph, $factor), 1);

        return new R($value, 'mg/l', [
            'CO₂ ≈ '.R::fmt($factor).' × '.R::fmt($kh).' °dKH × 10^(7 − '.R::fmt($ph).') = '.R::fmt($value, 1).' mg/l',
        ], [
            $assumptions,
            'To przybliżenie ze wzoru na zależność węglanową (KH, pH, CO₂), bez korekty temperatury.',
        ]);
    }
}
