<?php

namespace App\Filament\Shop\Widgets;

use App\Domain\Shops\Access;
use App\Domain\Shops\Models\Location;
use App\Filament\Shop\Resources\Locations\LocationResource;
use App\Filament\Shop\Support;
use Filament\Widgets\Widget;

/** Ostrzeżenie na pulpicie, gdy którakolwiek placówka sklepu nie ma miasta (np. po imporcie ze starego systemu). */
class MissingCity extends Widget
{
    protected static ?int $sort = -10;

    protected string $view = 'filament.shop.widgets.missing-city';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $shop = Support::shop();
        $names = Location::where('shop_id', $shop->id)->where(fn ($q) => $q->whereNull('city')->orWhere('city', ''))->orderBy('id')->pluck('name');

        return [
            'names' => $names,
            'url' => app(Access::class)->isOwner(Support::user(), $shop->id) ? LocationResource::getUrl('index') : null,
        ];
    }
}
