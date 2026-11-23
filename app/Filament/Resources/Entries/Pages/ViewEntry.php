<?php

namespace App\Filament\Resources\Entries\Pages;

use App\Domain\KnowledgeBase\Actions\UpdateDescription;
use App\Domain\KnowledgeBase\Models\Entry;
use App\Filament\Resources\Entries\EntryResource;
use App\Filament\Support;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;

class ViewEntry extends ViewRecord
{
    protected static string $resource = EntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editDescription')
                ->label('Edytuj opis')
                ->authorize('manage-knowledge')
                ->fillForm(fn (Entry $record) => ['body' => $record->description?->body, 'source_ref' => $record->description?->source_ref])
                ->schema([
                    Textarea::make('body')->label('Opis')->required()->rows(12),
                    Textarea::make('source_ref')->label('Źródło opisu')->rows(2),
                ])
                ->action(fn (Entry $record, array $data, Action $action) => Support::run($action, function () use ($record, $data): void {
                    app(UpdateDescription::class)->handle(Support::user(), $record->record, $data['body'], $data['source_ref'] ?? null);
                    $record->unsetRelation('description');
                }))
                ->successNotificationTitle('Zapisano opis'),
        ];
    }
}
