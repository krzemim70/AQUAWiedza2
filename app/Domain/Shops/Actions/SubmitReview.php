<?php

namespace App\Domain\Shops\Actions;

use App\Domain\IdentityAccess\Contracts\Accounts;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Data\ReviewData;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\Review;
use App\Domain\Shops\Orders;
use App\Domain\Shops\Policies\ReviewPolicy;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SubmitReview
{
    public function __construct(
        private Accounts $accounts,
        private Orders $orders,
        private ReviewPolicy $policy,
        private Settings $settings,
    ) {}

    public function handle(User $actor, Order $order, ReviewData $data): Review
    {
        return DB::transaction(function () use ($actor, $order, $data): Review {
            $customer = $this->accounts->lock($actor->id) ?? throw new AuthorizationException;
            $order = $this->orders->lock($order);
            $this->policy->submit($customer, $order)->authorize();

            $limit = (int) $this->settings->get(SettingKey::ShopReviews)['max_length'];
            $comment = Str::trim($data->comment ?? '');
            Validator::make(['rating' => $data->rating, 'comment' => $comment], [
                'rating' => ['integer', 'between:1,5'],
                'comment' => ['string', 'max:'.$limit],
            ], [
                'rating.between' => 'Wybierz ocenę od 1 do 5.',
                'comment.max' => 'Komentarz może mieć najwyżej :max znaków.',
            ])->validate();

            return Review::create([
                'shop_id' => $order->shop_id,
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'rating' => $data->rating,
                'comment' => $comment === '' ? null : $comment,
            ]);
        }, 3);
    }
}
