<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Advisor\Contracts\Compatibility;
use App\Domain\Advisor\Engine\Compatibility\StockLine;
use App\Domain\Advisor\Engine\Evidence;
use App\Domain\Advisor\Engine\FaunaRecord;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Advisor\Engine\WaterHints\ContraindicationGuard;
use App\Domain\Advisor\Engine\WaterHints\Hint;
use App\Domain\Advisor\Engine\WaterHints\PhProductCheck;
use App\Domain\Advisor\Engine\WaterHints\SpecRange;
use App\Domain\Advisor\Engine\WaterHints\WaterHintsEngine;
use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\WaterHintProduct;
use App\Domain\Aquarium\Data\WaterHintsReport;
use App\Domain\Aquarium\MeasurementWarnings;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Contracts\StructuredSpecs;
use App\Domain\KnowledgeBase\Contracts\WaterHintProducts;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Data\StructuredSpec;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\SpecSourceKind;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;

class BuildWaterHints
{
    /** @var list<string> */
    private const STRUCTURED_PARAMETERS = ['ph', 'temp_c', 'kh', 'gh', 'no3', 'po4'];

    /** @var list<string> */
    private const PRODUCT_PARAMETERS = ['ph', 'kh', 'gh', 'no3', 'po4'];

    /** @var list<string> Obszary pielęgnacji wykluczone z poleceń po pomiarze wody. */
    private const EXCLUDED_CARE_AREAS = [
        'Podłoża',
        'Leki',
        'Kamienie i skały',
        'Karmniki',
        'Narzędzia do cięcia',
        'Czyszczenie szyb',
        'Pokarmy dla ryb',
        'Pokarmy dla krewetek',
    ];

    public function __construct(
        private Access $access,
        private Compatibility $compatibility,
        private Catalog $catalog,
        private Settings $settings,
        private WaterHintsEngine $engine,
        private MeasurementWarnings $warnings,
        private WaterHintProducts $products,
        private StructuredSpecs $structuredSpecs,
        private ContraindicationGuard $contraindications,
    ) {}

    public function handle(User $actor, int $tankId): ?WaterHintsReport
    {
        $tank = $this->access->tank($actor, $tankId, Feature::WaterHints);
        $measurement = $tank->latestMeasurement;
        if ($measurement === null) {
            return null;
        }

        $livestock = $tank->livestock()->orderBy('id')->get();
        /** @var list<StockLine> $stock */
        $stock = $livestock
            ->filter(fn (Livestock $row): bool => $row->area === Area::Fauna)
            ->map(fn (Livestock $row): StockLine => new StockLine(GuideArea::Fauna, $row->catalog_id, $row->quantity))
            ->values()->all();
        /** @var list<string> $floraIds */
        $floraIds = $livestock
            ->filter(fn (Livestock $row): bool => $row->area === Area::Flora)
            ->map(fn (Livestock $row): string => $row->catalog_id)
            ->values()->all();

        $batch = $this->catalog->activeBatchId();
        [$fauna, $unknownFauna] = $this->compatibility->waterRequirements($stock, $batch);
        $hasFlora = $floraIds !== [];
        $knownFlora = $batch !== null && $hasFlora ? $this->catalog->labels(Area::Flora, $floraIds) : [];
        $unknownFlora = count(array_filter($floraIds, fn (string $id): bool => ! isset($knownFlora[$id])));

        $specsByFauna = $this->faunaSpecRanges($fauna);
        $readings = [
            'ph' => $measurement->ph === null ? null : (float) $measurement->ph,
            'temp_c' => $measurement->temp_c === null ? null : (float) $measurement->temp_c,
            'kh' => $measurement->kh === null ? null : (float) $measurement->kh,
            'gh' => $measurement->gh === null ? null : (float) $measurement->gh,
            'no3' => $measurement->no3 === null ? null : (float) $measurement->no3,
            'po4' => $measurement->po4 === null ? null : (float) $measurement->po4,
        ];
        /** @var list<string> $catalogChecks */
        $catalogChecks = array_values(array_filter(
            (array) $this->settings->get(SettingKey::WaterHintsChecks),
            is_string(...),
        ));
        $hints = $this->engine->assess(
            $readings,
            $fauna,
            $unknownFauna,
            array_values(array_unique([...$catalogChecks, 'kh', 'gh', 'no3', 'po4'])),
            $batch,
            $specsByFauna,
        );

        $careSpecs = $batch === null ? [] : $this->structuredSpecs->forArea(Area::Care);
        /** @var array<string, CatalogEntry> $careEntries */
        $careEntries = [];
        if ($batch !== null && $careSpecs !== []) {
            foreach ($this->catalog->snapshot(Area::Care, $batch, array_keys($careSpecs), withDescriptions: true) as $entry) {
                $careEntries[$entry->id] = $entry;
            }
        }

        $productsByParameter = [];
        $reasonsByParameter = [];
        foreach ($hints as $hint) {
            if (! in_array($hint->parameter, self::PRODUCT_PARAMETERS, true)) {
                continue;
            }
            [$matchedProduct, $blockReason] = $this->productFor(
                $measurement,
                $hint,
                $batch,
                $fauna,
                $unknownFauna,
                $hasFlora,
                $unknownFlora,
                $careSpecs,
                $careEntries,
            );
            if ($matchedProduct !== null) {
                $productsByParameter[$hint->parameter] = $matchedProduct;
            } elseif ($blockReason !== null) {
                $reasonsByParameter[$hint->parameter] = $blockReason;
            }
        }

        $ageInDays = max(0, (int) $measurement->measured_at->diffInDays(now()));
        $primaryProduct = $productsByParameter['ph'] ?? (array_values($productsByParameter)[0] ?? null);
        $primaryReason = array_key_exists('ph', $productsByParameter)
            ? null
            : ($reasonsByParameter['ph'] ?? (array_values($reasonsByParameter)[0] ?? null));

        return new WaterHintsReport(
            $measurement,
            $hints,
            $ageInDays,
            $ageInDays >= $this->settings->get(SettingKey::WaterHintsStaleDays),
            $primaryProduct,
            $primaryReason,
            $productsByParameter,
            $reasonsByParameter,
        );
    }

