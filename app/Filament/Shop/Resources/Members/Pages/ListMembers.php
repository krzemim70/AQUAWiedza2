<?php

namespace App\Filament\Shop\Resources\Members\Pages;

use App\Domain\Shops\Actions\AddMember;
use App\Filament\Shop\Resources\Members\MemberResource;
use App\Filament\Shop\Support;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')->label('Dodaj pracownika')
                ->schema([TextInput::make('email')->label('E-mail istniejącego konta')->email()->required()])
                ->action(fn (array $data, Action $action) => Support::run($action, fn () => app(AddMember::class)->handle(
                    Support::user(), Support::shop(), $data['email'],
                )))
                ->successNotificationTitle('Dodano pracownika'),
        ];
    }
}
