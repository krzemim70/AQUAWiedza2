<?php

namespace App\Domain\Shops\Actions;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Access;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Domain\Shops\Data\CartLine;
use App\Domain\Shops\Data\ShopRating;
use App\Domain\Shops\Models\Offer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Koszyk klienta z bieżącymi cenami i ilościami; jedno źródło problemów blokujących wysyłkę. */
class ListCart
{
    public function __construct(private PublicOffers $offers, private Catalog $catalog, private Access $access, private Settings $settings) {}

    /** @return list<CartLine> posortowane po sklepie i placówce */
    public function handle(User $user): array
    {
        $rows = DB::table('shop_cart_items')->where('user_id', $user->id)->get();
        if ($rows->isEmpty()) {
            return [];
        }

        $stored = Offer::with(['shop', 'location'])->whereIn('id', $rows->pluck('offer_id'))->get()->keyBy('id');
        $visible = $this->offers->forOffers($rows->pluck('offer_id')->map(fn ($id) => (int) $id)->all());
        $bounds = $this->settings->get(SettingKey::ShopOrders);

        $lines = [];
        foreach ($rows as $row) {
            $offer = $stored[$row->offer_id];
            $public = $visible[$row->offer_id] ?? null;
            $qty = (int) $row->qty;
            $price = $public->priceGrosze ?? $offer->price_grosze;
            $issue = match (true) {
                $public === null => 'Oferta nie jest już dostępna. Usuń pozycję z koszyka.',
                $this->access->isMember($user, $offer->shop_id) => 'Nie możesz kupować w swoim sklepie. Usuń pozycję z koszyka.',
                $qty > $public->freeQty => "Dostępna ilość to {$public->freeQty}. Zmniejsz ilość.",
                $qty < $bounds['qty_min'] || $qty > $bounds['qty_max'] => "Ilość w pozycji musi mieścić się w zakresie {$bounds['qty_min']}–{$bounds['qty_max']}.",
                $price !== (int) $row->price_seen_grosze => 'Cena zmieniła się (była '.number_format($row->price_seen_grosze / 100, 2, ',', ' ').' zł). Zaakceptuj nową cenę.',
                default => null,
            };
            $lines[] = new CartLine(
                $offer->id, $offer->shop_id, $offer->shop->name, $offer->location_id, $offer->location->name, $offer->location->address,
                $offer->area->value, $offer->legacy_id,
                $this->catalog->labels($offer->area, [$offer->legacy_id])[$offer->legacy_id] ?? $offer->legacy_id,
                $offer->variant, $qty, $price, (int) $row->price_seen_grosze, $public->freeQty ?? 0, $offer->terms_version, $issue,
                $public === null ? null : new ShopRating($public->shopRatingAvg, $public->shopRatingCount),
            );
        }
        usort($lines, fn (CartLine $a, CartLine $b) => [$a->shopId, $a->locationId, $a->offerId] <=> [$b->shopId, $b->locationId, $b->offerId]);

        return $lines;
    }
}
