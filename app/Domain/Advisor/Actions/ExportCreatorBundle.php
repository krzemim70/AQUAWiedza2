<?php

namespace App\Domain\Advisor\Actions;

use App\Domain\Advisor\Engine\Co2Need;
use App\Domain\Advisor\Engine\Compatibility\Check;
use App\Domain\Advisor\Engine\FaunaRecord;
use App\Domain\Advisor\Engine\FishBand;
use App\Domain\Advisor\Engine\FloraRecord;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\Temperament;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Domain\Advisor\Engine\WaterHints\DescriptionRange;
use App\Domain\Advisor\Engine\WaterHints\Range;
use App\Domain\Advisor\Engine\Zone;
use App\Domain\Advisor\FaunaPool;
use App\Domain\Advisor\FloraPool;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;

/** Eksportuje statyczny pakiet katalogu i reguł doradcy dla silnika w przeglądarce (bez długich opisów artykułowych). */
class ExportCreatorBundle
{
    /** @var list<string> */
    private const DEFAULT_TANK_TYPES = [
        'Nano / Krewetkarium',
        'Ogólne / Towarzyskie',
        'Roślinne',
        'Biotop Twardowodny (Malawi/Tanganika)',
        'Specjalistyczne',
    ];

    /** @var list<string> */
    private const EQUIPMENT_CATEGORIES = [
        'Filtracja',
        'Oświetlenie LED',
        'Ogrzewanie',
        'Dozowanie CO2',
    ];

    public function __construct(
        private Catalog $catalog,
        private FaunaPool $fauna,
        private FloraPool $flora,
        private Settings $settings,
    ) {}

    /** @return array<string, mixed> */
    public function relevantSettings(): array
    {
        return [
            SettingKey::CompatSeverity->value => $this->settings->get(SettingKey::CompatSeverity),
            SettingKey::CalculatorLimits->value => $this->settings->get(SettingKey::CalculatorLimits),
            SettingKey::FitThresholds->value => $this->settings->get(SettingKey::FitThresholds),
            SettingKey::SearchMinChars->value => $this->settings->get(SettingKey::SearchMinChars),
        ];
    }

    public function version(?int $batchId = null): string
    {
        $activeBatchId = $this->catalog->activeBatchId();
        $resolvedBatchId = $batchId ?? $activeBatchId;
        if ($resolvedBatchId === null) {
            return 'empty';
        }

        $signature = ($batchId === null || $batchId === $activeBatchId)
            ? ($this->catalog->contentSignature() ?? (string) $resolvedBatchId)
            : (string) $resolvedBatchId;

        $settingsJson = json_encode($this->relevantSettings(), JSON_THROW_ON_ERROR);

        return $resolvedBatchId.'-'.substr(hash('sha256', $signature.$settingsJson), 0, 12);
    }

