<?php

namespace App\Filament\Resources\ShopApplications;

use App\Domain\IdentityAccess\Actions\ApproveShopApplication;
use App\Domain\IdentityAccess\Actions\RejectShopApplication;
use App\Domain\IdentityAccess\Models\ShopApplication;
use App\Filament\Resources\ShopApplications\Pages\ListShopApplications;
use App\Filament\Support;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ShopApplicationResource extends Resource
{
    protected static ?string $model = ShopApplication::class;

    protected static ?string $slug = 'zgloszenia-sklepow';

    protected static ?string $modelLabel = 'zgłoszenie sklepu';

    protected static ?string $pluralModelLabel = 'Zgłoszenia sklepów';

    protected static string|UnitEnum|null $navigationGroup = 'Konta i sklepy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 2;

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
            ->columns([
                TextColumn::make('company_name')->label('Firma')->searchable(),
                TextColumn::make('nip')->label('NIP')->searchable(),
                TextColumn::make('user.email')->label('E-mail właściciela'),
                TextColumn::make('phone')->label('Telefon'),
                TextColumn::make('address')->label('Adres'),
                TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state) => self::statuses()[$state] ?? $state),
                TextColumn::make('decision_reason')->label('Uzasadnienie')->placeholder('—'),
                TextColumn::make('created_at')->label('Zgłoszono')->dateTime()->timezone((string) config('app.display_timezone')),
            ])
            ->filters([SelectFilter::make('status')->label('Status')->options(self::statuses())->default(ShopApplication::PENDING)])
            ->recordActions([
                self::decide('approve', 'Zatwierdź', false, fn ($record, $reason) => app(ApproveShopApplication::class)->handle(Support::user(), $record, $reason)),
                self::decide('reject', 'Odrzuć', true, fn ($record, $reason) => app(RejectShopApplication::class)->handle(Support::user(), $record, (string) $reason)),
            ]);
    }

    /** @return array<string, string> */
    private static function statuses(): array
    {
        return [
            ShopApplication::PENDING => 'Oczekuje na weryfikację',
            ShopApplication::APPROVED => 'Zatwierdzone',
            ShopApplication::REJECTED => 'Odrzucone',
        ];
    }

    private static function decide(string $name, string $label, bool $reasonRequired, Closure $call): Action
    {
        return Action::make($name)
            ->label($label)
            ->authorize('manage-users')
            ->visible(fn (ShopApplication $record) => $record->status === ShopApplication::PENDING)
            ->requiresConfirmation()
            ->schema([TextInput::make('reason')->label('Uzasadnienie')->required($reasonRequired)->maxLength(255)])
            ->action(fn (ShopApplication $record, array $data, Action $action) => Support::run($action, fn () => $call($record, $data['reason'] ?? null)))
            ->successNotificationTitle('Zapisano decyzję');
    }

    public static function getPages(): array
    {
        return ['index' => ListShopApplications::route('/')];
    }
}