    /**
     * @param  list<FaunaRecord>  $fauna
     * @return array<string, array<string, SpecRange>>
     */
    private function faunaSpecRanges(array $fauna): array
    {
        if ($fauna === []) {
            return [];
        }

        $raw = $this->structuredSpecs->forRecords(Area::Fauna, array_map(fn (FaunaRecord $r) => $r->id, $fauna));
        $result = [];
        foreach ($raw as $id => $spec) {
            foreach (self::STRUCTURED_PARAMETERS as $param) {
                $range = $spec->range($param);
                if ($range !== null) {
                    $result[$id][$param] = new SpecRange(
                        $range['min'],
                        $range['max'],
                        $spec->sourceKind->value,
                        $spec->sourceKind->label(),
                        $spec->sourceRef,
                        $spec->evidenceQuote,
                    );
                }
            }
        }

        return $result;
    }

    /**
     * @param  list<FaunaRecord>  $fauna
     * @param  array<string, StructuredSpec>  $careSpecs
     * @param  array<string, CatalogEntry>  $careEntries
     * @return array{?WaterHintProduct, ?string}
     */
    private function productFor(
        Measurement $measurement,
        Hint $hint,
        ?int $batch,
        array $fauna,
        int $unknownFauna,
        bool $hasFlora,
        int $unknownFlora,
        array $careSpecs,
        array $careEntries,
    ): array {
        $param = $hint->parameter;
        $isPh = $param === 'ph';

        if ($isPh && $hint->phProduct === PhProductCheck::MissingReading) {
            return [null, PhProductCheck::MissingReading->value];
        }

        if ($isPh) {
            $gateReason = $this->measurementGateReason($measurement, 'ph');
            if ($gateReason !== null) {
                return [null, $gateReason];
            }
        }

        if ($hint->productDirection === null) {
            $reason = $isPh ? ($hint->phProduct?->value ?: $hint->productBlockReason) : $hint->productBlockReason;
            if ($reason === null) {
                return [null, null];
            }

            $hasIncomplete = $unknownFauna > 0 || $unknownFlora > 0 || $this->hasMissingKind($fauna);
            if ($hasIncomplete && $this->hasContraindicatedSpecForParameter($param, $careSpecs, $careEntries)) {
                return [null, "{$reason} — ukryto preparat z przeciwwskazaniem (niepełne dane obsady)"];
            }

            return $isPh ? [null, $reason] : [null, null];
        }

        $candidates = $this->candidatesFor($param, $hint->productDirection, $batch, $careSpecs, $careEntries);
        if ($candidates === []) {
            if ($isPh) {
                return $hint->productDirection === 'down'
                    ? [null, 'brak preparatu z potwierdzonym opisem w katalogu']
                    : [null, PhProductCheck::NotAboveAll->value];
            }

            return [null, null];
        }

        if (! $isPh) {
            $gateReason = $this->measurementGateReason($measurement, $param);
            if ($gateReason !== null) {
                return [null, $gateReason];
            }
        }

        $blockedReason = null;
        foreach ($candidates as $candidate) {
            $block = $this->contraindications->blockReason(
                $candidate['contraindications'],
                $fauna,
                $unknownFauna,
                $hasFlora,
                $unknownFlora,
            );
            if ($block === null) {
                return [$candidate['product'], null];
            }
            $blockedReason ??= $block;
        }

        return [null, $blockedReason];
    }

