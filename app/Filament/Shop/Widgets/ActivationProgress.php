<?php

namespace App\Filament\Shop\Widgets;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Actions\EvaluateShop;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\ShopState;
use App\Filament\Shop\Support;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Pulpit sklepu: stan, postęp aktywacji (X / próg) i wykorzystanie limitu ofert. */
class ActivationProgress extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $shop = Support::shop();
        $evaluate = app(EvaluateShop::class);
        $limit = (int) app(Settings::class)->get(SettingKey::ShopActivation)['offer_limit'];
        $exception = $evaluate->hasException($shop);

        $activation = match (true) {
            $shop->state === ShopState::Preparation => Stat::make('Oferty do aktywacji', $evaluate->qualifying($shop).' / '.$evaluate->threshold())
                ->description($exception ? 'Odstępstwo administratora — sklep aktywuje się po dodaniu czynnej placówki' : 'Sklep aktywuje się po osiągnięciu progu i dodaniu czynnej placówki'),
            default => Stat::make('Oferty kwalifikujące się', (string) $evaluate->qualifying($shop))->description('Próg aktywacji: '.$evaluate->threshold()),
        };

        return [
            Stat::make('Status sklepu', $shop->state->label()),
            $activation,
            Stat::make('Opublikowane oferty', Offer::where('shop_id', $shop->id)->live()->count().' / '.$limit)->description('Limit ofert sklepu'),
        ];
    }
}
