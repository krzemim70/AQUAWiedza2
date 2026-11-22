<?php

namespace App\Filament\Resources\Orders;

use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderState;
use App\Filament\Resources\Orders\Pages\ListOrders;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Zamówienia wszystkich sklepów do odczytu (wsparcie). Bez danych kontaktowych klientów. */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'zamowienia';

    protected static ?string $modelLabel = 'zamówienie';

    protected static ?string $pluralModelLabel = 'Zamówienia sklepów';

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-users');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('shop'))
            ->columns([
                TextColumn::make('id')->label('Nr')->sortable(),
                TextColumn::make('ean13')->label('Numer EAN')->placeholder('—')->searchable(),
                TextColumn::make('shop.name')->label('Sklep')->searchable(),
                TextColumn::make('state')->label('Status')->badge()->formatStateUsing(fn (OrderState $state) => $state->label()),
                TextColumn::make('total_grosze')->label('Suma')->formatStateUsing(fn (int $state) => number_format($state / 100, 2, ',', ' ').' zł'),
                TextColumn::make('created_at')->label('Złożone')->dateTime()->timezone(config('app.display_timezone'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')->label('Status')->options(collect(OrderState::cases())->mapWithKeys(fn (OrderState $s) => [$s->value => $s->label()])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListOrders::route('/')];
    }
}
