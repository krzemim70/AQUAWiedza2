<?php

namespace App\Domain\Shops\Actions;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Access;
use App\Domain\Shops\Contracts\WatchCounts;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\OfferAvailability;
use App\Models\User;

class ListLocalDemand
{
    public function __construct(
        private Access $access,
        private Catalog $catalog,
        private WatchCounts $watches,
        private Settings $settings,
    ) {}

    /** @return list<array{city: string, records: list<array{id: string, name: string, area: Area, count: int}>}> */
    public function handle(User $actor, Shop $shop): array
    {
        $this->access->authorizeMember($actor, $shop->id);

        $cities = [];
        foreach ($shop->locations()->orderBy('city')->orderBy('id')->pluck('city') as $city) {
            $key = OfferAvailability::cityKey($city);
            if ($key !== null) {
                $cities[$key] ??= trim($city);
            }
        }
        if ($cities === []) {
            return [];
        }

        $threshold = (int) $this->settings->get(SettingKey::DemandMetrics)['k'];
        $labels = [];
        foreach (Area::cases() as $area) {
            $labels[$area->value] = array_column($this->catalog->snapshot($area), 'name', 'id');
        }

        $demand = [];
        foreach ($cities as $city) {
            $records = [];
            foreach (Area::cases() as $area) {
                $names = $labels[$area->value];
                if ($names === []) {
                    continue;
                }
                $counts = $this->watches->byRecord($city, $area, array_keys($names));
                foreach ($counts->counts as $id => $count) {
                    if ($count >= $threshold) {
                        $records[] = ['id' => $id, 'name' => $names[$id], 'area' => $area, 'count' => $count];
                    }
                }
            }
            if ($records !== []) {
                usort($records, fn (array $a, array $b) => $b['count'] <=> $a['count']);
                $demand[] = ['city' => $city, 'records' => $records];
            }
        }

        return $demand;
    }
}
