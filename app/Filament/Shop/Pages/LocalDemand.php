<?php

namespace App\Filament\Shop\Pages;

use App\Domain\Shops\Access;
use App\Domain\Shops\Actions\ListLocalDemand;
use App\Domain\Shops\Models\Shop;
use App\Filament\Shop\Support;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class LocalDemand extends Page
{
    protected static ?string $title = 'Popyt w okolicy';

    protected static ?string $slug = 'popyt-w-okolicy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|array $routeMiddleware = 'access:shop_panel';

    protected ?string $subheading = 'Aktywne obserwacje „Daj znać, gdy będzie dostępne” w miastach Twoich placówek.';

    protected string $view = 'filament.shop.pages.local-demand';

    public static function canAccess(): bool
    {
        $actor = Filament::auth()->user();
        $shop = Filament::getTenant();

        return $actor instanceof User && $shop instanceof Shop && app(Access::class)->isMember($actor, $shop->id);
    }

    protected function getViewData(): array
    {
        return ['demand' => app(ListLocalDemand::class)->handle(Support::user(), Support::shop())];
    }
}
