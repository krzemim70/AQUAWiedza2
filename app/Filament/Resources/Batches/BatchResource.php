<?php

namespace App\Filament\Resources\Batches;

use App\Domain\KnowledgeBase\Actions\ActivateBatch;
use App\Domain\KnowledgeBase\Models\Batch;
use App\Filament\Resources\Batches\Pages\ListBatches;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/** Partie importu bazy wiedzy: stan, liczby rekordów i przywracanie wcześniejszej partii. */
class BatchResource extends Resource
{
    protected static ?string $model = Batch::class;

    protected static ?string $slug = 'importy';

    protected static ?string $modelLabel = 'import';

    protected static ?string $pluralModelLabel = 'Importy';

    public static function canViewAny(): bool
    {
        return Gate::allows('manage-knowledge');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('Partia'),
                TextColumn::make('state')->label('Stan')->badge()->formatStateUsing(fn (string $state) => self::states()[$state] ?? $state),
                TextColumn::make('counts')->label('Rekordy')->getStateUsing(fn (Batch $r) => implode(', ', array_map(fn (string $area, int $n) => "$area: $n", array_keys($r->counts), $r->counts)) ?: null)->placeholder('—'),
                TextColumn::make('errors')->label('Błędy')->getStateUsing(fn (Batch $r) => count($r->errors) ?: '—')
                    ->tooltip(fn (Batch $record) => implode("\n", array_slice($record->errors, 0, 10)) ?: null),
                TextColumn::make('author.name')->label('Autor')->placeholder('konsola'),
                TextColumn::make('created_at')->label('Utworzono')->dateTime(),
                TextColumn::make('activated_at')->label('Aktywowano')->dateTime()->placeholder('—'),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Aktywuj')
                    ->visible(fn (Batch $record) => in_array($record->state, [Batch::PENDING, Batch::SUPERSEDED], true))
                    ->requiresConfirmation()
                    ->action(function (Batch $record, Action $action): void {
                        app(ActivateBatch::class)->handle($record, auth()->user());
                        $action->success();
                    })
                    ->successNotificationTitle('Aktywowano partię'),
            ]);
    }

    /** @return array<string, string> */
    private static function states(): array
    {
        return [
            Batch::PENDING => 'Zapisana',
            Batch::ACTIVE => 'Aktywna',
            Batch::SUPERSEDED => 'Zastąpiona',
            Batch::FAILED => 'Odrzucona',
        ];
    }

    public static function getPages(): array
    {
        return ['index' => ListBatches::route('/')];
    }
}
