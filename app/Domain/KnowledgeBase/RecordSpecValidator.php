<?php

namespace App\Domain\KnowledgeBase;

use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Entry;
use App\Domain\KnowledgeBase\Models\Record;
use App\Domain\KnowledgeBase\Models\RecordSpec;
use App\Domain\KnowledgeBase\Models\SpecSourceKind;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use Illuminate\Validation\ValidationException;

/** Walidacja i normalizacja pól strukturalnych bazy wiedzy oraz kontrola cytatów katalogowych. */
class RecordSpecValidator
{
    /** @var array<string, string> */
    public const CARE_PARAMETERS = [
        'ph' => 'pH',
        'kh' => 'Twardość węglanowa (KH)',
        'gh' => 'Twardość ogólna (GH)',
        'no3' => 'Azotany (NO₃)',
        'po4' => 'Fosforany (PO₄)',
        'temp' => 'Temperatura',
    ];

    /** @var array<string, string> */
    public const CARE_DIRECTIONS = [
        'up' => 'Podnosi',
        'down' => 'Obniża',
        'stabilize' => 'Stabilizuje',
    ];

    /** @var array<string, string> */
    public const CARE_UNITS = [
        'ml' => 'ml',
        'g' => 'g',
        'szt' => 'szt.',
        'krople' => 'krople',
    ];

    /** @var array<string, string> */
    public const WATER_TYPES = [
        'freshwater' => 'Woda słodka',
        'marine' => 'Woda morska',
        'both' => 'Woda słodka i morska',
    ];

    /** @var array<string, string> */
    public const CONTRAINDICATIONS = [
        'shrimp' => 'Krewetki',
        'snails' => 'Ślimaki',
        'scaleless_fish' => 'Ryby bez łusek',
        'plants' => 'Rośliny',
    ];

    /** @var array<string, string> */
    public const FAUNA_PARAMETERS = [
        'ph' => 'pH',
        'temp_c' => 'Temperatura (°C)',
        'kh' => 'KH (°dKH)',
        'gh' => 'GH (°dGH)',
        'no3' => 'NO₃ (mg/l)',
        'po4' => 'PO₄ (mg/l)',
    ];

    /** @var array<string, array{0: float, 1: float}> */
    public const FAUNA_BOUNDS = [
        'ph' => [0.0, 14.0],
        'temp_c' => [0.0, 50.0],
        'kh' => [0.0, 50.0],
        'gh' => [0.0, 100.0],
        'no3' => [0.0, 500.0],
        'po4' => [0.0, 50.0],
    ];

