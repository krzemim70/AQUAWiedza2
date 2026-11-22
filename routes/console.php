<?php

use App\Domain\IdentityAccess\Actions\ExpireFishroom;
use App\Domain\IdentityAccess\Actions\PurgeUnverifiedAccounts;
use App\Domain\Shops\Actions\BuildDailyReports;
use App\Domain\Shops\Actions\ExpireOffers;
use App\Domain\Shops\Actions\PseudonymizeOrders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(ExpireFishroom::class)->handle())->daily()->name('expire-fishroom');
Schedule::call(fn () => app(PurgeUnverifiedAccounts::class)->handle())->daily()->name('purge-unverified-accounts')->withoutOverlapping();
Schedule::call(fn () => app(ExpireOffers::class)->handle())->hourly()->name('expire-offers');
Schedule::call(fn () => app(BuildDailyReports::class)->handle())->hourly()->name('shop-daily-reports'); // godzina raportu (10:00 Europe/Warsaw) w ustawieniu shops.orders
Schedule::call(fn () => app(PseudonymizeOrders::class)->handle())->daily()->name('pseudonymize-orders');
