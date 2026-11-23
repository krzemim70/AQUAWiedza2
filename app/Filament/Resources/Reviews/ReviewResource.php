<?php

namespace App\Filament\Resources\Reviews;

use App\Domain\Shops\Actions\SetReviewVisibility;
use App\Domain\Shops\Models\Review;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Support;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $slug = 'opinie';

    protected static ?string $modelLabel = 'opinia';

    protected static ?string $pluralModelLabel = 'Opinie o sklepach';

    protected static string|UnitEnum|null $navigationGroup = 'Konta i sklepy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?int $navigationSort = 5;

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
                TextColumn::make('shop.name')->label('Sklep')->searchable(),
                TextColumn::make('rating')->label('Ocena (1–5)')->sortable(),
                TextColumn::make('comment')->label('Komentarz')->placeholder('—')->wrap(),
                TextColumn::make('created_at')->label('Dodano')->dateTime()->timezone(config('app.display_timezone'))->sortable(),
                TextColumn::make('visibility')->label('Widoczność')->state(fn (Review $record) => $record->hidden_reason === null ? 'Widoczna' : 'Ukryta'),
                TextColumn::make('hidden_reason')->label('Powód ukrycia')->placeholder('—')->wrap(),
            ])
            ->recordActions([
                Action::make('hide')->label('Ukryj opinię')->color('danger')
                    ->authorize('manage-users')
                    ->visible(fn (Review $record) => $record->hidden_reason === null)
                    ->requiresConfirmation()
                    ->extraAttributes(['style' => 'min-height: 2.75rem'])
                    ->schema([Textarea::make('reason')->label('Powód ukrycia')->required()->maxLength(255)])
                    ->action(fn (Review $record, array $data, Action $action) => Support::run(
                        $action,
                        fn () => app(SetReviewVisibility::class)->handle(Support::user(), $record, visible: false, reason: $data['reason']),
                    ))
                    ->successNotificationTitle('Opinia została ukryta'),
                Action::make('restore')->label('Przywróć opinię')
                    ->authorize('manage-users')
                    ->visible(fn (Review $record) => $record->hidden_reason !== null)->requiresConfirmation()
                    ->extraAttributes(['style' => 'min-height: 2.75rem'])
                    ->action(fn (Review $record, Action $action) => Support::run(
                        $action,
                        fn () => app(SetReviewVisibility::class)->handle(Support::user(), $record, visible: true),
                    ))
                    ->successNotificationTitle('Opinia została przywrócona'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListReviews::route('/')];
    }
}
