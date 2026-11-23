<?php

namespace App\Domain\Shops\Policies;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderState;
use App\Domain\Shops\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReviewPolicy
{
    public function __construct(private ResolveAccess $access, private Settings $settings) {}

    public function submit(User $actor, Order $order): Response
    {
        $decision = $this->access->handle($actor, Feature::ShopCart);
        if (! $decision->allowed) {
            return Response::deny($decision->message);
        }

        if ($order->user_id !== $actor->id) {
            return Response::denyAsNotFound();
        }

        if ($order->state !== OrderState::PickedUp) {
            return Response::deny('Opinię można dodać po odbiorze zamówienia.');
        }

        $pickup = $order->events()->where('from_state', OrderState::Ready)->where('to_state', OrderState::PickedUp)->first();
        if ($pickup === null || $pickup->occurred_at->isFuture()) {
            return Response::deny('Brak potwierdzonej daty odbioru.');
        }

        $days = (int) $this->settings->get(SettingKey::ShopReviews)['window_days'];
        if ($pickup->occurred_at->addDays($days)->isPast()) {
            return Response::deny('Termin dodania opinii upłynął.');
        }

        return Review::where('order_id', $order->id)->exists()
            ? Response::deny('To zamówienie ma już opinię.')
            : Response::allow();
    }
}
