<?php

namespace App\Filament\Shop\Resources\Members;

use App\Domain\Shops\Access;
use App\Domain\Shops\Actions\RemoveMember;
use App\Domain\Shops\Models\Member;
use App\Domain\Shops\Models\MemberRole;
use App\Filament\Shop\Resources\Members\Pages\ListMembers;
use App\Filament\Shop\Support;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Zespół sklepu: właściciel dodaje pracownika po adresie e-mail istniejącego konta i go usuwa. */
class MemberResource extends Resource
{
    protected static ?string $model = Member::class;

    protected static ?string $slug = 'zespol';

    protected static ?string $modelLabel = 'członek zespołu';

    protected static ?string $pluralModelLabel = 'Zespół';

    public static function canViewAny(): bool
    {
        return app(Access::class)->isOwner(Support::user(), Support::shop()->id);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('user.name')->label('Nazwa'),
                TextColumn::make('user.email')->label('E-mail'),
                TextColumn::make('role')->label('Rola')->badge()->formatStateUsing(fn (MemberRole $state) => $state->label()),
            ])
            ->recordActions([
                Action::make('remove')->label('Usuń z zespołu')->color('danger')
                    ->visible(fn (Member $r) => $r->role === MemberRole::Staff)
                    ->requiresConfirmation()
                    ->action(fn (Member $record, Action $action) => Support::run($action, fn () => app(RemoveMember::class)->handle(Support::user(), $record)))
                    ->successNotificationTitle('Usunięto z zespołu'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMembers::route('/')];
    }
}
