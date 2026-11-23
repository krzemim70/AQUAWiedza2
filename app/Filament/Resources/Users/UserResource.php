<?php

namespace App\Filament\Resources\Users;

use App\Domain\IdentityAccess\Actions\ActivateAccount;
use App\Domain\IdentityAccess\Actions\ChangeAccess;
use App\Domain\IdentityAccess\Data\ChangeAccessData;
use App\Domain\IdentityAccess\Models\AccessChangeKind as Kind;
use App\Domain\IdentityAccess\Models\AccountType;
use App\Domain\IdentityAccess\Models\Tier;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Support;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'uzytkownicy';

    protected static ?string $modelLabel = 'użytkownik';

    protected static ?string $pluralModelLabel = 'Użytkownicy';

    protected static string|UnitEnum|null $navigationGroup = 'Konta i sklepy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-users');
    }

    public static function canView(Model $record): bool
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
            ->columns([
                TextColumn::make('name')->label('Nazwa')->searchable(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('type')->label('Typ')->formatStateUsing(fn (AccountType $state) => $state->label()),
                TextColumn::make('tier')->label('Poziom')->badge()->getStateUsing(fn (User $r) => $r->tier()->label()),
                TextColumn::make('fishroom_ends_at')->label('Koniec Fishroom')->dateTime()->timezone((string) config('app.display_timezone'))->placeholder('—'),
                TextColumn::make('state')->label('Stan konta')->badge()->getStateUsing(fn (User $r) => self::state($r)),
                IconColumn::make('verified')->label('Potwierdzony')->boolean()->getStateUsing(fn (User $r) => $r->hasVerifiedEmail()),
            ])
            ->filters([
                SelectFilter::make('type')->label('Typ')->options(collect(AccountType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])),
                SelectFilter::make('level')->label('Poziom')
                    ->options([Tier::Tank->value => 'Tank', Tier::Fishroom->value => 'Fishroom'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'fishroom' => $query->where('fishroom_ends_at', '>', now()),
                        'tank' => $query->where(fn ($w) => $w->whereNull('fishroom_ends_at')->orWhere('fishroom_ends_at', '<=', now())),
                        default => $query,
                    }),
                SelectFilter::make('account_state')->label('Stan konta')
                    ->options(['active' => 'Aktywne', 'pending' => 'Oczekuje na aktywację', 'suspended' => 'Zawieszone'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'suspended' => $query->whereNotNull('suspended_at'),
                        'pending' => $query->whereNull('suspended_at')->whereNull('email_verified_at'),
                        'active' => $query->whereNull('suspended_at')->whereNotNull('email_verified_at'),
                        default => $query,
                    }),
                TernaryFilter::make('verified')->label('E-mail potwierdzony')->nullable()->attribute('email_verified_at'),
            ])
            ->recordActions([ViewAction::make()->authorize('manage-users'), ...self::accessActions()])
            ->toolbarActions([
                BulkAction::make('activate')
                    ->label('Aktywuj konto')
                    ->authorize('manage-users')
                    ->visible(fn () => Gate::allows('manage-users'))
                    ->requiresConfirmation()
                    ->schema([TextInput::make('reason')->label('Powód')->maxLength(255)])
                    ->action(fn (Collection $records, array $data, BulkAction $action) => Support::run($action, function () use ($records, $data): void {
                        $actor = Support::user();
                        foreach ($records as $record) {
                            if ($record instanceof User) {
                                app(ActivateAccount::class)->handle($record, $actor, $data['reason'] ?? null);
                            }
                        }
                    }))
                    ->successNotificationTitle('Aktywowano wybrane konta')
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label('Nazwa'),
            TextEntry::make('email')->label('E-mail'),
            TextEntry::make('type')->label('Typ')->formatStateUsing(fn (AccountType $state) => $state->label()),
            TextEntry::make('email_verified_at')->label('E-mail potwierdzony')->dateTime()->timezone((string) config('app.display_timezone'))->placeholder('nie'),
            TextEntry::make('tier')->label('Poziom')->badge()->getStateUsing(fn (User $r) => $r->tier()->label()),
            TextEntry::make('fishroom_ends_at')->label('Koniec Fishroom')->dateTime()->timezone((string) config('app.display_timezone'))->placeholder('—'),
            TextEntry::make('state')->label('Stan konta')->badge()->getStateUsing(fn (User $r) => self::state($r)),
            RepeatableEntry::make('history')->label('Historia zmian dostępu')
                ->getStateUsing(fn (User $r) => $r->accessChanges()->with('actor')->get()->map(fn ($c) => [
                    'at' => $c->created_at->timezone((string) config('app.display_timezone'))->toDateTimeString(),
                    'change' => $c->kind->label(),
                    'until' => $c->fishroom_ends_at?->toDateString() ?? '—',
                    'actor' => $c->actor->name ?? 'system',
                    'reason' => $c->reason,
                ])->all())
                ->schema([
                    TextEntry::make('at')->label('Kiedy'),
                    TextEntry::make('change')->label('Zmiana'),
                    TextEntry::make('until')->label('Fishroom do'),
                    TextEntry::make('actor')->label('Kto'),
                    TextEntry::make('reason')->label('Powód'),
                ])->columns(5),
        ]);
    }

    private static function state(User $user): string
    {
        return match (true) {
            $user->isSuspended() => 'Zawieszone',
            ! $user->hasVerifiedEmail() => 'Oczekuje na aktywację',
            default => 'Aktywne',
        };
    }

    /** @return list<Action> */
    public static function accessActions(): array
    {
        $reason = fn () => TextInput::make('reason')->label('Powód')->required()->maxLength(255);

        return [
            Action::make('activate')
                ->label('Aktywuj konto')
                ->authorize('manage-users')
                ->visible(fn (User $record) => ! $record->hasVerifiedEmail() && Gate::allows('manage-users'))
                ->requiresConfirmation()
                ->schema([TextInput::make('reason')->label('Powód')->maxLength(255)])
                ->action(fn (User $record, array $data, Action $action) => Support::run(
                    $action,
                    fn () => app(ActivateAccount::class)->handle($record, Support::user(), $data['reason'] ?? null),
                ))
                ->successNotificationTitle('Aktywowano konto'),
            self::accessAction('grantFishroom', 'Nadaj Fishroom', Kind::FishroomGranted)
                ->visible(fn (User $r) => ! $r->isSuspended())
                ->fillForm(fn (User $r) => ['ends_at' => self::defaultEnd($r)])
                ->schema([DatePicker::make('ends_at')->label('Koniec dostępu (włącznie)')->required(), $reason()]),
            self::accessAction('revokeFishroom', 'Przywróć Tank', Kind::FishroomRevoked)
                ->visible(fn (User $r) => $r->tier() === Tier::Fishroom)->color('gray')
                ->schema([$reason()]),
            self::accessAction('suspend', 'Zawieś', Kind::Suspended)
                ->visible(fn (User $r) => ! $r->isSuspended() && ! $r->is(auth()->user()))->color('danger')
                ->schema([$reason()]),
            self::accessAction('unsuspend', 'Odwieś', Kind::Unsuspended)
                ->visible(fn (User $r) => $r->isSuspended())
                ->schema([$reason()]),
        ];
    }

    /** Nowe nadanie startuje od teraz; przedłużenie od końca bieżącego Fishroom. Okres z ustawień. */
    private static function defaultEnd(User $user): Carbon
    {
        $period = app(Settings::class)->get(SettingKey::FishroomPeriod);
        $from = $user->tier() === Tier::Fishroom ? $user->fishroom_ends_at : now();

        return $period['unit'] === 'day' ? $from->copy()->addDays($period['count']) : $from->copy()->addMonthsNoOverflow($period['count']);
    }

    private static function accessAction(string $name, string $label, Kind $kind): Action
    {
        return Action::make($name)
            ->label($label)
            ->authorize('manage-users')
            ->requiresConfirmation()
            ->successNotificationTitle('Zapisano zmianę dostępu')
            ->action(fn (User $record, array $data, Action $action) => Support::run(
                $action,
                fn () => app(ChangeAccess::class)->handle(new ChangeAccessData(
                    Support::user(), $record, $kind, $data['reason'],
                    isset($data['ends_at']) ? Carbon::parse($data['ends_at'], 'UTC')->endOfDay() : null,
                )),
            ));
    }

    public static function getPages(): array
    {
        return ['index' => ListUsers::route('/'), 'view' => ViewUser::route('/{record}')];
    }
}
