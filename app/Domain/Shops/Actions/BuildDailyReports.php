<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Models\DailyReport;
use App\Domain\Shops\Models\Member;
use App\Domain\Shops\Models\OrderState;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;
use App\Domain\Shops\Notifications\DailyReportNotice;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Raport dobowy za poprzedni dzień lokalny (północ–północ, `Europe/Warsaw`), po godzinie z ustawień. Uruchamiany co godzinę:
 * jeden raport na sklep i dzień (blokada sklepu + `UNIQUE`), więc ponowne uruchomienie niczego nie dubluje,
 * a pominięty dzień zostanie nadrobiony.
 */
class BuildDailyReports
{
    public function __construct(private Settings $settings) {}

    public function handle(?CarbonInterface $now = null): int
    {
        $tz = config('app.display_timezone');
        $local = Carbon::instance($now ?? now())->timezone($tz);
        if ($local->hour < (int) $this->settings->get(SettingKey::ShopOrders)['report_hour']) {
            return 0;
        }

        $day = $local->copy()->startOfDay()->subDay();
        $from = $day->copy()->utc();
        $to = $day->copy()->addDay()->startOfDay()->utc();
        $created = 0;

        foreach (Shop::whereIn('state', [ShopState::Active->value, ShopState::Suspended->value])->orderBy('id')->get() as $shop) {
            $created += DB::transaction(function () use ($shop, $day, $from, $to) {
                Shop::lockForUpdate()->findOrFail($shop->id);
                if (DailyReport::where('shop_id', $shop->id)->whereDate('business_date', $day->toDateString())->exists()) {
                    return 0;
                }
                $report = DailyReport::create([
                    'shop_id' => $shop->id, 'business_date' => $day->toDateString(), 'period_from' => $from, 'period_to' => $to,
                    'generated_at' => now(), 'data' => $this->aggregate($shop, $from, $to),
                ]);
                DB::afterCommit(fn () => Notification::send(
                    User::whereIn('id', Member::where('shop_id', $shop->id)->pluck('user_id'))->get(), new DailyReportNotice($shop, $report),
                ));

                return 1;
            });
        }

        return $created;
    }

    /** @return array<string, mixed> */
    private function aggregate(Shop $shop, CarbonInterface $from, CarbonInterface $to): array
    {
        $events = DB::table('shop_order_events')->where('shop_id', $shop->id)->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to);
        $orders = [];
        foreach (OrderState::cases() as $state) {
            $orders[$state->value] = (clone $events)->where('to_state', $state->value)->where('from_state', '!=', $state->value)->count()
                + ($state === OrderState::New ? (clone $events)->whereNull('from_state')->count() : 0);
        }

        $moves = DB::table('shop_stock_movements')->where('shop_id', $shop->id)->where('occurred_at', '>=', $from)->where('occurred_at', '<', $to);
        $reasons = DB::table('shop_order_events')->join('shop_orders', 'shop_orders.id', '=', 'shop_order_events.order_id')
            ->where('shop_order_events.shop_id', $shop->id)->where('shop_order_events.to_state', 'rejected')
            ->where('shop_order_events.occurred_at', '>=', $from)->where('shop_order_events.occurred_at', '<', $to)
            ->selectRaw('shop_orders.rejection_code as code, count(*) as n')->groupBy('shop_orders.rejection_code')->pluck('n', 'code')->all();

        return [
            'orders' => $orders,
            'rejection_reasons' => $reasons,
            'pieces' => [
                'reserved' => (int) (clone $moves)->where('kind', 'reserve')->sum('delta_reserved'),
                'released' => (int) (clone $moves)->where('kind', 'release')->sum('delta_free'),
                'issued' => -(int) (clone $moves)->where('kind', 'issue')->sum('delta_reserved'),
            ],
            'payments_grosze' => (int) DB::table('shop_order_payments')->join('shop_orders', 'shop_orders.id', '=', 'shop_order_payments.order_id')
                ->where('shop_orders.shop_id', $shop->id)->where('confirmed_at', '>=', $from)->where('confirmed_at', '<', $to)->sum('amount_grosze'),
        ];
    }
}
