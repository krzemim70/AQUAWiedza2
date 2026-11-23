<?php

namespace App\Filament\Resources\Photos;

use App\Domain\KnowledgeBase\Actions\ApprovePhotos;
use App\Domain\KnowledgeBase\Models\Photo;
use App\Filament\Resources\Photos\Pages\ListPhotos;
use App\Filament\Support;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class PhotoResource extends Resource
{
    protected static ?string $model = Photo::class;

    protected static ?string $slug = 'zdjecia';

    protected static ?string $modelLabel = 'zdjęcie';

    protected static ?string $pluralModelLabel = 'Zdjęcia katalogu';

    protected static string|UnitEnum|null $navigationGroup = 'Baza wiedzy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-knowledge');
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

    /** @return Builder<Photo> */
    public static function getEloquentQuery(): Builder
    {
        return Photo::query()->with(['record.activeEntry']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                ImageColumn::make('preview')->label('Miniatura')->disk('public')->size(64)
                    ->state(fn (Photo $record): ?string => $record->url ? url($record->url) : $record->file_path)
                    ->alt(fn (Photo $record): string => 'Zdjęcie: '.($record->record->activeEntry?->label() ?? $record->record->legacy_id))
                    ->extraImgAttributes(['loading' => 'lazy', 'referrerpolicy' => 'no-referrer']),
                TextColumn::make('record.legacy_id')->label('ID rekordu')->searchable(),
                TextColumn::make('name')->label('Nazwa')->state(fn (Photo $record): ?string => $record->record->activeEntry?->label())->placeholder('—'),
                TextColumn::make('author')->label('Autor')->placeholder('—')->wrap(),
                TextColumn::make('licence')->label('Licencja')->placeholder('—')->wrap(),
                TextColumn::make('verified')->label('Status')->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Zatwierdzone' : 'Niezatwierdzone'),
            ])
            ->filters([
                TernaryFilter::make('verified')->label('Status zdjęcia')->placeholder('Wszystkie')
                    ->trueLabel('Zatwierdzone')->falseLabel('Niezatwierdzone'),
            ])
            ->toolbarActions([
                BulkAction::make('approve')
                    ->label('Zatwierdź zdjęcia')
                    ->authorize('manage-knowledge')
                    ->requiresConfirmation()
                    ->modalDescription('Potwierdź, że sprawdzone zdjęcia mogą zostać opublikowane z podanym autorstwem i licencją.')
                    ->action(fn (Collection $records, BulkAction $action) => Support::run(
                        $action,
                        fn () => app(ApprovePhotos::class)->handle(Support::user(), $records->modelKeys()),
                    ))
                    ->successNotificationTitle('Zatwierdzono wybrane zdjęcia')
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPhotos::route('/')];
    }
}