    public function __construct(private Settings $settings) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{source_ref: ?string, evidence_quote: string, ean: ?string, attributes: array<string, mixed>}
     */
    public function validateAndNormalize(
        Record $record,
        SpecSourceKind $sourceKind,
        ?string $sourceRef,
        string $evidenceQuote,
        ?string $ean,
        array $attributes,
        ?int $batchId = null,
    ): array {
        /** @var list<string> $allowedSources */
        $allowedSources = $this->settings->get(SettingKey::CatalogSpecSources);
        $errors = [];

        if (! in_array($sourceKind->value, $allowedSources, true)) {
            $errors['source_kind'][] = 'Wybrany rodzaj źródła jest wyłączony w ustawieniach.';
        }

        if (! $sourceKind->allowedFor($record->area)) {
            $errors['source_kind'][] = match ($sourceKind) {
                SpecSourceKind::ManufacturerLabel => 'Źródło „etykieta producenta” jest dozwolone wyłącznie dla preparatów i sprzętu.',
                SpecSourceKind::Literature => 'Źródło „literatura” jest dozwolone wyłącznie dla zakresów fauny.',
                SpecSourceKind::CatalogQuote => 'Wybrany rodzaj źródła nie jest dozwolony dla tego obszaru.',
            };
        }

        $quote = trim($evidenceQuote);
        if ($quote === '') {
            $errors['evidence_quote'][] = 'Podaj cytat potwierdzający dane.';
        } elseif (mb_strlen($quote) > 2000) {
            $errors['evidence_quote'][] = 'Cytat może mieć maksymalnie 2000 znaków.';
        }

        $ref = trim((string) ($sourceRef ?? ''));
        $ref = $ref === '' ? null : $ref;
        if ($sourceKind->requiresSourceRef() && $ref === null) {
            $errors['source_ref'][] = 'To źródło wymaga podania referencji (producent i nazwa karty albo publikacja / stały odnośnik).';
        } elseif ($ref !== null && mb_strlen($ref) > 500) {
            $errors['source_ref'][] = 'Referencja źródła może mieć maksymalnie 500 znaków.';
        }

        $eanClean = trim((string) ($ean ?? ''));
        $eanClean = $eanClean === '' ? null : $eanClean;
        if ($eanClean !== null && preg_match('/^[0-9]{8,14}$/', $eanClean) !== 1) {
            $errors['ean'][] = 'Kod EAN musi składać się z 8 do 14 cyfr.';
        }

        $attrResult = self::validateAttributes($record->area, $attributes, $eanClean);
        foreach ($attrResult['errors'] as $key => $messages) {
            $errors[$key] = [...($errors[$key] ?? []), ...$messages];
        }

        $normalizedAttributes = $attrResult['attributes'];

        if ($sourceKind === SpecSourceKind::CatalogQuote && $quote !== '') {
            $entry = Entry::query()
                ->inBatch($batchId)
                ->where('record_id', $record->id)
                ->with('description')
                ->first();

            if ($entry === null) {
                $errors['evidence_quote'][] = 'Rekord nie występuje w aktywnej partii katalogu.';
            } else {
                $texts = self::activeDescriptionTexts($entry);
                if (! self::quoteMatchesTexts($quote, $texts)) {
                    $errors['evidence_quote'][] = 'Cytat musi dosłownie występować w aktywnym opisie rekordu.';
                }

                if ($record->area === Area::Care && isset($normalizedAttributes['contraindications']) && is_array($normalizedAttributes['contraindications'])) {
                    foreach ($normalizedAttributes['contraindications'] as $index => $item) {
                        if (is_array($item) && is_string($item['quote'] ?? null) && ! self::quoteMatchesTexts($item['quote'], $texts)) {
                            $errors["attributes.contraindications.{$index}.quote"][] = 'Cytat przeciwwskazania musi dosłownie występować w aktywnym opisie rekordu.';
                        }
                    }
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'source_ref' => $ref,
            'evidence_quote' => $quote,
            'ean' => $eanClean,
            'attributes' => $normalizedAttributes,
        ];
    }

    /**
     * Sprawdza, czy cytat(y) specyfikacji nadal występują w opisie rekordu nowej partii.
     * Zwraca powód wyłączenia albo null, jeśli cytaty są zgodne.
     */
    public function quoteFailureReason(RecordSpec $spec, ?Entry $entry): ?string
    {
        if ($spec->source_kind !== SpecSourceKind::CatalogQuote) {
            return null;
        }

        if ($entry === null) {
            return 'Rekord nie występuje w aktywnej partii katalogu.';
        }

        $texts = self::activeDescriptionTexts($entry);
        if (! self::quoteMatchesTexts($spec->evidence_quote, $texts)) {
            return 'Cytat nie występuje już w aktywnym opisie rekordu.';
        }

        if ($spec->record->area === Area::Care && isset($spec->attributes['contraindications']) && is_array($spec->attributes['contraindications'])) {
            foreach ($spec->attributes['contraindications'] as $item) {
                if (is_array($item) && is_string($item['quote'] ?? null) && ! self::quoteMatchesTexts($item['quote'], $texts)) {
                    return 'Cytat przeciwwskazania nie występuje już w aktywnym opisie rekordu.';
                }
            }
        }

        return null;
    }

    /**
     * Czysta walidacja i normalizacja sekcji `attributes` według obszaru katalogu.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{attributes: array<string, mixed>, errors: array<string, list<string>>}
     */
    public static function validateAttributes(Area $area, array $attributes, ?string $ean = null): array
    {
        return match ($area) {
            Area::Care => self::validateCareAttributes($attributes),
            Area::Fauna => self::validateFaunaAttributes($attributes),
            Area::Equipment => self::validateEquipmentAttributes($attributes, $ean),
            Area::Flora => self::validateFloraAttributes($attributes),
        };
    }

    /**
     * @return list<string>
     */
    public static function activeDescriptionTexts(Entry $entry): array
    {
        $texts = [];
        $body = $entry->description?->body;
        if (is_string($body) && trim($body) !== '') {
            $texts[] = $body;
        }

        $field = $entry->fields['opis'] ?? $entry->fields['opis_krotki'] ?? null;
        if (is_string($field) && trim($field) !== '') {
            $texts[] = $field;
        }

        return $texts;
    }

    /** @param list<string> $texts */
    public static function quoteMatchesTexts(string $quote, array $texts): bool
    {
        if ($quote === '') {
            return false;
        }

        foreach ($texts as $text) {
            if (str_contains($text, $quote)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{attributes: array<string, mixed>, errors: array<string, list<string>>}
     */
    private static function validateCareAttributes(array $attributes): array
    {
        $allowedKeys = [
            'parameter',
            'direction',
            'dose_amount',
            'unit',
            'dose_per_liters',
            'package_amount',
            'water_type',
            'contraindications',
        ];
        $errors = self::rejectUnknownKeys($attributes, $allowedKeys);
        $normalized = [];

        $parameter = self::cleanString($attributes['parameter'] ?? null);
        if ($parameter !== null) {
            if (! array_key_exists($parameter, self::CARE_PARAMETERS)) {
                $errors['attributes.parameter'][] = 'Wybierz parametr z zamkniętej listy (ph, kh, gh, no3, po4, temp).';
            } else {
                $normalized['parameter'] = $parameter;
            }
        }

        $direction = self::cleanString($attributes['direction'] ?? null);
        if ($direction !== null) {
            if (! array_key_exists($direction, self::CARE_DIRECTIONS)) {
                $errors['attributes.direction'][] = 'Wybierz kierunek z zamkniętej listy (up, down, stabilize).';
            } else {
                $normalized['direction'] = $direction;
            }
        }

        if ($parameter !== null && $direction === null) {
            $errors['attributes.direction'][] = 'Wybierz kierunek działania dla wybranego parametru.';
        }
        if ($direction !== null && $parameter === null) {
            $errors['attributes.parameter'][] = 'Wybierz parametr dla wybranego kierunku działania.';
        }

        $doseAmount = self::parsePositiveFloat($attributes['dose_amount'] ?? null, 100000.0, 'attributes.dose_amount', 'Dawka musi być liczbą większą od zera (maks. 100000).', $errors);
        if ($doseAmount !== null) {
            $normalized['dose_amount'] = $doseAmount;
        }

        $dosePerLiters = self::parsePositiveFloat($attributes['dose_per_liters'] ?? null, 100000.0, 'attributes.dose_per_liters', 'Objętość odniesienia (litry) musi być liczbą większą od zera (maks. 100000).', $errors);
        if ($dosePerLiters !== null) {
            $normalized['dose_per_liters'] = $dosePerLiters;
            if ($doseAmount === null) {
                $errors['attributes.dose_amount'][] = 'Podaj dawkę, jeśli określasz objętość odniesienia w litrach.';
            }
        }

        $packageAmount = self::parsePositiveFloat($attributes['package_amount'] ?? null, 1000000.0, 'attributes.package_amount', 'Pojemność opakowania musi być liczbą większą od zera (maks. 1000000).', $errors);
        if ($packageAmount !== null) {
            $normalized['package_amount'] = $packageAmount;
        }

        $unit = self::cleanString($attributes['unit'] ?? null);
        if ($unit !== null) {
            if (! array_key_exists($unit, self::CARE_UNITS)) {
                $errors['attributes.unit'][] = 'Wybierz jednostkę z zamkniętej listy (ml, g, szt, krople).';
            } else {
                $normalized['unit'] = $unit;
            }
        } elseif ($doseAmount !== null || $packageAmount !== null) {
            $errors['attributes.unit'][] = 'Wybierz jednostkę z zamkniętej listy (ml, g, szt, krople).';
        }

        $waterType = self::cleanString($attributes['water_type'] ?? null);
        if ($waterType !== null) {
            if (! array_key_exists($waterType, self::WATER_TYPES)) {
                $errors['attributes.water_type'][] = 'Wybierz rodzaj wody z zamkniętej listy (freshwater, marine, both).';
            } else {
                $normalized['water_type'] = $waterType;
            }
        }

        if (array_key_exists('contraindications', $attributes) && $attributes['contraindications'] !== null) {
            $rawItems = $attributes['contraindications'];
            if (! is_array($rawItems)) {
                $errors['attributes.contraindications'][] = 'Przeciwwskazania muszą być listą pozycji.';
            } else {
                $contraindications = [];
                $seenTargets = [];
                foreach (array_values($rawItems) as $index => $item) {
                    if (! is_array($item)) {
                        $errors["attributes.contraindications.{$index}"][] = 'Nieprawidłowy wpis przeciwwskazania.';

                        continue;
                    }

                    foreach (array_keys($item) as $itemKey) {
                        if (! in_array($itemKey, ['target', 'quote'], true)) {
                            $errors["attributes.contraindications.{$index}"][] = "Nieznane pole przeciwwskazania: {$itemKey}.";
                        }
                    }

                    $targetValid = false;
                    $target = self::cleanString($item['target'] ?? null);
                    if ($target === null || ! array_key_exists($target, self::CONTRAINDICATIONS)) {
                        $errors["attributes.contraindications.{$index}.target"][] = 'Wybierz grupę przeciwwskazania z zamkniętej listy.';
                    } elseif (isset($seenTargets[$target])) {
                        $errors["attributes.contraindications.{$index}.target"][] = 'To przeciwwskazanie zostało już dodane.';
                    } else {
                        $seenTargets[$target] = true;
                        $targetValid = true;
                    }

                    $quoteValid = false;
                    $itemQuote = self::cleanString($item['quote'] ?? null);
                    if ($itemQuote === null) {
                        $errors["attributes.contraindications.{$index}.quote"][] = 'Każde przeciwwskazanie wymaga cytatu potwierdzającego.';
                    } elseif (mb_strlen($itemQuote) > 500) {
                        $errors["attributes.contraindications.{$index}.quote"][] = 'Cytat przeciwwskazania może mieć maksymalnie 500 znaków.';
                    } else {
                        $quoteValid = true;
                    }

                    if ($targetValid && $quoteValid) {
                        $contraindications[] = ['target' => $target, 'quote' => $itemQuote];
                    }
                }

                if ($contraindications !== []) {
                    $normalized['contraindications'] = $contraindications;
                }
            }
        }

        if ($normalized === [] && $errors === []) {
            $errors['attributes'][] = 'Uzupełnij co najmniej jeden parametr strukturalny preparatu.';
        }

        return ['attributes' => $normalized, 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{attributes: array<string, mixed>, errors: array<string, list<string>>}
     */
    private static function validateFaunaAttributes(array $attributes): array
    {
        $errors = self::rejectUnknownKeys($attributes, array_keys(self::FAUNA_PARAMETERS));
        $normalized = [];

        foreach (self::FAUNA_PARAMETERS as $param => $label) {
            if (! array_key_exists($param, $attributes) || $attributes[$param] === null) {
                continue;
            }

            $range = $attributes[$param];
            if (! is_array($range)) {
                $errors["attributes.{$param}"][] = "Zakres dla {$label} musi zawierać pola min i max.";

                continue;
            }

            $rawMin = $range['min'] ?? null;
            $rawMax = $range['max'] ?? null;

            $minBlank = $rawMin === null || (is_string($rawMin) && trim($rawMin) === '');
            $maxBlank = $rawMax === null || (is_string($rawMax) && trim($rawMax) === '');
            if ($minBlank && $maxBlank) {
                continue;
            }

            foreach (array_keys($range) as $rangeKey) {
                if (! in_array($rangeKey, ['min', 'max'], true)) {
                    $errors["attributes.{$param}"][] = "Nieznane pole zakresu {$label}: {$rangeKey}.";
                }
            }

            $min = self::parseNumeric($rawMin);
            $max = self::parseNumeric($rawMax);

            if ($min === null) {
                $errors["attributes.{$param}.min"][] = "Podaj wartość minimalną dla {$label}.";
            }
            if ($max === null) {
                $errors["attributes.{$param}.max"][] = "Podaj wartość maksymalną dla {$label}.";
            }

            if ($min === null || $max === null) {
                continue;
            }

            [$lo, $hi] = self::FAUNA_BOUNDS[$param];
            if ($min < $lo || $min > $hi) {
                $errors["attributes.{$param}.min"][] = "Wartość minimalna dla {$label} musi mieścić się w zakresie {$lo}–{$hi}.";
            }
            if ($max < $lo || $max > $hi) {
                $errors["attributes.{$param}.max"][] = "Wartość maksymalna dla {$label} musi mieścić się w zakresie {$lo}–{$hi}.";
            }
            if ($max < $min) {
                $errors["attributes.{$param}.max"][] = "Wartość maksymalna dla {$label} nie może być mniejsza niż minimalna.";
            }

            $normalized[$param] = ['min' => $min, 'max' => $max];
        }

        if ($normalized === [] && $errors === []) {
            $errors['attributes'][] = 'Podaj co najmniej jeden zakres parametrów wody dla gatunku.';
        }

        return ['attributes' => $normalized, 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{attributes: array<string, mixed>, errors: array<string, list<string>>}
     */
    private static function validateEquipmentAttributes(array $attributes, ?string $ean): array
    {
        $errors = self::rejectUnknownKeys($attributes, ['power_w', 'flow_l_h', 'water_type']);
        $normalized = [];

        $power = self::parsePositiveFloat($attributes['power_w'] ?? null, 10000.0, 'attributes.power_w', 'Moc (W) musi być liczbą większą od zera (maks. 10000).', $errors);
        if ($power !== null) {
            $normalized['power_w'] = $power;
        }

        $flow = self::parsePositiveFloat($attributes['flow_l_h'] ?? null, 100000.0, 'attributes.flow_l_h', 'Wydajność (l/h) musi być liczbą większą od zera (maks. 100000).', $errors);
        if ($flow !== null) {
            $normalized['flow_l_h'] = $flow;
        }

        $waterType = self::cleanString($attributes['water_type'] ?? null);
        if ($waterType !== null) {
            if (! array_key_exists($waterType, self::WATER_TYPES)) {
                $errors['attributes.water_type'][] = 'Wybierz rodzaj wody z zamkniętej listy (freshwater, marine, both).';
            } else {
                $normalized['water_type'] = $waterType;
            }
        }

        if ($normalized === [] && $ean === null && $errors === []) {
            $errors['attributes'][] = 'Uzupełnij co najmniej jeden parametr sprzętu albo kod EAN.';
        }

        return ['attributes' => $normalized, 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{attributes: array<string, mixed>, errors: array<string, list<string>>}
     */
    private static function validateFloraAttributes(array $attributes): array
    {
        $errors = self::rejectUnknownKeys($attributes, []);

        return ['attributes' => [], 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $allowedKeys
     * @return array<string, list<string>>
     */
    private static function rejectUnknownKeys(array $attributes, array $allowedKeys): array
    {
        $errors = [];
        foreach (array_keys($attributes) as $key) {
            if (! in_array($key, $allowedKeys, true)) {
                $errors['attributes'][] = "Nieznane pole atrybutu: {$key}.";
            }
        }

        return $errors;
    }

    private static function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function parseNumeric(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed !== '' && is_numeric($trimmed)) {
                return (float) $trimmed;
            }
        }

        return null;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private static function parsePositiveFloat(mixed $value, float $max, string $key, string $message, array &$errors): ?float
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }
        $number = self::parseNumeric($value);
        if ($number === null || $number <= 0.0 || $number > $max) {
            $errors[$key][] = $message;

            return null;
        }

        return $number;
    }
}
