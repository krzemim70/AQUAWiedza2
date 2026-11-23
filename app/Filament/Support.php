<?php

namespace App\Filament;

use App\Models\User;
use Closure;
use DomainException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/** Wspólne pomocniki panelu administratora: bieżący użytkownik i obsługa błędów operacji domenowych. */
final class Support
{
    public static function user(): User
    {
        /** @var User */
        return Filament::auth()->user() ?? auth()->user();
    }

    /** Wykonuje operację domenową; błędy walidacji, domeny lub uprawnień pokazuje jako powiadomienie i przerywa akcję. */
    public static function run(Action $action, Closure $call): void
    {
        try {
            $call();
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->unique()->implode(' ');
            Notification::make()->danger()->title($message)->send();
            $action->failureNotificationTitle($message)->failure();
            $action->halt();
        } catch (DomainException|AuthorizationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $action->failureNotificationTitle($e->getMessage())->failure();
            $action->halt();
        }

        $action->success();
    }
}
