<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Advisor\Contracts\Compatibility;
use App\Domain\Advisor\Engine\Compatibility\StockLine;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Advisor\Engine\WaterHints\WaterHintsEngine;
use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\WaterHintsReport;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
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

        return new WaterHintsReport($measurement, $hints, $ageInDays, $ageInDays >= $this->settings->get(SettingKey::WaterHintsStaleDays));
    }
}
