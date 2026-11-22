<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\RedfieldResult;
use App\Domain\Aquarium\Models\RedfieldZone;
use App\Domain\Aquarium\Validation;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;

class CalculateRedfield
{
    private const NO3_MOLAR_MASS = 62.004;

    private const PO4_MOLAR_MASS = 94.97;

    public function __construct(private Access $access, private Settings $settings) {}

    /** Obliczenie bez zapisu pomiaru. Strefa wynika z niezaokrąglonego N:P i granic z ustawień. */
    public function handle(User $actor, string $no3, string $po4): RedfieldResult
    {
        $this->access->authorize($actor, Feature::Redfield);

        $max = $this->settings->get(SettingKey::RedfieldMaxInput);
        $v = Validation::validate(
            ['no3' => str_replace(',', '.', trim($no3)), 'po4' => str_replace(',', '.', trim($po4))],
            ['no3' => ['required', 'numeric', 'min:0', "max:$max"], 'po4' => ['required', 'numeric', 'gt:0', "max:$max"]],
            ['no3' => 'NO₃ (mg/l)', 'po4' => 'PO₄ (mg/l)'],
        );
        $no3 = (float) $v['no3'];
        $po4 = (float) $v['po4'];

        $molar = ($no3 / self::NO3_MOLAR_MASS) / ($po4 / self::PO4_MOLAR_MASS);
        $zone = $this->zone($molar);

        return new RedfieldResult(round($molar, 2), round($no3 / $po4, 2), $zone, $zone->message());
    }

    /** Granice [a, b, c, d]: poniżej a, [a,b), [b,c] zbilansowany, (c,d], powyżej d. */
    private function zone(float $ratio): RedfieldZone
    {
        [$a, $b, $c, $d] = $this->settings->get(SettingKey::RedfieldZoneEdges);

        return match (true) {
            $ratio < $a => RedfieldZone::StrongPhosphateExcess,
            $ratio < $b => RedfieldZone::PhosphateExcess,
            $ratio <= $c => RedfieldZone::Balanced,
            $ratio <= $d => RedfieldZone::NitrateExcess,
            default => RedfieldZone::StrongNitrateExcess,
        };
    }
}
