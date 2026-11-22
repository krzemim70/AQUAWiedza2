<?php

namespace App\Domain\Aquarium;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;

/** Zakresy wejść kalkulatorów z ustawienia `aquarium.calculators.limits`; wartości dodatnie, przecinek jak kropka. */
class CalculatorLimits
{
    public function __construct(private Settings $settings) {}

    /** @return list<string> */
    public function rules(string $key, bool $integer = false, bool $required = true): array
    {
        $limit = $this->settings->get(SettingKey::CalculatorLimits)[$key];

        return [$required ? 'required' : 'nullable', 'bail', $integer ? 'integer' : 'numeric', 'gt:0', 'min:'.$limit['min'], 'max:'.$limit['max']];
    }

    public function max(string $key): float
    {
        return (float) $this->settings->get(SettingKey::CalculatorLimits)[$key]['max'];
    }

    public static function norm(mixed $value): string
    {
        return str_replace(',', '.', trim(is_scalar($value) ? (string) $value : ''));
    }
}
