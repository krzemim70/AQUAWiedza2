<?php

namespace App\Filament\Shop\Resources\Locations\Pages;

use App\Domain\Shops\Actions\SaveLocation;
use App\Filament\Shop\Resources\Locations\LocationResource;
use App\Filament\Shop\Support;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListLocations extends ListRecords
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label('Nowa placówka')
                ->schema(LocationResource::fields())
                ->action(fn (array $data, Action $action) => Support::run($action, fn () => app(SaveLocation::class)->handle(
                    Support::user(), Support::shop(), LocationResource::data($data),
                )))
                ->successNotificationTitle('Dodano placówkę'),
        ];
    }
}
