<?php

namespace App\Filament\Resources\Entries\RelationManagers;

use App\Domain\KnowledgeBase\Actions\SavePhoto;
use App\Domain\KnowledgeBase\Models\Entry;
use App\Domain\KnowledgeBase\Models\Photo;
use App\Filament\Support;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Metadane zdjęć rekordu (bez plików). */
class PhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Zdjęcia';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('url')->label('Adres URL')->limit(50)->placeholder('—'),
                TextColumn::make('file_path')->label('Plik')->placeholder('—'),
                TextColumn::make('author')->label('Autor')->placeholder('—'),
                TextColumn::make('licence')->label('Licencja')->placeholder('—'),
                IconColumn::make('verified')->label('Zweryfikowane')->boolean(),
            ])
            ->headerActions([$this->save('add', 'Dodaj zdjęcie')])
            ->recordActions([$this->save('edit', 'Edytuj')->fillForm(fn (Photo $record) => $record->only(['url', 'file_path', 'source_url', 'author', 'licence', 'verified']))]);
    }

    private function save(string $name, string $label): Action
    {
        return Action::make($name)
            ->label($label)
            ->authorize('manage-knowledge')
            ->schema([
                TextInput::make('url')->label('Adres URL')->maxLength(512),
                TextInput::make('file_path')->label('Ścieżka pliku')->maxLength(512),
                TextInput::make('source_url')->label('Adres źródła')->maxLength(512),
                TextInput::make('author')->label('Autor')->maxLength(255),
                TextInput::make('licence')->label('Licencja')->maxLength(255),
                Toggle::make('verified')->label('Zweryfikowane'),
            ])
            ->action(fn (array $data, Action $action, ?Photo $record = null) => Support::run($action, function () use ($data, $record): void {
                /** @var Entry $entry */
                $entry = $this->getOwnerRecord();
                app(SavePhoto::class)->handle(Support::user(), $entry->record, $data, $record);
            }))
            ->successNotificationTitle('Zapisano zdjęcie');
    }
}
