<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\IdentityAccess\Models\Feature;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** Historia pomiarów (Fishroom), od najnowszego. */
class ListMeasurements
{
    public function __construct(private Access $access) {}

    /** @return Collection<int, Measurement> */
    public function handle(User $actor, int $tankId, int $limit = 200): Collection
    {
        return $this->access->tank($actor, $tankId, Feature::MeasurementHistory)
            ->measurements()->orderByDesc('measured_at')->orderByDesc('id')->limit($limit)->get();
    }
}
