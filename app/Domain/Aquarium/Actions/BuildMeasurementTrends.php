<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Advisor\Contracts\Compatibility;
use App\Domain\Advisor\Engine\Compatibility\StockLine;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Calculators\TrendCalculator;
use App\Domain\Aquarium\Data\MeasurementTrendsReport;
use App\Domain\Aquarium\Data\ParameterTrend;
use App\Domain\Aquarium\Data\TrendPoint;
use App\Domain\Aquarium\MeasurementWarnings;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;

/**
 * Buduje raport trendów pomiarów zbiornika w wybranym oknie czasowym (Fishroom).
 */
class BuildMeasurementTrends
{
    public function __construct(
        private Access $access,
        private Settings $settings,
        private Compatibility $compatibility,
        private Catalog $catalog,
        private MeasurementWarnings $warnings,
    ) {}

    public function handle(User $actor, int $tankId, ?int $windowDays = null): MeasurementTrendsReport
    {
        $tank = $this->access->tank($actor, $tankId, Feature::MeasurementTrends);

        $config = $this->settings->get(SettingKey::MeasurementTrends);
        $minPoints = (int) $config['min_points'];
        /** @var list<int> $windows */
        $windows = array_values(array_map(fn (mixed $window): int => (int) $window, (array) $config['windows_days']));
        $selectedWindow = ($windowDays !== null && in_array($windowDays, $windows, true))
            ? $windowDays
            : (in_array(30, $windows, true) ? 30 : $windows[0]);

        $now = now()->utc();
        $from = $now->copy()->subDays($selectedWindow);

        $measurements = $tank->measurements()
            ->where('measured_at', '>=', $from)
            ->where('measured_at', '<=', $now)
            ->orderBy('measured_at')
            ->orderBy('id')
            ->get();

        $stock = $tank->livestock()->where('area', Area::Fauna->value)->orderBy('id')->get()
            ->map(fn ($row): StockLine => new StockLine(GuideArea::Fauna, $row->catalog_id, $row->quantity))
            ->all();
        $batch = $this->catalog->activeBatchId();
        $commonRanges = $this->compatibility->commonWaterRanges($stock, $batch);

        $trends = [];
        foreach (Measurement::LABELS as $param => $label) {
            $points = [];
            $rows = [];
            foreach ($measurements as $measurement) {
                $raw = $measurement->$param;
                if ($raw === null) {
                    continue;
                }

                $points[] = new TrendPoint(
                    measuredAt: $measurement->measured_at->toDateTimeImmutable(),
                    value: (float) $raw,
                    method: $measurement->method,
                );
                $rows[] = [
                    'measured_at' => $measurement->measured_at,
                    'value' => (string) $raw,
                    'method_label' => $measurement->method?->label(),
                    'warnings' => $this->warnings->for($measurement, $param),
                ];
            }

            if ($points === []) {
                continue;
            }

            $result = TrendCalculator::calculate($param, $points, $selectedWindow, $minPoints, $now->toDateTimeImmutable());
            $rangesForParam = $commonRanges[$param] ?? null;

            $trends[] = new ParameterTrend(
                parameter: $param,
                label: $label,
                trend: $result,
                stockRanges: $rangesForParam ?? [],
                hasStockRangeSupport: array_key_exists($param, $commonRanges),
                rows: array_reverse($rows),
            );
        }

        return new MeasurementTrendsReport(
            windowDays: $selectedWindow,
            availableWindows: $windows,
            minPoints: $minPoints,
            trends: $trends,
        );
    }
}
