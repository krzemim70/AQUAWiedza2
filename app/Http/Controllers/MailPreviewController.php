<?php

namespace App\Http\Controllers;

use App\Domain\IdentityAccess\Notifications\ResetPasswordNotice;
use App\Domain\IdentityAccess\Notifications\VerifyEmailNotice;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Models\DailyReport;
use App\Domain\Shops\Models\Location;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderState;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Notifications\DailyReportNotice;
use App\Domain\Shops\Notifications\OrderNotice;
use App\Models\User;
use Illuminate\Http\Response;

class MailPreviewController
{
    public function __invoke(string $rodzaj): Response
    {
        abort_unless(app()->isLocal(), 404);

        $user = new User(['email' => 'podglad@example.test']);
        $user->id = 0;

        $shop = new Shop(['name' => 'Zielone Akwarium']);
        $shop->id = 12;
        $order = new Order([
            'id' => 42, 'shop_id' => $shop->id, 'total_grosze' => 12450,
            'ean13' => '5900000000423', 'rejection_code' => 'brak_aktualnej_oferty',
        ]);
        $order->setRelation('shop', $shop);
        $order->setRelation('location', new Location(['name' => 'Placówka Główna', 'address' => 'Rynek 1', 'city' => 'Poznań']));

        $notice = match ($rodzaj) {
            'weryfikacja' => new VerifyEmailNotice((int) SettingKey::ActivationLifetime->default()),
            'reset-hasla' => new ResetPasswordNotice('przykladowy-token'),
            'nowe-zamowienie' => new OrderNotice($order, 'submitted'),
            'zamowienie-potwierdzone' => new OrderNotice($order, 'confirmed'),
            'zamowienie-odrzucone' => new OrderNotice($order, 'rejected'),
            'zamowienie-gotowe' => new OrderNotice($order, 'ready'),
            'raport-dobowy' => new DailyReportNotice($shop, new DailyReport([
                'business_date' => now(config('app.display_timezone'))->subDay()->toDateString(),
                'data' => [
                    'orders' => array_fill_keys(array_column(OrderState::cases(), 'value'), 2),
                    'pieces' => ['reserved' => 8, 'released' => 2, 'issued' => 4],
                    'payments_grosze' => 24900,
                ],
            ])),
            default => abort(404),
        };

        return response((string) $notice->toMail($user)->render())
            ->header('Cache-Control', 'no-store');
    }
}
