<?php

use App\Domain\IdentityAccess\Actions\ExpireFishroom;
use App\Domain\Shops\Actions\ExpireOffers;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(ExpireFishroom::class)->handle())->daily()->name('expire-fishroom');
Schedule::call(fn () => app(ExpireOffers::class)->handle())->hourly()->name('expire-offers');
