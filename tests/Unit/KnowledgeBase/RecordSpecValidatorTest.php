<?php

use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\SpecSourceKind;
use App\Domain\KnowledgeBase\RecordSpecValidator;

it('validates and normalizes complete care product attributes', function () {
    $result = RecordSpecValidator::validateAttributes(Area::Care, [
        'parameter' => 'ph',
        'direction' => 'down',
        'dose_amount' => '2.5',
        'unit' => 'g',
        'dose_per_liters' => '80',
        'package_amount' => '300',
        'water_type' => 'freshwater',
        'contraindications' => [
            ['target' => 'shrimp', 'quote' => 'Ostrożnie w zbiornikach z krewetkami.'],
            ['target' => 'snails', 'quote' => 'Nie stosować ze ślimakami.'],
        ],
    ]);

    expect($result['errors'])->toBe([])
        ->and($result['attributes'])->toBe([
            'parameter' => 'ph',
            'direction' => 'down',
            'dose_amount' => 2.5,
            'dose_per_liters' => 80.0,
            'package_amount' => 300.0,
            'unit' => 'g',
            'water_type' => 'freshwater',
            'contraindications' => [
                ['target' => 'shrimp', 'quote' => 'Ostrożnie w zbiornikach z krewetkami.'],
                ['target' => 'snails', 'quote' => 'Nie stosować ze ślimakami.'],
            ],
        ]);
});

it('rejects invalid care units, parameters, directions and contraindications', function () {
    $badUnit = RecordSpecValidator::validateAttributes(Area::Care, [
        'dose_amount' => 5,
        'unit' => 'litry',
    ]);
    expect($badUnit['errors'])->toHaveKey('attributes.unit');

    $missingUnit = RecordSpecValidator::validateAttributes(Area::Care, [
        'package_amount' => 250,
    ]);
    expect($missingUnit['errors'])->toHaveKey('attributes.unit');

    $badParamAndDirection = RecordSpecValidator::validateAttributes(Area::Care, [
        'parameter' => 'nh4',
        'direction' => 'remove',
        'water_type' => 'brackish',
    ]);
    expect($badParamAndDirection['errors'])
        ->toHaveKey('attributes.parameter')
        ->toHaveKey('attributes.direction')
        ->toHaveKey('attributes.water_type');

    $coupledParam = RecordSpecValidator::validateAttributes(Area::Care, [
        'parameter' => 'kh',
    ]);
    expect($coupledParam['errors'])->toHaveKey('attributes.direction');

    $litersWithoutDose = RecordSpecValidator::validateAttributes(Area::Care, [
        'dose_per_liters' => 100,
        'unit' => 'ml',
    ]);
    expect($litersWithoutDose['errors'])->toHaveKey('attributes.dose_amount');

    $badContraindication = RecordSpecValidator::validateAttributes(Area::Care, [
        'parameter' => 'ph',
        'direction' => 'down',
        'contraindications' => [
            ['target' => 'shrimp', 'quote' => '   '],
            ['target' => 'shrimp', 'quote' => 'Duplikat.'],
            ['target' => 'corals', 'quote' => 'Nieznana grupa.'],
        ],
    ]);
    expect($badContraindication['errors'])
        ->toHaveKey('attributes.contraindications.0.quote')
        ->toHaveKey('attributes.contraindications.1.target')
        ->toHaveKey('attributes.contraindications.2.target');

    $empty = RecordSpecValidator::validateAttributes(Area::Care, []);
    expect($empty['errors'])->toHaveKey('attributes');
});

it('validates fauna min-max ranges and rejects inverted or out-of-bounds ranges', function () {
    $valid = RecordSpecValidator::validateAttributes(Area::Fauna, [
        'ph' => ['min' => '6.8', 'max' => '7.5'],
        'temp_c' => ['min' => 22, 'max' => 25.5],
        'kh' => ['min' => 4, 'max' => 12],
        'gh' => ['min' => 8, 'max' => 30],
        'no3' => ['min' => 0, 'max' => 25],
        'po4' => ['min' => 0, 'max' => 1.0],
    ]);

    expect($valid['errors'])->toBe([])
        ->and($valid['attributes']['ph'])->toBe(['min' => 6.8, 'max' => 7.5])
        ->and($valid['attributes']['gh'])->toBe(['min' => 8.0, 'max' => 30.0]);

    $inverted = RecordSpecValidator::validateAttributes(Area::Fauna, [
        'ph' => ['min' => 8.0, 'max' => 6.5],
    ]);
    expect($inverted['errors'])->toHaveKey('attributes.ph.max');

    $outOfBounds = RecordSpecValidator::validateAttributes(Area::Fauna, [
        'ph' => ['min' => -1, 'max' => 15],
        'temp_c' => ['min' => 20, 'max' => 60],
    ]);
    expect($outOfBounds['errors'])
        ->toHaveKey('attributes.ph.min')
        ->toHaveKey('attributes.ph.max')
        ->toHaveKey('attributes.temp_c.max');

    $incomplete = RecordSpecValidator::validateAttributes(Area::Fauna, [
        'gh' => ['min' => 5, 'max' => null],
    ]);
    expect($incomplete['errors'])->toHaveKey('attributes.gh.max');

    $unknown = RecordSpecValidator::validateAttributes(Area::Fauna, [
        'salinity' => ['min' => 1, 'max' => 2],
    ]);
    expect($unknown['errors'])->toHaveKey('attributes');

    $empty = RecordSpecValidator::validateAttributes(Area::Fauna, []);
    expect($empty['errors'])->toHaveKey('attributes');
});

it('enforces allowed areas for each source kind', function () {
    expect(SpecSourceKind::CatalogQuote->allowedFor(Area::Fauna))->toBeTrue()
        ->and(SpecSourceKind::CatalogQuote->allowedFor(Area::Flora))->toBeTrue()
        ->and(SpecSourceKind::CatalogQuote->allowedFor(Area::Equipment))->toBeTrue()
        ->and(SpecSourceKind::CatalogQuote->allowedFor(Area::Care))->toBeTrue()
        ->and(SpecSourceKind::ManufacturerLabel->allowedFor(Area::Care))->toBeTrue()
        ->and(SpecSourceKind::ManufacturerLabel->allowedFor(Area::Equipment))->toBeTrue()
        ->and(SpecSourceKind::ManufacturerLabel->allowedFor(Area::Fauna))->toBeFalse()
        ->and(SpecSourceKind::ManufacturerLabel->allowedFor(Area::Flora))->toBeFalse()
        ->and(SpecSourceKind::Literature->allowedFor(Area::Fauna))->toBeTrue()
        ->and(SpecSourceKind::Literature->allowedFor(Area::Care))->toBeFalse()
        ->and(SpecSourceKind::Literature->allowedFor(Area::Equipment))->toBeFalse()
        ->and(SpecSourceKind::Literature->allowedFor(Area::Flora))->toBeFalse();
});