    /** @return array<string, mixed> */
    public function handle(?int $batchId = null): array
    {
        $activeBatchId = $this->catalog->activeBatchId();
        $resolvedBatchId = $batchId ?? $activeBatchId;
        $version = $this->version($batchId);

        $faunaRecords = $resolvedBatchId === null
            ? []
            : $this->fauna->records($resolvedBatchId, withDescriptions: true);

        $floraEntries = $resolvedBatchId === null ? [] : $this->catalog->snapshot(Area::Flora, $resolvedBatchId);
        $floraRecordsById = [];
        if ($resolvedBatchId !== null && $resolvedBatchId === $activeBatchId) {
            foreach ($this->flora->records() as $record) {
                $floraRecordsById[$record->id] = $record;
            }
        }

        $equipmentEntries = $resolvedBatchId === null ? [] : $this->catalog->snapshot(Area::Equipment, $resolvedBatchId);
        $careEntries = $resolvedBatchId === null ? [] : $this->catalog->snapshot(Area::Care, $resolvedBatchId);

        $tankTypes = $this->collectTankTypes($faunaRecords, $floraEntries, $equipmentEntries);
        $tankTypeIndex = array_flip($tankTypes);

        $faunaThumbs = $this->catalog->thumbnails(Area::Fauna, array_map(fn (FaunaRecord $r) => $r->id, $faunaRecords), $resolvedBatchId);
        $floraThumbs = $this->catalog->thumbnails(Area::Flora, array_map(fn (CatalogEntry $e) => $e->id, $floraEntries), $resolvedBatchId);
        $equipmentThumbs = $this->catalog->thumbnails(Area::Equipment, array_map(fn (CatalogEntry $e) => $e->id, $equipmentEntries), $resolvedBatchId);
        $careThumbs = $this->catalog->thumbnails(Area::Care, array_map(fn (CatalogEntry $e) => $e->id, $careEntries), $resolvedBatchId);

        return [
            'version' => $version,
            'batchId' => $resolvedBatchId,
            'dicts' => [
                'tankBands' => array_map(fn (TankBand $band) => [
                    'code' => $band->value,
                    'index' => $band->index(),
                    'label' => $band->label(),
                    'volume' => $band->volume(),
                ], TankBand::cases()),
                'fishBands' => array_map(fn (FishBand $band) => $band->label(), FishBand::cases()),
                'tempBands' => array_map(fn (TemperatureBand $band) => [
                    'code' => $band->value,
                    'index' => $band->index(),
                    'label' => $band->label(),
                    'rangeLabel' => Range::forBand($band)->label(' °C'),
                ], TemperatureBand::cases()),
                'phBands' => array_map(fn (PhBand $band) => [
                    'code' => $band->value,
                    'index' => $band->index(),
                    'label' => $band->label(),
                ], PhBand::cases()),
                'temperaments' => array_map(fn (Temperament $t) => $t->label(), Temperament::cases()),
                'zones' => array_map(fn (Zone $z) => $z->label(), Zone::cases()),
                'co2Needs' => array_map(fn (Co2Need $c) => $c->label(), Co2Need::cases()),
                'tankTypes' => $tankTypes,
            ],
            'rules' => $this->rules(),
            'fauna' => array_map(
                fn (FaunaRecord $record) => $this->exportFauna($record, $faunaThumbs[$record->id] ?? null, $tankTypeIndex),
                $faunaRecords,
            ),
            'flora' => array_map(
                fn (CatalogEntry $entry) => $this->exportFlora(
                    $entry,
                    $floraRecordsById[$entry->id] ?? $this->floraRecordFromEntry($entry),
                    $floraThumbs[$entry->id] ?? null,
                    $tankTypeIndex,
                ),
                $floraEntries,
            ),
            'equipment' => array_map(
                fn (CatalogEntry $entry) => $this->exportEquipment($entry, $equipmentThumbs[$entry->id] ?? null, $tankTypeIndex),
                $equipmentEntries,
            ),
            'care' => array_map(
                fn (CatalogEntry $entry) => $this->exportCare($entry, $careThumbs[$entry->id] ?? null),
                $careEntries,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        $rawLimits = $this->settings->get(SettingKey::CalculatorLimits);
        $limits = is_array($rawLimits) ? $rawLimits : [];
        $dim = is_array($limits['dimension_cm'] ?? null) ? $limits['dimension_cm'] : ['min' => 1, 'max' => 1000];
        $vol = is_array($limits['volume_l'] ?? null) ? $limits['volume_l'] : ['min' => 1, 'max' => 65535];

        $rawThresholds = $this->settings->get(SettingKey::FitThresholds);
        $fitThresholds = is_array($rawThresholds) ? array_values(array_map('intval', $rawThresholds)) : [50, 70, 85];

        $rawSeverity = $this->settings->get(SettingKey::CompatSeverity);
        $compatSeverity = is_array($rawSeverity) ? $rawSeverity : [];

        return [
            'weights' => [4, 3, 2, 1],
            'maxFishGap' => 3,
            'fitThresholds' => $fitThresholds,
            'searchMinChars' => (int) $this->settings->get(SettingKey::SearchMinChars),
            'compatSeverity' => $compatSeverity,
            'compatChecks' => array_map(fn (Check $check) => $check->value, Check::cases()),
            'limits' => [
                'dimension_cm' => ['min' => (int) $dim['min'], 'max' => (int) $dim['max']],
                'volume_l' => ['min' => (int) $vol['min'], 'max' => (int) $vol['max']],
            ],
            'tankBands' => array_map(fn (TankBand $band) => [
                'code' => $band->value,
                'maxLiters' => match ($band) {
                    TankBand::Nano => 39,
                    TankBand::Small => 100,
                    TankBand::Medium => 250,
                    TankBand::Large => 500,
                    TankBand::Huge => null,
                },
                'volume' => $band->volume(),
            ], TankBand::cases()),
            'equipmentCategories' => self::EQUIPMENT_CATEGORIES,
            'equipmentMissingSpecsNote' => 'Moc i przepływ: brak danych w bazie — sprawdź na opakowaniu.',
            'careGroups' => [
                'startTaskCategory' => 'Start / przygotowanie',
                'fishFoodDomain' => 'Pokarmy dla ryb',
                'shrimpFoodDomain' => 'Pokarmy dla krewetek',
                'plantDomains' => ['Nawozy', 'Preparaty wzbogacające'],
                'testingTaskKind' => 'Testowanie wody',
                'snailFoodMissingText' => 'Pokarmy dla ślimaków: brak danych w bazie.',
            ],
        ];
    }

    /**
     * @param  list<FaunaRecord>  $fauna
     * @param  list<CatalogEntry>  $flora
     * @param  list<CatalogEntry>  $equipment
     * @return list<string>
     */
    private function collectTankTypes(array $fauna, array $flora, array $equipment): array
    {
        $types = self::DEFAULT_TANK_TYPES;
        $known = array_flip($types);
        $lists = [];
        foreach ($fauna as $record) {
            $lists[] = $record->tankTypes;
        }
        foreach ($flora as $entry) {
            $lists[] = $entry->values['TypAkwarium'] ?? [];
        }
        foreach ($equipment as $entry) {
            $lists[] = $entry->values['TypAkwarium'] ?? [];
        }

        foreach ($lists as $values) {
            foreach ($values as $value) {
                if ($value !== '' && $value !== 'Wszystkie' && ! isset($known[$value])) {
                    $known[$value] = count($types);
                    $types[] = $value;
                }
            }
        }

        return $types;
    }

    /**
     * @param  array<string, int>  $tankTypeIndex
     * @return array<string, mixed>
     */
    private function exportFauna(FaunaRecord $record, ?string $thumb, array $tankTypeIndex): array
    {
        return [
            'id' => $record->id,
            'name' => $record->name,
            'latin' => $record->latinName,
            'search' => Catalog::fold(trim("{$record->name} {$record->latinName} {$record->id}")),
            'thumb' => $thumb,
            'kind' => $record->kind,
            'size' => $record->size?->index(),
            'temp' => $record->temperature?->index(),
            'ph' => array_map(fn (PhBand $b) => $b->index(), $record->ph),
            'unknownPh' => $record->hasUnknownPhBand,
            'tankSizes' => array_map(fn (TankBand $b) => $b->index(), $record->tankSizes),
            'anySize' => $record->anyTankSize,
            'tankTypes' => $this->mapTankTypes($record->tankTypes, $tankTypeIndex),
            'anyType' => in_array('Wszystkie', $record->tankTypes, true),
            'temperament' => $record->temperament?->index(),
            'zone' => $record->zone?->index(),
            'discrepancies' => $this->faunaDiscrepancies($record),
        ];
    }

    /**
     * @return list<array{column: string, bandRange: string, excerpt: string}>
     */
    private function faunaDiscrepancies(FaunaRecord $record): array
    {
        if ($record->description === null || $record->description === '') {
            return [];
        }

        $discrepancies = [];

        if (count($record->ph) === 1 && ! $record->hasUnknownPhBand) {
            $range = Range::forBand($record->ph[0]);
            $described = DescriptionRange::extract($record->description, true);
            if ($described !== null && ! $range->hasBounds($described[1], $described[2])) {
                $discrepancies[] = [
                    'column' => 'pH',
                    'bandRange' => $range->label(''),
                    'excerpt' => $described[0],
                ];
            }
        }

        if ($record->temperature !== null) {
            $range = Range::forBand($record->temperature);
            $described = DescriptionRange::extract($record->description, false);
            if ($described !== null && ! $range->hasBounds($described[1], $described[2])) {
                $discrepancies[] = [
                    'column' => 'Temperatura',
                    'bandRange' => $range->label(' °C'),
                    'excerpt' => $described[0],
                ];
            }
        }

        return $discrepancies;
    }

    /**
     * @param  array<string, int>  $tankTypeIndex
     * @return array<string, mixed>
     */
    private function exportFlora(CatalogEntry $entry, FloraRecord $record, ?string $thumb, array $tankTypeIndex): array
    {
        return [
            'id' => $record->id,
            'name' => $record->name,
            'latin' => $record->latinName,
            'search' => Catalog::fold(trim("{$record->name} {$record->latinName} {$record->id}")),
            'thumb' => $thumb,
            'co2' => $record->co2?->index(),
            'difficulty' => trim($entry->fields['Trudność'] ?? ''),
            'plantZone' => trim($entry->fields['Strefa'] ?? ''),
            'plantSize' => trim($entry->fields['Wielkość'] ?? ''),
            'tankSizes' => array_map(fn (TankBand $b) => $b->index(), $record->tankSizes),
            'anySize' => $record->anyTankSize,
            'tankTypes' => $this->mapTankTypes($record->tankTypes, $tankTypeIndex),
            'anyType' => in_array('Wszystkie', $record->tankTypes, true),
        ];
    }

    private function floraRecordFromEntry(CatalogEntry $entry): FloraRecord
    {
        return new FloraRecord(
            $entry->id,
            $entry->name,
            (string) $entry->nameLatin,
            Co2Need::fromLabel($entry->fields['CO2'] ?? null),
            array_values(array_filter(array_map(TankBand::fromLabel(...), $entry->values['WielkośćAkwarium'] ?? []))),
            in_array('Wszystkie', $entry->values['WielkośćAkwarium'] ?? [], true),
            $entry->values['TypAkwarium'] ?? [],
        );
    }

    /**
     * @param  array<string, int>  $tankTypeIndex
     * @return array<string, mixed>
     */
    private function exportEquipment(CatalogEntry $entry, ?string $thumb, array $tankTypeIndex): array
    {
        $producer = trim($entry->fields['producent'] ?? '');
        $brand = trim($entry->fields['Marka'] ?? '');
        $kind = trim($entry->fields['rodzaj'] ?? '');
        $sizes = $entry->values['WielkośćAkwarium'] ?? [];
        $types = $entry->values['TypAkwarium'] ?? [];

        return [
            'id' => $entry->id,
            'name' => $entry->name,
            'brand' => $brand,
            'producer' => $producer,
            'category' => trim($entry->fields['kategoria_glowna'] ?? ''),
            'kind' => $kind,
            'level' => trim($entry->fields['Poziom_doświadczenia'] ?? ''),
            'shortDesc' => trim($entry->fields['opis_krotki'] ?? ''),
            'search' => Catalog::fold(trim(implode(' ', array_filter([$entry->name, $producer, $brand, $kind, $entry->id])))),
            'thumb' => $thumb,
            'tankSizes' => array_values(array_map(
                fn (TankBand $b) => $b->index(),
                array_filter(array_map(TankBand::fromLabel(...), $sizes)),
            )),
            'anySize' => in_array('Wszystkie', $sizes, true),
            'tankTypes' => $this->mapTankTypes($types, $tankTypeIndex),
            'anyType' => in_array('Wszystkie', $types, true),
        ];
    }

    /** @return array<string, mixed> */
    private function exportCare(CatalogEntry $entry, ?string $thumb): array
    {
        $producer = trim($entry->fields['producent'] ?? '');
        $brand = trim($entry->fields['Marka'] ?? '');
        $taskKind = trim($entry->fields['rodzaj_zadania'] ?? '');
        $domain = trim($entry->fields['obszar'] ?? '');

        return [
            'id' => $entry->id,
            'name' => $entry->name,
            'brand' => $brand,
            'producer' => $producer,
            'taskCategory' => trim($entry->fields['kategoria_zadania'] ?? ''),
            'taskKind' => $taskKind,
            'domain' => $domain,
            'desc' => trim($entry->fields['opis'] ?? ''),
            'search' => Catalog::fold(trim(implode(' ', array_filter([$entry->name, $producer, $brand, $taskKind, $domain, $entry->id])))),
            'thumb' => $thumb,
        ];
    }

    /**
     * @param  list<string>  $labels
     * @param  array<string, int>  $tankTypeIndex
     * @return list<int>
     */
    private function mapTankTypes(array $labels, array $tankTypeIndex): array
    {
        $indices = [];
        foreach ($labels as $label) {
            if ($label !== 'Wszystkie' && isset($tankTypeIndex[$label])) {
                $indices[] = $tankTypeIndex[$label];
            }
        }

        return $indices;
    }
}
