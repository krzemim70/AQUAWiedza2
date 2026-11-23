<?php

namespace App\Domain\Shops\Actions;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Access;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Domain\Shops\Data\ReorderResult;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderState;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ponowne zamówienie pozycji z własnego odebranego zamówienia: dodaje do koszyka nadal dostępne oferty
 * i raportuje braki towaru oraz zmiany cen.
 */
class ReorderOrder
{
    public function __construct(
        private ResolveAccess $access,
        private Access $shops,
        private PublicOffers $offers,
        private ChangeCart $changeCart,
        private Settings $settings,
    ) {}

    public function handle(User $actor, int $orderId): ReorderResult
    {
        $decision = $this->access->handle($actor, Feature::ShopCart);
        $decision->allowed || throw new AuthorizationException($decision->message);

        return DB::transaction(function () use ($actor, $orderId): ReorderResult {
            User::lockForUpdate()->findOrFail($actor->id);
            $order = Order::with('items')->where('user_id', $actor->id)->findOrFail($orderId);

            if ($order->state !== OrderState::PickedUp) {
                throw ValidationException::withMessages(['order' => 'Ponowne zamówienie jest dostępne tylko dla odebranych zamówień.']);
            }

            $items = $order->items;
            /** @var list<int> $offerIds */
            $offerIds = $items->pluck('offer_id')->map(fn ($id): int => (int) $id)->values()->all();
            $visible = $this->offers->forOffers($offerIds);
            $bounds = $this->settings->get(SettingKey::ShopOrders);

            $addedCount = 0;
            $added = [];
            $priceChanges = [];
            $unavailable = [];

            foreach ($items as $item) {
                $offer = $visible[$item->offer_id] ?? null;
                if ($offer === null || $offer->freeQty <= 0) {
                    $unavailable[] = "{$item->name}: brak towaru lub oferta nie jest już dostępna.";

                    continue;
                }
                if ($this->shops->isMember($actor, $offer->shopId)) {
                    $unavailable[] = "{$item->name}: nie możesz kupować we własnym sklepie.";

                    continue;
                }

                $inCart = (int) DB::table('shop_cart_items')
                    ->where('user_id', $actor->id)
                    ->where('offer_id', $offer->id)
                    ->value('qty');
                $maxAddable = min((int) $bounds['qty_max'] - $inCart, $offer->freeQty - $inCart);
                if ($maxAddable <= 0) {
                    $unavailable[] = "{$item->name}: brak wolnej ilości do dodania (limit koszyka lub stan oferty).";

                    continue;
                }

                $addQty = min($item->qty, $maxAddable);
                if ($inCart + $addQty < (int) $bounds['qty_min']) {
                    $unavailable[] = "{$item->name}: minimalna ilość w pozycji to {$bounds['qty_min']}.";

                    continue;
                }

                try {
                    $this->changeCart->handle($actor, $offer->id, $addQty, add: true);
                } catch (ValidationException $exception) {
                    $first = collect($exception->errors())->flatten()->first();
                    $unavailable[] = "{$item->name}: ".(is_string($first) ? $first : 'nie udało się dodać pozycji.');

                    continue;
                }

                $addedCount++;
                $added[] = $item->name;

                if ($addQty < $item->qty) {
                    $unavailable[] = "{$item->name}: dostępna ilość to {$offer->freeQty} (dodano {$addQty} z {$item->qty} szt.).";
                }

                if ($offer->priceGrosze !== $item->price_grosze) {
                    $old = number_format($item->price_grosze / 100, 2, ',', ' ').' zł';
                    $new = number_format($offer->priceGrosze / 100, 2, ',', ' ').' zł';
                    $priceChanges[] = "{$item->name}: zmieniła się cena (poprzednio {$old}, obecnie {$new}).";
                }
            }

            return new ReorderResult($addedCount, $added, $priceChanges, $unavailable);
        });
    }
}
