<?php

use App\Domain\Aquarium\Actions\SendDueReminders;
use App\Domain\IdentityAccess\Actions\ExpireFishroom;
use App\Domain\IdentityAccess\Actions\PurgeUnverifiedAccounts;
use App\Domain\Shops\Actions\BuildDailyReports;
use App\Domain\Shops\Actions\ExpireOffers;
use App\Domain\Shops\Actions\NotifyRecordWatchers;
use App\Domain\Shops\Actions\PseudonymizeOrders;
use App\Domain\Shops\Actions\PurgeOfferImports;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('shops:daily-reports {--date= : Data biznesowa w formacie RRRR-MM-DD}', function () {
    $date = $this->option('date');
    $today = now(config('app.display_timezone'))->toDateString();
    $validator = Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d', "before:{$today}"]]);
    if ($date !== null && $validator->fails()) {
        $this->error('Podaj minioną datę biznesową w formacie RRRR-MM-DD.');

        return 1;
    }

    $count = app(BuildDailyReports::class)->handle(businessDate: $date);
    $this->info("Liczba zapisanych raportów: {$count}");

    return 0;
})->purpose('Generuje raporty dobowe sklepów');

Schedule::call(fn () => app(ExpireFishroom::class)->handle())->daily()->name('expire-fishroom')->onOneServer()->withoutOverlapping();
Schedule::call(fn () => app(PurgeUnverifiedAccounts::class)->handle())->daily()->name('purge-unverified-accounts')->onOneServer()->withoutOverlapping();
Schedule::call(fn () => app(ExpireOffers::class)->handle())->hourly()->name('expire-offers')->onOneServer()->withoutOverlapping();
Schedule::call(fn () => app(PurgeOfferImports::class)->handle())->hourly()->name('purge-offer-imports')->onOneServer()->withoutOverlapping();
Schedule::command('shops:daily-reports')->hourly()->name('shop-daily-reports')->onOneServer()->withoutOverlapping();
Schedule::call(fn () => app(PseudonymizeOrders::class)->handle())->daily()->name('pseudonymize-orders')->onOneServer()->withoutOverlapping();
Schedule::call(fn () => app(NotifyRecordWatchers::class)->handle())->everyFifteenMinutes()->name('notify-record-watchers')->onOneServer()->withoutOverlapping();
Schedule::call(fn () => app(SendDueReminders::class)->handle())->hourly()->name('send-due-reminders')->onOneServer()->withoutOverlapping();
