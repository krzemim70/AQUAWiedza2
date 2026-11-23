<?php

namespace App\Domain\Shops\Contracts;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Data\ShopRating;
use App\Domain\Shops\Models\Review;

class ShopRatings
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  list<int>  $shopIds
     * @return array<int, ShopRating> po identyfikatorze sklepu; poniżej progu średnia i liczba nie są ujawniane
     */
    public function forShops(array $shopIds): array
    {
        if ($shopIds === []) {
            return [];
        }

        $minimum = (int) $this->settings->get(SettingKey::ShopReviews)['min_to_show'];
        $ratings = array_fill_keys($shopIds, new ShopRating);
        $groups = Review::published()->whereIn('shop_id', $shopIds)->groupBy('shop_id')
            ->selectRaw('shop_id, AVG(rating) as average, COUNT(*) as review_count')
            ->havingRaw('COUNT(*) >= ?', [$minimum])->toBase()->get();

        foreach ($groups as $group) {
            $ratings[$group->shop_id] = new ShopRating((float) $group->average, (int) $group->review_count);
        }

        return $ratings;
    }
}
