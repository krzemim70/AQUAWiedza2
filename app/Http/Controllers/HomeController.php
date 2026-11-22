<?php

namespace App\Http\Controllers;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Strona główna: opis serwisu, liczby z katalogu i cena Fishroom z ustawień. */
class HomeController
{
    public function __invoke(Request $request, Catalog $catalog, Settings $settings, ResolveAccess $access): View
    {
        $counts = [];
        foreach (Area::cases() as $area) {
            $counts[$area->value] = $catalog->count($area);
        }

        $price = $settings->get(SettingKey::FishroomPrice);
        $period = $settings->get(SettingKey::FishroomPeriod);
        $currency = $settings->get(SettingKey::FishroomCurrency);

        return view('public.home', [
            'counts' => $counts,
            'price' => is_int($price) ? $this->price($price, (string) $currency, (array) $period) : null,
            'advisorUrl' => $access->handle($request->user(), Feature::TankRanking)->allowed ? route('advisor.tank') : route('drop.show'),
        ]);
    }

    /** @param array<string, mixed> $period */
    private function price(int $grosze, string $currency, array $period): string
    {
        $amount = rtrim(rtrim(number_format($grosze / 100, 2, ',', ' '), '0'), ',');
        $unit = ['day' => 'dzień', 'month' => 'miesiąc'][$period['unit'] ?? ''] ?? null;

        return $amount.' '.($currency === 'PLN' ? 'zł' : $currency).($unit !== null && ($period['count'] ?? 1) === 1 ? ' / '.$unit : '');
    }
}
