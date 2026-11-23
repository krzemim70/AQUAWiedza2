<?php

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;

const NEW_KEYS = [
    'aquarium.compat.severity', 'aquarium.compat.temperament', 'aquarium.stocking.cm_per_liter', 'aquarium.calculators.limits',
    'aquarium.calculators.co2', 'aquarium.plan.limit', 'aquarium.reminders.auto', 'advisor.water_hints.map', 'advisor.water_hints.checks',
    'advisor.water_hints.tolerance', 'advisor.water_hints.stale_days', 'shops.watch', 'shops.reviews', 'shops.promo', 'campaigns.default_min_discount', 'metrics.demand',
];

function startValue(string $key): mixed
{
    return app(Settings::class)->get(SettingKey::from($key));
}

function accepts(string $key, mixed $value): bool
{
    return validator(['value' => $value], SettingKey::from($key)->rules())->passes();
}

it('defines every new key from the feature design', function () {
    foreach (NEW_KEYS as $key) {
        expect(SettingKey::tryFrom($key))->not->toBeNull($key);
    }
});

it('starts with values that claim nothing beyond the data', function () {
    expect(startValue('aquarium.stocking.cm_per_liter'))->toBeNull()
        ->and(startValue('campaigns.default_min_discount'))->toBeNull()
        ->and(startValue('aquarium.reminders.auto'))->toBe(array_fill_keys(['water_change_days', 'filter_cleaning_days', 'fertilization_days', 'water_test_after_days', 'consumable_lead_days'], null))
        ->and(startValue('aquarium.calculators.co2'))->toBe(['factor' => 3, 'low_mg_l' => null, 'high_mg_l' => null, 'source' => null])
        ->and(startValue('aquarium.compat.temperament'))->toBe([])
        ->and(startValue('aquarium.compat.severity')['tank_too_small'])->toBe('warning')
        ->and(startValue('aquarium.compat.severity')['fish_too_large'])->toBe('off')
        ->and(startValue('aquarium.plan.limit'))->toBe(['drop' => 0, 'tank' => 1, 'fishroom' => null])
        ->and(startValue('advisor.water_hints.checks'))->toBe(['ph', 'temp_c'])
        ->and(startValue('advisor.water_hints.tolerance'))->toBe(['ph' => 0, 'temp_c' => 0])
        ->and(startValue('advisor.water_hints.map'))->toBe([])
        ->and(startValue('advisor.water_hints.stale_days'))->toBe(30)
        ->and(startValue('shops.watch'))->toBe(['tank' => 10, 'fishroom' => 100, 'retention_days' => 14])
        ->and(startValue('shops.reviews'))->toBe(['window_days' => 30, 'min_to_show' => 3, 'max_length' => 500])
        ->and(startValue('shops.promo'))->toBe(['max_days' => 30, 'lookback_days' => 30, 'show_reference_price' => false])
        ->and(startValue('metrics.demand'))->toBe(['k' => 5, 'retention_days' => 400, 'region' => 'city']);
});

it('validates the new values', function () {
    $auto = ['water_change_days' => 14, 'filter_cleaning_days' => null, 'fertilization_days' => null, 'water_test_after_days' => null, 'consumable_lead_days' => null];

    expect(accepts('aquarium.stocking.cm_per_liter', null))->toBeTrue()
        ->and(accepts('aquarium.stocking.cm_per_liter', 1.5))->toBeTrue()
        ->and(accepts('aquarium.stocking.cm_per_liter', 0))->toBeFalse()
        ->and(accepts('aquarium.reminders.auto', $auto))->toBeTrue()
        ->and(accepts('aquarium.reminders.auto', [...$auto, 'water_change_days' => 0]))->toBeFalse()
        ->and(accepts('aquarium.compat.severity', array_replace(startValue('aquarium.compat.severity'), ['ph_pair' => 'off'])))->toBeTrue()
        ->and(accepts('aquarium.compat.severity', array_replace(startValue('aquarium.compat.severity'), ['ph_pair' => 'bad'])))->toBeFalse()
        ->and(accepts('aquarium.calculators.limits', array_replace(startValue('aquarium.calculators.limits'), ['kh' => ['min' => 5, 'max' => 1]])))->toBeFalse()
        ->and(accepts('advisor.water_hints.checks', []))->toBeTrue()
        ->and(accepts('advisor.water_hints.stale_days', 30))->toBeTrue()
        ->and(accepts('advisor.water_hints.stale_days', 0))->toBeFalse()
        ->and(accepts('advisor.water_hints.checks', ['no3']))->toBeFalse();
});

it('accepts an empty pH correction list', function () {
    $key = SettingKey::VerificationCorrections->value;
    $corrections = startValue($key);

    expect($corrections['ph_acid'])->toBe(['acid buffer'])
        ->and($corrections['ph_other'])->toBe([])
        ->and(accepts($key, $corrections))->toBeTrue();
});
