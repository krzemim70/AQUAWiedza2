<?php

namespace App\Filament\Shop\Resources\Orders;

use App\Domain\Shops\Actions\AdvanceOrder;
use App\Domain\Shops\Actions\ApproveOrder;
use App\Domain\Shops\Actions\CloseOrder;
use App\Domain\Shops\Actions\RejectOrder;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\OrderState;
use App\Filament\Shop\Resources\Orders\Pages\ListOrders;
use App\Filament\Shop\Resources\Orders\Pages\ViewOrder;
use App\Filament\Shop\Support;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Zamówienia sklepu: lista z filtrem stanu, szczegóły z kontaktem klienta i czynności zależne od stanu. */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'zamowienia';

    protected static ?string $modelLabel = 'zamówienie';

    protected static ?string $pluralModelLabel = 'Zamówienia';

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
            ->modifyQueryUsing(fn ($query) => $query->with('contact'))
            ->columns([
                TextColumn::make('id')->label('Nr')->sortable(),
                TextColumn::make('ean13')->label('Numer EAN')->placeholder('—')->searchable(),
                TextColumn::make('state')->label('Status')->badge()->formatStateUsing(fn (OrderState $state) => $state->label()),
                TextColumn::make('customer')->label('Klient')->getStateUsing(fn (Order $r) => $r->contactVisible() ? $r->contact?->nick : null)->placeholder('—'),
                TextColumn::make('total_grosze')->label('Suma')->formatStateUsing(fn (int $state) => Support::money($state)),
                TextColumn::make('created_at')->label('Złożone')->dateTime()->timezone(config('app.display_timezone'))->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')->label('Status')->options(collect(OrderState::cases())->mapWithKeys(fn (OrderState $s) => [$s->value => $s->label()])),
            ])
            ->recordActions([Action::make('view')->label('Szczegóły')->url(fn (Order $r) => self::getUrl('view', ['record' => $r])), ...self::actions()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Zamówienie')->columns(3)->schema([
                TextEntry::make('ean13')->label('Numer EAN (tytuł przelewu)')->placeholder('— (przed decyzją)'),
                TextEntry::make('state')->label('Status')->badge()->formatStateUsing(fn (OrderState $state) => $state->label()),
                TextEntry::make('total_grosze')->label('Suma')->formatStateUsing(fn (int $state) => Support::money($state)),
                TextEntry::make('location.name')->label('Placówka odbioru'),
                TextEntry::make('created_at')->label('Złożone')->dateTime()->timezone(config('app.display_timezone')),
                TextEntry::make('rejection_code')->label('Powód odrzucenia')->placeholder('—')
                    ->formatStateUsing(fn (Order $r) => $r->rejection_code === 'inne' ? $r->note : (Order::REJECTION_CODES[$r->rejection_code] ?? '—')),
            ]),
            Section::make('Klient')->columns(3)->schema([
                TextEntry::make('nick')->label('Nick')->getStateUsing(fn (Order $r) => $r->contactVisible() ? $r->contact?->nick : null)->placeholder('Dane usunięte po okresie przechowywania'),
                TextEntry::make('email')->label('E-mail')->getStateUsing(fn (Order $r) => $r->contactVisible() ? $r->contact?->email : null)->placeholder('—'),
                TextEntry::make('phone')->label('Telefon')->getStateUsing(fn (Order $r) => $r->contactVisible() ? $r->contact?->phone : null)->placeholder('—'),
            ]),
            RepeatableEntry::make('items')->label('Pozycje')->columns(4)
                ->getStateUsing(fn (Order $r) => $r->items->map(fn ($i) => [
                    'name' => $i->name.($i->variant !== '' ? " ({$i->variant})" : ''),
                    'price' => Support::money($i->price_grosze), 'qty' => $i->qty, 'sum' => Support::money($i->price_grosze * $i->qty),
                ])->all())
                ->schema([
                    TextEntry::make('name')->label('Pozycja'),
                    TextEntry::make('price')->label('Cena z zamówienia'),
                    TextEntry::make('qty')->label('Ilość'),
                    TextEntry::make('sum')->label('Razem'),
                ]),
            RepeatableEntry::make('history')->label('Historia')->columns(3)
                ->getStateUsing(fn (Order $r) => $r->events()->with('actor')->get()->map(fn ($e) => [
                    'at' => $e->occurred_at->timezone(config('app.display_timezone'))->format('d.m.Y H:i'),
                    'state' => $e->to_state->label(), 'actor' => $e->actor->name ?? 'system',
                ])->all())
                ->schema([
                    TextEntry::make('at')->label('Kiedy'),
                    TextEntry::make('state')->label('Stan'),
                    TextEntry::make('actor')->label('Kto'),
                ]),
        ]);
    }

    /**
     * Czynności zależne od stanu (lista i szczegóły); każda woła operację domenową, która sprawdza członkostwo.
     *
     * @return list<Action>
     */
    public static function actions(): array
    {
        $step = fn (string $name, string $label, OrderState $from, OrderState $to, string $done) => Action::make($name)->label($label)
            ->visible(fn (Order $r) => $r->state === $from)->requiresConfirmation()
            ->action(fn (Order $record, Action $action) => Support::run($action, fn () => app(AdvanceOrder::class)->handle(Support::user(), $record, $to)))
            ->successNotificationTitle($done);

        return [
            Action::make('approve')->label('Potwierdź zamówienie')->visible(fn (Order $r) => $r->state === OrderState::New)
                ->requiresConfirmation()->modalDescription('Zarezerwuje dostępną ilość wszystkich pozycji i nada numer zamówienia.')
                ->action(fn (Order $record, Action $action) => Support::run($action, fn () => app(ApproveOrder::class)->handle(Support::user(), $record)))
                ->successNotificationTitle('Zamówienie potwierdzone'),
            Action::make('reject')->label('Odrzuć zamówienie')->color('danger')->visible(fn (Order $r) => $r->state === OrderState::New)
                ->schema([
                    Select::make('code')->label('Powód')->options(Order::REJECTION_CODES)->required(),
                    Textarea::make('note')->label('Opis (obowiązkowy dla powodu „Inne”)')->maxLength(255),
                ])
                ->action(fn (Order $record, array $data, Action $action) => Support::run($action, fn () => app(RejectOrder::class)->handle(Support::user(), $record, $data['code'], $data['note'] ?? null)))
                ->successNotificationTitle('Zamówienie odrzucone'),
            $step('confirmPayment', 'Potwierdź wpływ', OrderState::Confirmed, OrderState::Paid, 'Potwierdzono wpływ'),
            $step('startPreparation', 'Rozpocznij przygotowanie', OrderState::Paid, OrderState::Preparing, 'Zamówienie w przygotowaniu'),
            $step('markReady', 'Gotowe do odbioru', OrderState::Preparing, OrderState::Ready, 'Zamówienie gotowe do odbioru'),
            $step('markPickedUp', 'Wydano towar', OrderState::Ready, OrderState::PickedUp, 'Zapisano wydanie'),
            Action::make('close')->label('Zamknij bez realizacji')->color('danger')->visible(fn (Order $r) => ! $r->state->isFinal())
                ->modalDescription(fn (Order $r) => $r->state->holdsReservation() ? 'Zarezerwowane ilości wrócą do dostępnych.' : null)
                ->schema([
                    Textarea::make('reason')->label('Powód')->required()->maxLength(200),
                    Textarea::make('settlement')->label('Rozliczenie poza platformą (wymagane po potwierdzeniu wpływu)')->maxLength(200),
                ])
                ->action(fn (Order $record, array $data, Action $action) => Support::run($action, fn () => app(CloseOrder::class)->handle(Support::user(), $record, $data['reason'], $data['settlement'] ?? null)))
                ->successNotificationTitle('Zamówienie zamknięte'),
        ];
    }

    public static function getPages(): array
    {
        return ['index' => ListOrders::route('/'), 'view' => ViewOrder::route('/{record}')];
    }
}
