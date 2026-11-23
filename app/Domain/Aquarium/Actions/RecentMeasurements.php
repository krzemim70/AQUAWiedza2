<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\IdentityAccess\Models\Feature;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** Ostatnie pomiary zbiornika (od najnowszego), dostępne także w planie Tank. */
class RecentMeasurements
{
    public function __construct(private Access $access) {}

    /** @return Collection<int, Measurement> */
    public function handle(User $actor, int $tankId, int $limit = 3, Feature $feature = Feature::TankView): Collection
    {
        return $this->access->tank($actor, $tankId, $feature)
            ->measurements()
            ->orderByDesc('measured_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();
    }
}
