<?php

namespace App\Domain\Advisor;

use App\Domain\Advisor\Engine\Guide;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Advisor\Engine\Item;
use App\Domain\Advisor\Engine\Rules;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;

/** Adapter: rekordy czterech obszarów z wybranej partii katalogu i reguły z ustawień jako wartości dla silnika kreatora. */
class GuideSource
{
    public function __construct(private Catalog $catalog, private Settings $settings) {}

    /** Partia, na której liczymy: wskazana albo aktywna (null, gdy nie ma żadnej). */
    public function batchId(?int $batch = null): ?int
    {
        return $batch ?? $this->catalog->activeBatchId();
    }

    public function guide(?int $batch): Guide
    {
        $items = [];
        foreach (Area::cases() as $area) {
            foreach ($this->catalog->snapshot($area, $batch) as $entry) {
                $items[] = self::item($entry);
            }
        }

        return new Guide($items, $this->rules());
    }

    public function rules(): Rules
    {
        $output = $this->settings->get(SettingKey::OutputPolicy);

        return new Rules(
            $this->settings->get(SettingKey::FitThresholds),
            $this->settings->get(SettingKey::VerificationThresholds),
            $output['cards'], $output['shortlist'], $output['include_ties'],
            $this->settings->get(SettingKey::VerificationCorrections),
        );
    }

    public static function item(CatalogEntry $entry): Item
    {
        return new Item(GuideArea::from($entry->area->value), $entry->id, $entry->name, $entry->nameLatin, $entry->fields, $entry->values);
    }
}
