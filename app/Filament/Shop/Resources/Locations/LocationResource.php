<?php

namespace App\Filament\Shop\Resources\Locations;

use App\Domain\Shops\Access;
use App\Domain\Shops\Actions\SaveLocation;
use App\Domain\Shops\Data\LocationData;
use App\Domain\Shops\Models\Location;
use App\Filament\Shop\Resources\Locations\Pages\ListLocations;
use App\Filament\Shop\Support;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static ?string $slug = 'placowki';

    protected static ?string $modelLabel = 'placówka';

    protected static ?string $pluralModelLabel = 'Placówki';

    public static function canViewAny(): bool
    {
        return app(Access::class)->isOwner(Support::user(), Support::shop()->id);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nazwa'),
                TextColumn::make('address')->label('Adres'),
                TextColumn::make('city')->label('Miasto'),
                IconColumn::make('active')->label('Czynna')->boolean(),
            ])
            ->recordActions([
                Action::make('edit')->label('Edytuj')
                    ->fillForm(fn (Location $r) => $r->only(['name', 'address', 'city', 'phone', 'opening_hours', 'active']))
                    ->schema(self::fields())
                    ->action(fn (Location $record, array $data, Action $action) => Support::run($action, fn () => app(SaveLocation::class)->handle(
                        Support::user(), Support::shop(), self::data($data), $record,
                    )))
                    ->successNotificationTitle('Zapisano placówkę'),
            ]);
    }

    /** @return list<mixed> */
    public static function fields(): array
    {
        return [
            TextInput::make('name')->label('Nazwa')->required()->maxLength(255),
            TextInput::make('address')->label('Adres odbioru')->required()->maxLength(255),
            TextInput::make('city')->label('Miasto')->required()->maxLength(120),
            TextInput::make('phone')->label('Telefon')->maxLength(32),
            Textarea::make('opening_hours')->label('Godziny otwarcia'),
            Toggle::make('active')->label('Czynna')->default(true),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function data(array $data): LocationData
    {
        return new LocationData($data['name'], $data['address'], $data['city'], $data['phone'] ?? null, $data['opening_hours'] ?? null, (bool) ($data['active'] ?? false));
    }

    public static function getPages(): array
    {
        return ['index' => ListLocations::route('/')];
    }
}
