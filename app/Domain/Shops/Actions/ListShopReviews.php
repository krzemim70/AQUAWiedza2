<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Models\Review;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\Paginator;

class ListShopReviews
{
    /** @return Paginator<int, Review> */
    public function handle(Shop $shop): Paginator
    {
        if ($shop->state !== ShopState::Active) {
            throw (new ModelNotFoundException)->setModel(Shop::class);
        }

        return $shop->reviews()->published()->whereNotNull('comment')->latest('id')
            ->simplePaginate(columns: ['id', 'rating', 'comment', 'created_at']);
    }
}
