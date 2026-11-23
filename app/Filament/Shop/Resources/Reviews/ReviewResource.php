<?php

namespace App\Filament\Shop\Resources\Reviews;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Shops\Access;
use App\Domain\Shops\Models\Review;
use App\Domain\Shops\Models\Shop;
use App\Filament\Shop\Resources\Reviews\Pages\ListReviews;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $slug = 'opinie';

    protected static ?string $modelLabel = 'opinia';

    protected static ?string $pluralModelLabel = 'Opinie o sklepie';

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();
        $shop = Filament::getTenant();

        return $user instanceof User && $shop instanceof Shop
            && app(ResolveAccess::class)->handle($user, Feature::ShopPanel)->allowed
            && app(Access::class)->isMember($user, $shop->id);
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
                TextColumn::make('rating')->label('Ocena (1–5)')->sortable(),
                TextColumn::make('comment')->label('Komentarz')->placeholder('—')->wrap()
                    ->state(fn (Review $record) => $record->hidden_reason === null ? $record->comment : null),
                TextColumn::make('created_at')->label('Dodano')->dateTime()->timezone(config('app.display_timezone'))->sortable(),
                TextColumn::make('visibility')->label('Widoczność')->state(fn (Review $record) => $record->hidden_reason === null ? 'Widoczna' : 'Ukryta'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListReviews::route('/')];
    }
}
