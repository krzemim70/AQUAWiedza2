<?php

namespace App\Filament\Shop\Resources\Offers\Pages;

use App\Domain\Shops\Actions\SaveOffer;
use App\Filament\Shop\Resources\Offers\OfferResource;
use App\Filament\Shop\Support;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListOffers extends ListRecords
{
    protected static string $resource = OfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label('Nowa oferta')
                ->schema(OfferResource::createForm())
                ->action(fn (array $data, Action $action) => Support::run($action, fn () => app(SaveOffer::class)->handle(
                    Support::user(), Support::shop(), OfferResource::data($data),
                )))
                ->successNotificationTitle('Zapisano szkic oferty'),
        ];
    }
}
