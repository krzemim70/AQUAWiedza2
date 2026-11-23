<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Advisor\Engine\Compatibility\CompatibilityReport;
use App\Domain\Advisor\Engine\Compatibility\Severity;
use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\Aquarium\LivestockFitWarning;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddToLivestock
{
    public function __construct(private Access $access, private Catalog $catalog, private CheckLivestockFit $fit) {}

    /**
     * Gdy baza wiedzy ma uwagi dla tego gatunku w tym zbiorniku, a `$acknowledge` jest false, rzuca LivestockFitWarning
     * (nic nie zapisuje). Z `$acknowledge` dodaje i zapisuje kody przyjętych ustaleń w obsadzie.
     */
    public function handle(User $actor, int $tankId, LivestockData $data, ?string $requestKey = null, bool $acknowledge = false): void
    {
        DB::transaction(function () use ($actor, $tankId, $data, $requestKey, $acknowledge) {
            $tank = $this->access->tank($actor, $tankId, forUpdate: true);

            if ($requestKey !== null && DB::table('tank_livestock_additions')->insertOrIgnore([
                'user_id' => $actor->id,
                'request_key' => strtolower($requestKey),
            ]) === 0) {
                return;
            }

            if (! in_array($data->area, [Area::Fauna, Area::Flora], true)
                || $this->catalog->find($data->area, $data->catalogId) === null) {
                throw ValidationException::withMessages(['species' => 'Wybierz gatunek z katalogu.']);
            }

            $key = ['tank_id' => $tank->id, 'area' => $data->area->value, 'catalog_id' => $data->catalogId];
            $stock = Livestock::where($key);
            $current = $stock->lockForUpdate()->first();

            if ($data->quantity < 1 || $data->quantity > LivestockData::MAX_QUANTITY - ($current->quantity ?? 0)) {
                throw ValidationException::withMessages(['quantity' => 'Łączna ilość w zbiorniku musi mieścić się w przedziale 1–'.LivestockData::MAX_QUANTITY.'.']);
            }

            $findings = $this->access->allows($actor, Feature::CompatibilityCheck) ? $this->fit->forTank($tank, $data)->findings : [];
            $findings = array_values(array_filter($findings, fn ($finding) => in_array($finding->severity, [Severity::Warning, Severity::Conflict], true)));
            if ($findings !== [] && ! $acknowledge) {
                throw new LivestockFitWarning(new CompatibilityReport(true, $findings, []));
            }
            $acknowledged = array_values(array_unique([...($current->acknowledged_findings ?? []), ...array_map(fn ($f) => $f->key(), $findings)]));

            if ($current === null) {
                Livestock::create($key + ['quantity' => $data->quantity, 'acknowledged_findings' => $acknowledged ?: null]);
            } else {
                $current->quantity += $data->quantity;
                $current->acknowledged_findings = $acknowledged ?: null;
                $current->save();
            }
        }, attempts: 3);
    }
}
