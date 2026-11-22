<?php

namespace App\Domain\Shops\Actions;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Models\ActivationException;
use App\Domain\Shops\Models\Location;
use App\Domain\Shops\Models\MemberRole;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;
use Illuminate\Support\Facades\DB;

/**
 * Ocena gotowości: sklep w przygotowaniu staje się aktywny, gdy ma czynną placówkę i próg kwalifikujących się ofert
 * albo aktywne odstępstwo. Aktywacja zostaje po późniejszym spadku liczby ofert.
 */
class EvaluateShop
{
    public function __construct(private Settings $settings, private Catalog $catalog) {}

    public function handle(Shop $shop): Shop
    {
        return DB::transaction(function () use ($shop) {
            $shop = Shop::lockForUpdate()->findOrFail($shop->id);
            if ($shop->state !== ShopState::Preparation || ! $this->hasActiveLocation($shop)) {
                return $shop;
            }
            if (! $shop->members()->where('role', MemberRole::Owner)->lockForUpdate()->first(['id'])) {
                return $shop;
            }

            if ($this->hasException($shop) || $this->qualifying($shop) >= $this->threshold()) {
                $shop->forceFill(['state' => ShopState::Active, 'activated_at' => now()])->save();
                activity()->performedOn($shop)->log('shop activated');
            }

            return $shop;
        });
    }

    public function threshold(): int
    {
        return (int) $this->settings->get(SettingKey::ShopActivation)['threshold'];
    }

    public function hasException(Shop $shop): bool
    {
        return ActivationException::where('shop_id', $shop->id)->where('effective_from', '<=', now())->where('effective_to', '>', now())->exists();
    }

    public function hasActiveLocation(Shop $shop): bool
    {
        return Location::where('shop_id', $shop->id)->where('active', true)->exists();
    }

    /** Opublikowane, niewygasłe oferty w czynnych placówkach, wskazujące aktywne rekordy katalogu (także z zerową ilością). */
    public function qualifying(Shop $shop): int
    {
        $count = 0;
        $keys = Offer::where('shop_id', $shop->id)->live()
            ->whereHas('location', fn ($q) => $q->where('active', true))
            ->get(['area', 'legacy_id'])->groupBy(fn (Offer $o) => $o->area->value);

        foreach ($keys as $area => $offers) {
            $present = $this->catalog->labels(Area::from($area), $offers->pluck('legacy_id')->all());
            $count += $offers->filter(fn (Offer $o) => isset($present[$o->legacy_id]))->count();
        }

        return $count;
    }
}