    private function measurementGateReason(Measurement $measurement, string $param): ?string
    {
        $now = now();
        $maxHours = (int) $this->settings->get(SettingKey::WaterHintProductMaxAgeHours);
        $label = $param === 'ph' ? 'pH' : (Measurement::LABELS[$param] ?? $param);

        return match (true) {
            $measurement->measured_at->gt($now) => 'data pomiaru jest w przyszłości',
            $measurement->measured_at->lt($now->copy()->subHours($maxHours)) => "pomiar ma więcej niż {$maxHours} godz.",
            $this->warnings->for($measurement, $param) !== [] => "odczyt {$label} ma ostrzeżenie",
            default => null,
        };
    }

    /**
     * @param  array<string, StructuredSpec>  $careSpecs
     * @param  array<string, CatalogEntry>  $careEntries
     * @return list<array{product: WaterHintProduct, contraindications: list<array{target: string, quote: string}>}>
     */
    private function candidatesFor(
        string $param,
        string $direction,
        ?int $batch,
        array $careSpecs,
        array $careEntries,
    ): array {
        if ($batch === null) {
            return [];
        }

        $candidates = [];
        foreach ($careSpecs as $id => $spec) {
            if (($spec->attributes['parameter'] ?? null) !== $param || ($spec->attributes['direction'] ?? null) !== $direction) {
                continue;
            }
            $entry = $careEntries[$id] ?? null;
            if ($entry === null || ! $this->isAllowedCareEntry($entry)) {
                continue;
            }

            $source = $spec->sourceRef !== null && $spec->sourceRef !== ''
                ? "{$spec->sourceKind->label()} ({$spec->sourceRef})"
                : $spec->sourceKind->label();
            $column = $spec->sourceKind === SpecSourceKind::CatalogQuote ? 'opis' : 'specyfikacja preparatu';

            $candidates[] = [
                'product' => new WaterHintProduct(
                    $entry->name,
                    new Evidence($column, $spec->evidenceQuote, $entry->id, $batch, $source),
                    $spec->sourceKind->label(),
                    $spec->sourceRef,
                ),
                'contraindications' => $spec->contraindications(),
            ];
        }

        if ($param === 'ph' && $direction === 'down') {
            foreach ($this->products->loweringPh($batch) as $id => $entry) {
                if ($entry === null || isset($careSpecs[$id])) {
                    continue;
                }
                $candidates[] = [
                    'product' => new WaterHintProduct(
                        $entry->name,
                        new Evidence('opis', $entry->fields['opis'], $entry->id, $batch),
                    ),
                    'contraindications' => [],
                ];
            }
        }

        return $candidates;
    }

    private function isAllowedCareEntry(CatalogEntry $entry): bool
    {
        if (($entry->fields['rodzaj_zadania'] ?? null) === 'Testowanie wody') {
            return false;
        }

        return ! in_array($entry->fields['obszar'] ?? null, self::EXCLUDED_CARE_AREAS, true);
    }

    /**
     * @param  array<string, StructuredSpec>  $careSpecs
     * @param  array<string, CatalogEntry>  $careEntries
     */
    private function hasContraindicatedSpecForParameter(string $param, array $careSpecs, array $careEntries): bool
    {
        foreach ($careSpecs as $id => $spec) {
            if (($spec->attributes['parameter'] ?? null) !== $param) {
                continue;
            }
            $entry = $careEntries[$id] ?? null;
            if ($entry !== null && $this->isAllowedCareEntry($entry) && $spec->contraindications() !== []) {
                return true;
            }
        }

        return false;
    }

    /** @param list<FaunaRecord> $fauna */
    private function hasMissingKind(array $fauna): bool
    {
        foreach ($fauna as $record) {
            if (! in_array(mb_strtolower(trim($record->kind)), ['ryba', 'skorupiak', 'ślimak'], true)) {
                return true;
            }
        }

        return false;
    }
}
