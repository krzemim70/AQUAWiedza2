<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Calculators\SupplyForecast;
use App\Domain\Aquarium\Models\SupplyUnit;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Domain\Shops\Contracts\PublicShops;
use App\Domain\Shops\Data\PublicOffer;
use App\Domain\Shops\OfferAvailability;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TankSupplies extends Component
{
    #[Locked]
    public int $tankId;

    public function mount(int $tankId): void
    {
        $this->tankId = $tankId;
    }

    public function render(
        Access $access,
        Catalog $catalog,
        PublicOffers $offers,
        PublicShops $shops,
        Settings $settings,
    ): View {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        $tank = $access->tank($user, $this->tankId, Feature::TankSupplies);
        $rows = $tank->supplies()->active()->orderBy('id')->get();
        /** @var list<string> $ids */
        $ids = $rows->pluck('catalog_id')->map(fn (mixed $id): string => (string) $id)->unique()->values()->all();

        $names = $catalog->labels(Area::Care, $ids);
        $canOffers = $access->allows($user, Feature::PublicOffers);
        $canCart = $access->allows($user, Feature::ShopCart);
        $offersByRecord = $canOffers ? $offers->forRecords(Area::Care, $ids) : [];
        $cityKey = OfferAvailability::cityKey($user->city);
        $shopNames = $shops->activeNames();
        $now = now()->utc();

        $items = [];
        foreach ($rows as $row) {
            $projection = SupplyForecast::calculate(
                remainingAmount: (float) $row->remaining_amount,
                balanceAt: $row->balance_at,
                doseAmount: (float) $row->dose_amount,
                dosePerLiters: $row->dose_per_liters !== null ? (float) $row->dose_per_liters : null,
                tankVolumeLiters: $tank->volume_liters,
                intervalDays: $row->interval_days,
                now: $now,
            );

            $recordOffers = array_values(array_filter(
                $offersByRecord[$row->catalog_id] ?? [],
                fn (PublicOffer $offer): bool => $offer->freeQty > 0,
            ));

            $preferredMissing = false;
            $reorderOffer = null;
            if ($row->preferred_shop_id !== null) {
                foreach ($recordOffers as $offer) {
                    if ($offer->shopId === $row->preferred_shop_id) {
                        $reorderOffer = $offer;
                        break;
                    }
                }
                if ($reorderOffer === null) {
                    $preferredMissing = true;
                    $reorderOffer = OfferAvailability::firstIn($recordOffers, $cityKey);
                }
            } else {
                $reorderOffer = OfferAvailability::firstIn($recordOffers, $cityKey);
            }

            $items[] = [
                'row' => $row,
                'name' => $names[$row->catalog_id] ?? null,
                'projection' => $projection,
                'reorderOffer' => $reorderOffer,
                'preferredMissing' => $preferredMissing,
            ];
        }

        $query = request()->query('szukaj');
        $phrase = is_string($query) ? mb_substr(trim($query), 0, 100) : '';
        $limit = (int) ($settings->get(SettingKey::Supplies)[$user->tier()->value] ?? 0);

        return view('livewire.aquarium.tank-supplies', [
            'tank' => $tank,
            'items' => $items,
            'shopNames' => $shopNames,
            'units' => SupplyUnit::cases(),
            'canCart' => $canCart,
            'hasCity' => $cityKey !== null,
            'limit' => $limit,
            'atLimit' => ! $user->is_admin && $rows->count() >= $limit,
            'phrase' => $phrase,
            'results' => $phrase === '' ? [] : $catalog->search($phrase, [Area::Care]),
        ]);
    }
}
