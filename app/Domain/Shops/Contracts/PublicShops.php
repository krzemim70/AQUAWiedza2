<?php

namespace App\Domain\Shops\Contracts;

use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;

class PublicShops
{
    /** @return array<int, string> id => nazwa aktywnego sklepu */
    public function activeNames(): array
    {
        return Shop::query()->where('state', ShopState::Active->value)->orderBy('name')->pluck('name', 'id')->all();
    }

    public function isActive(int $shopId): bool
    {
        return Shop::query()->whereKey($shopId)->where('state', ShopState::Active->value)->exists();
    }
}
