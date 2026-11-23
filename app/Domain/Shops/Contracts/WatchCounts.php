<?php

namespace App\Domain\Shops\Contracts;

use App\Domain\IdentityAccess\Contracts\Accounts;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Data\RecordWatchCounts;
use App\Domain\Shops\Models\RecordWatch;
use App\Domain\Shops\OfferAvailability;

class WatchCounts
{
    public function __construct(private Accounts $accounts) {}

    /**
     * @param  list<string>  $ids
     */
    public function byRecord(?string $city, Area $area, array $ids): RecordWatchCounts
    {
        $cityKey = OfferAvailability::cityKey($city);
        $counts = array_fill_keys($ids, 0);
        $watches = RecordWatch::pending()->where('area', $area)->whereIn('legacy_id', $ids);
        if ($cityKey === null) {
            $totals = $watches->selectRaw('legacy_id, count(*) as total')->groupBy('legacy_id')->pluck('total', 'legacy_id');
            foreach ($totals as $id => $total) {
                $counts[$id] = (int) $total;
            }
        } else {
            foreach ($watches->select(['id', 'user_id', 'legacy_id'])->lazyById(500)->chunk(500) as $batch) {
                $users = $this->accounts->forIds($batch->pluck('user_id')->unique()->values()->all());
                foreach ($batch as $watch) {
                    if (OfferAvailability::cityKey($users->get($watch->user_id)?->city) === $cityKey) {
                        $counts[$watch->legacy_id]++;
                    }
                }
            }
        }

        return new RecordWatchCounts($counts);
    }
}
