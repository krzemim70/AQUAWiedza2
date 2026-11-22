<?php

namespace App\Filament\Resources\Entries;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Entry;
use App\Filament\Resources\Entries\Pages\ListEntries;
use App\Filament\Resources\Entries\Pages\ViewEntry;
use App\Filament\Resources\Entries\RelationManagers\PhotosRelationManager;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Rekordy aktywnej partii katalogu. Edycja atrybutów poza zakresem (zmiana danych = nowy import). */
class EntryResource extends Resource
{
    protected static ?string $model = Entry::class;

    protected static ?string $slug = 'baza-wiedzy';

    protected static ?string $modelLabel = 'rekord';

    protected static ?string $pluralModelLabel = 'Baza wiedzy';

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

    /** @return Builder<Entry> */
    public static function getEloquentQuery(): Builder
    {
        return Entry::query()->inActiveBatch()->with('record');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('record.area')->label('Obszar')->badge()->formatStateUsing(fn (Area $state) => $state->label()),
                TextColumn::make('record.legacy_id')->label('ID')->searchable(),
                TextColumn::make('label')->label('Nazwa')->getStateUsing(fn (Entry $r) => $r->label())
                    ->searchable(query: fn (Builder $query, string $search) => $query->where('search_text', 'like', '%'.addcslashes(Catalog::fold($search), '%_\\').'%')),
                TextColumn::make('name_latin')->label('Nazwa łacińska')->placeholder('—'),
                IconColumn::make('has_description')->label('Opis')->boolean()->getStateUsing(fn (Entry $r) => $r->description !== null),
                TextColumn::make('photos_count')->label('Zdjęcia')->counts('photos'),
            ])
            ->filters([
                SelectFilter::make('area')->label('Obszar')
                    ->options(collect(Area::cases())->mapWithKeys(fn (Area $a) => [$a->value => $a->label()]))
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn ($q, $area) => $q->whereHas('record', fn ($r) => $r->where('area', $area)))),
                TernaryFilter::make('description')->label('Opis')->placeholder('Wszystkie')->trueLabel('Z opisem')->falseLabel('Bez opisu')
                    ->queries(true: fn (Builder $q) => $q->whereHas('description'), false: fn (Builder $q) => $q->whereDoesntHave('description'), blank: fn (Builder $q) => $q),
                TernaryFilter::make('photo')->label('Zdjęcie zweryfikowane')->placeholder('Wszystkie')->trueLabel('Tak')->falseLabel('Nie')
                    ->queries(true: fn (Builder $q) => $q->whereHas('photos', fn ($p) => $p->where('verified', true)), false: fn (Builder $q) => $q->whereDoesntHave('photos', fn ($p) => $p->where('verified', true)), blank: fn (Builder $q) => $q),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('record.area')->label('Obszar')->formatStateUsing(fn (Area $state) => $state->label()),
            TextEntry::make('record.legacy_id')->label('ID'),
            TextEntry::make('label')->label('Nazwa')->getStateUsing(fn (Entry $r) => $r->label()),
            TextEntry::make('description.body')->label('Opis')->placeholder('Brak opisu')->columnSpanFull(),
            TextEntry::make('description.source_ref')->label('Źródło opisu')->placeholder('—')->columnSpanFull(),
            KeyValueEntry::make('fields')->label('Atrybuty (wartości z pliku)')->columnSpanFull(),
        ]);
    }

    public static function getRelations(): array
    {
        return [PhotosRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ListEntries::route('/'), 'view' => ViewEntry::route('/{record}')];
    }
}
