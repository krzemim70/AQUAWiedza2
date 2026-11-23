<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Advisor\Contracts\Compatibility;
use App\Domain\Advisor\Engine\Compatibility\StockLine;
use App\Domain\Advisor\Engine\Evidence;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Advisor\Engine\WaterHints\PhProductCheck;
use App\Domain\Advisor\Engine\WaterHints\WaterHintsEngine;
use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\WaterHintProduct;
use App\Domain\Aquarium\Data\WaterHintsReport;
use App\Domain\Aquarium\MeasurementWarnings;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Contracts\WaterHintProducts;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;

class BuildWaterHints
{
    public function __construct(
        private Access $access,
        private Compatibility $compatibility,
        private Catalog $catalog,
        private Settings $settings,
        private WaterHintsEngine $engine,
        private MeasurementWarnings $warnings,
        private WaterHintProducts $products,
    ) {}

    public function handle(User $actor, int $tankId): ?WaterHintsReport
    {
        $tank = $this->access->tank($actor, $tankId, Feature::WaterHints);
        $measurement = $tank->latestMeasurement;
        if ($measurement === null) {
            return null;
        }

        $stock = $tank->livestock()->where('area', Area::Fauna->value)->orderBy('id')->get()
            ->map(fn ($row) => new StockLine(GuideArea::Fauna, $row->catalog_id, $row->quantity))->all();
        $batch = $this->catalog->activeBatchId();
        [$fauna, $unknown] = $this->compatibility->waterRequirements($stock, $batch);
        $readings = [
            'ph' => $measurement->ph === null ? null : (float) $measurement->ph,
            'temp_c' => $measurement->temp_c === null ? null : (float) $measurement->temp_c,
        ];
        $hints = $this->engine->assess($readings, $fauna, $unknown, $this->settings->get(SettingKey::WaterHintsChecks), $batch);
        $ageInDays = max(0, (int) $measurement->measured_at->diffInDays(now()));
        $product = $reason = null;
        foreach ($hints as $hint) {
            if ($hint->phProduct !== null) {
                [$product, $reason] = $this->productFor($measurement, $hint->phProduct, $batch);
            }
        }

        return new WaterHintsReport($measurement, $hints, $ageInDays, $ageInDays >= $this->settings->get(SettingKey::WaterHintsStaleDays), $product, $reason);
    }

    /** @return array{?WaterHintProduct, ?string} */
    private function productFor(Measurement $measurement, PhProductCheck $check, ?int $batch): array
    {
        $now = now();
        $maxHours = (int) $this->settings->get(SettingKey::WaterHintProductMaxAgeHours);
        $reason = match (true) {
            $check === PhProductCheck::MissingReading => $check->value,
            $measurement->measured_at->gt($now) => 'data pomiaru jest w przyszłości',
            $measurement->measured_at->lt($now->copy()->subHours($maxHours)) => "pomiar ma więcej niż $maxHours godz.",
            $this->warnings->for($measurement, 'ph') !== [] => 'odczyt pH ma ostrzeżenie',
            $check !== PhProductCheck::Eligible => $check->value,
            default => null,
        };
        if ($reason !== null) {
            return [null, $reason];
        }

        foreach ($batch === null ? [] : $this->products->loweringPh($batch) as $entry) {
            if ($entry !== null) {
                return [new WaterHintProduct($entry->name, new Evidence('opis', $entry->fields['opis'], $entry->id, $batch)), null];
            }
        }

        return [null, 'brak preparatu z potwierdzonym opisem w katalogu'];
    }
}
