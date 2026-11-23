<?php

namespace App\Domain\Advisor\Actions;

use App\Domain\Advisor\Contracts\Compatibility;
use App\Domain\Advisor\Data\CompareSpeciesData;
use App\Domain\Advisor\Data\TogetherComparisonResult;
use App\Domain\Advisor\Engine\Compatibility\StockLine;
use App\Domain\Advisor\Engine\Compatibility\TankContext;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;

/** Porównanie katalogowe 2–8 gatunków fauny bez zbiornika i bez zapisu w bazie. */
class CompareSpeciesTogether
{
    public function __construct(
        private Catalog $catalog,
        private Compatibility $compatibility,
        private Settings $settings,
    ) {}

    public function handle(CompareSpeciesData $data): TogetherComparisonResult
    {
        $limits = $this->settings->get(SettingKey::TogetherSpeciesLimits);
        $minSpecies = (int) $limits['min'];
        $maxSpecies = (int) $limits['max'];
        $searchMinChars = (int) $this->settings->get(SettingKey::SearchMinChars);

        $selectedIds = [];
        $seen = [];
        foreach ($data->ids as $raw) {
            $id = strtoupper(trim($raw));
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $selectedIds[] = $id;
        }

        /** @var array<string, CatalogEntry> $foundById */
        $foundById = [];
        if ($selectedIds !== []) {
            foreach ($this->catalog->snapshot(Area::Fauna, ids: $selectedIds) as $entry) {
                $foundById[$entry->id] = $entry;
            }
        }

        $species = [];
        $unknownIds = [];
        foreach ($selectedIds as $id) {
            if (isset($foundById[$id])) {
                $species[] = $foundById[$id];
            } else {
                $unknownIds[] = $id;
            }
        }

        $tooMany = count($selectedIds) > $maxSpecies;
        $tooFew = ! $tooMany && $selectedIds !== [] && count($species) < $minSpecies;

        $report = null;
        if (! $tooMany && count($species) >= $minSpecies) {
            $stock = array_map(
                fn (string $id) => new StockLine(GuideArea::Fauna, $id),
                $selectedIds,
            );
            $report = $this->compatibility->check(new TankContext(null), $stock);
        }

        $search = trim((string) ($data->search ?? ''));
        $searchResults = $search !== '' ? $this->catalog->search($search, [Area::Fauna]) : [];

        return new TogetherComparisonResult(
            minSpecies: $minSpecies,
            maxSpecies: $maxSpecies,
            selectedIds: $selectedIds,
            species: $species,
            unknownIds: $unknownIds,
            tooMany: $tooMany,
            tooFew: $tooFew,
            report: $report,
            searchResults: $searchResults,
            searchMinChars: $searchMinChars,
        );
    }
}
