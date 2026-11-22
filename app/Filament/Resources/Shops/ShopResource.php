<?php

namespace App\Filament\Resources\Shops;

use App\Domain\Shops\Actions\GrantActivationException;
use App\Domain\Shops\Actions\ResumeShop;
use App\Domain\Shops\Actions\SuspendShop;
use App\Domain\Shops\Models\MemberRole;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;
use App\Filament\Resources\Shops\Pages\ListShops;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Sklepy w pilocie: stan, odstępstwo od progu aktywacji, wstrzymanie i wznowienie. Weryfikacja firmy to „Zgłoszenia sklepów”. */
class ShopResource extends Resource
{
    protected static ?string $model = Shop::class;

    protected static ?string $slug = 'sklepy';

    protected static ?string $modelLabel = 'sklep';

    protected static ?string $pluralModelLabel = 'Sklepy';

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
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['members' => fn ($q) => $q->where('role', MemberRole::Owner->value)->with('user')])
                ->withCount(['offers as live_offers_count' => fn ($q) => $q->live()]))
            ->columns([
                TextColumn::make('name')->label('Firma')->searchable(),
                TextColumn::make('nip')->label('NIP')->searchable(),
                TextColumn::make('owner')->label('Właściciel')->getStateUsing(fn (Shop $r) => $r->members->first()?->user->email),
                TextColumn::make('state')->label('Status')->badge()->formatStateUsing(fn (ShopState $state) => $state->label()),
                TextColumn::make('live_offers_count')->label('Opublikowane oferty'),
                TextColumn::make('activated_at')->label('Aktywowano')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('state')->label('Status')->options(collect(ShopState::cases())->mapWithKeys(fn (ShopState $s) => [$s->value => $s->label()])),
            ])
            ->recordActions([
                self::decide('exception', 'Odstępstwo od progu', fn (Shop $r) => in_array($r->state, [ShopState::Preparation, ShopState::Active, ShopState::Suspended], true), [
                    DateTimePicker::make('from')->label('Od')->required()->default(now()),
                    DateTimePicker::make('to')->label('Do')->required()->after('from'),
                ], fn (Shop $r, array $d) => app(GrantActivationException::class)->handle(auth()->user(), $r, Carbon::parse($d['from']), Carbon::parse($d['to']), $d['reason'])),
                self::decide('suspend', 'Wstrzymaj', fn (Shop $r) => in_array($r->state, [ShopState::Preparation, ShopState::Active], true), [],
                    fn (Shop $r, array $d) => app(SuspendShop::class)->handle(auth()->user(), $r, $d['reason'])),
                self::decide('resume', 'Wznów', fn (Shop $r) => $r->state === ShopState::Suspended, [],
                    fn (Shop $r, array $d) => app(ResumeShop::class)->handle(auth()->user(), $r, $d['reason'])),
            ]);
    }

    /** @param list<mixed> $fields */
    private static function decide(string $name, string $label, Closure $visible, array $fields, Closure $call): Action
    {
        return Action::make($name)->label($label)->visible($visible)->requiresConfirmation()
            ->schema([...$fields, TextInput::make('reason')->label('Powód')->required()->maxLength(255)])
            ->action(function (Shop $record, array $data, Action $action) use ($call): void {
                try {
                    $call($record, $data);
                } catch (ValidationException $e) {
                    $action->failureNotificationTitle(collect($e->errors())->flatten()->implode(' '))->failure();
                    $action->halt();
                } catch (AuthorizationException $e) {
                    $action->failureNotificationTitle($e->getMessage())->failure();
                    $action->halt();
                }
                $action->success();
            })
            ->successNotificationTitle('Zapisano');
    }

    public static function getPages(): array
    {
        return ['index' => ListShops::route('/')];
    }
}
