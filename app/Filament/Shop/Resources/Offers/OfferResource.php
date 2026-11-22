<?php

namespace App\Filament\Shop\Resources\Offers;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Actions\PublishOffer;
use App\Domain\Shops\Actions\SaveOffer;
use App\Domain\Shops\Actions\SetAvailableQuantity;
use App\Domain\Shops\Actions\WithdrawOffer;
use App\Domain\Shops\Data\OfferData;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use App\Filament\Shop\Resources\Offers\Pages\ListOffers;
use App\Filament\Shop\Support;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static ?string $slug = 'oferty';

    protected static ?string $modelLabel = 'oferta';

    protected static ?string $pluralModelLabel = 'Oferty';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('location'))
            ->columns([
                TextColumn::make('name')->label('Pozycja')->getStateUsing(fn (Offer $r) => self::name($r)),
                TextColumn::make('variant')->label('Wariant')->placeholder('—'),
                TextColumn::make('location.name')->label('Placówka'),
                TextColumn::make('price_grosze')->label('Cena')->formatStateUsing(fn (int $state) => Support::money($state)),
                TextColumn::make('valid_to')->label('Ważna do')->dateTime()->timezone(config('app.display_timezone')),
                TextColumn::make('state')->label('Status')->badge()->formatStateUsing(fn (OfferState $state) => $state->label()),
                TextColumn::make('free_qty')->label('Dostępna ilość'),
                TextColumn::make('reserved_qty')->label('Zarezerwowana ilość'),
            ])
            ->recordActions([
                Action::make('edit')->label('Edytuj warunki')
                    ->fillForm(fn (Offer $r) => [
                        'variant' => $r->variant, 'price' => $r->price_grosze / 100,
                        'valid_from' => $r->valid_from, 'valid_to' => $r->valid_to,
                    ])
                    ->schema(self::terms())
                    ->action(fn (Offer $record, array $data, Action $action) => Support::run($action, fn () => app(SaveOffer::class)->handle(
                        Support::user(), Support::shop(), self::data($data, $record), $record,
                    )))
                    ->successNotificationTitle('Zapisano warunki oferty'),
                Action::make('publish')->label('Opublikuj')->visible(fn (Offer $r) => $r->state !== OfferState::Published)
                    ->requiresConfirmation()
                    ->action(fn (Offer $record, Action $action) => Support::run($action, fn () => app(PublishOffer::class)->handle(Support::user(), $record)))
                    ->successNotificationTitle('Oferta opublikowana'),
                Action::make('withdraw')->label('Wycofaj')->color('danger')->visible(fn (Offer $r) => $r->state === OfferState::Published)
                    ->requiresConfirmation()
                    ->action(fn (Offer $record, Action $action) => Support::run($action, fn () => app(WithdrawOffer::class)->handle(Support::user(), $record)))
                    ->successNotificationTitle('Oferta wycofana'),
                Action::make('quantity')->label('Zmień dostępną ilość')
                    ->fillForm(fn (Offer $r) => ['qty' => $r->free_qty, 'version' => $r->version])
                    ->schema([
                        Hidden::make('version'),
                        TextInput::make('qty')->label('Nowa dostępna ilość')->numeric()->integer()->minValue(0)->required()
                            ->helperText('Podaj ilość całkowitą, nie przyrost. Ilość zarezerwowana pozostaje bez zmian.'),
                        Checkbox::make('confirmed')->label('Potwierdzam, że ta ilość zgadza się ze stanem fizycznym')->accepted(),
                        Textarea::make('reason')->label('Powód zmiany')->maxLength(255),
                    ])
                    ->action(fn (Offer $record, array $data, Action $action) => Support::run($action, fn () => app(SetAvailableQuantity::class)->handle(
                        Support::user(), $record, (int) $data['version'], (int) $data['qty'], (bool) $data['confirmed'], $data['reason'] ?? null,
                    )))
                    ->successNotificationTitle('Zapisano dostępną ilość'),
                Action::make('history')->label('Historia ilości')
                    ->modalContent(fn (Offer $r) => view('shops.offer-history', ['movements' => $r->movements()->with('actor')->get()]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Zamknij'),
            ]);
    }

    /**
     * Formularz nowej oferty: wybór pozycji z wyszukiwarki katalogu.
     *
     * @return list<mixed>
     */
    public static function createForm(): array
    {
        $catalog = app(Catalog::class);

        return [
            Select::make('record')->label('Pozycja z katalogu')->required()->searchable()
                ->getSearchResultsUsing(fn (string $search) => collect($catalog->search($search))
                    ->mapWithKeys(fn ($e) => [$e->area->value.':'.$e->id => $e->area->label().' — '.$e->name])->all())
                ->getOptionLabelUsing(function (string $value) use ($catalog) {
                    [$area, $id] = explode(':', $value, 2);

                    return $catalog->labels(Area::from($area), [$id])[$id] ?? $value;
                }),
            Select::make('location_id')->label('Placówka odbioru')->required()
                ->options(fn () => Support::shop()->locations()->where('active', true)->pluck('name', 'id')),
            ...self::terms(),
            TextInput::make('free_qty')->label('Dostępna ilość')->numeric()->integer()->minValue(0)->default(0)->required(),
        ];
    }

    /** @return list<mixed> */
    public static function terms(): array
    {
        return [
            TextInput::make('variant')->label('Wariant')->maxLength(120)->helperText('Np. rozmiar lub opakowanie. Może być puste.'),
            TextInput::make('price')->label('Cena (zł)')->numeric()->minValue(0.01)->step(0.01)->required(),
            DateTimePicker::make('valid_from')->label('Ważna od')->required()->default(now())->timezone(config('app.display_timezone')),
            DateTimePicker::make('valid_to')->label('Ważna do')->required()->after('valid_from')->timezone(config('app.display_timezone')),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function data(array $data, ?Offer $offer = null): OfferData
    {
        [$area, $id] = $offer ? [$offer->area, $offer->legacy_id] : [Area::from(explode(':', $data['record'], 2)[0]), explode(':', $data['record'], 2)[1]];

        return new OfferData(
            (int) ($offer->location_id ?? $data['location_id']), $area, $id, (string) ($data['variant'] ?? ''),
            (int) round((float) $data['price'] * 100), Carbon::parse($data['valid_from']), Carbon::parse($data['valid_to']),
            (int) ($data['free_qty'] ?? 0),
        );
    }

    private static function name(Offer $offer): string
    {
        return app(Catalog::class)->labels($offer->area, [$offer->legacy_id])[$offer->legacy_id] ?? $offer->legacy_id.' (poza katalogiem)';
    }

    public static function getPages(): array
    {
        return ['index' => ListOffers::route('/')];
    }
}
