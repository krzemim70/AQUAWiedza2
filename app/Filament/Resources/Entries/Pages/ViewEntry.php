<?php

namespace App\Filament\Resources\Entries\Pages;

use App\Domain\KnowledgeBase\Actions\UpdateDescription;
use App\Domain\KnowledgeBase\Models\Entry;
use App\Filament\Resources\Entries\EntryResource;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewEntry extends ViewRecord
{
    protected static string $resource = EntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editDescription')
                ->label('Edytuj opis')
                ->fillForm(fn (Entry $record) => ['body' => $record->description?->body, 'source_ref' => $record->description?->source_ref])
                ->schema([
                    Textarea::make('body')->label('Opis')->required()->rows(12),
                    Textarea::make('source_ref')->label('Źródło opisu')->rows(2),
                ])
                ->action(function (Entry $record, array $data, Action $action): void {
                    try {
                        app(UpdateDescription::class)->handle(auth()->user(), $record->record, $data['body'], $data['source_ref'] ?? null);
                    } catch (ValidationException $e) {
                        $action->failureNotificationTitle(collect($e->errors())->flatten()->implode(' '))->failure();
                        $action->halt();
                    }
                    $record->unsetRelation('description');
                    $action->success();
                })
                ->successNotificationTitle('Zapisano opis'),
        ];
    }
}
