<?php

namespace App\Filament\Shop;

use App\Domain\Shops\Models\Shop;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Wspólne drobiazgi panelu sklepu: bieżący sklep, kwoty i obsługa błędów operacji domenowych. */
final class Support
{
    public static function shop(): Shop
    {
        /** @var Shop */
        return Filament::getTenant();
    }

    public static function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    public static function money(int $grosze): string
    {
        return number_format($grosze / 100, 2, ',', ' ').' zł';
    }

    /** Wykonuje operację domenową; błąd walidacji lub uprawnień pokazuje jako powiadomienie i przerywa akcję. */
    public static function run(Action $action, Closure $call): void
    {
        try {
            $call();
        } catch (ValidationException $e) {
            $action->failureNotificationTitle(collect($e->errors())->flatten()->implode(' '))->failure();
            $action->halt();
        } catch (AuthorizationException $e) {
            $action->failureNotificationTitle($e->getMessage())->failure();
            $action->halt();
        }
        $action->success();
    }
}
