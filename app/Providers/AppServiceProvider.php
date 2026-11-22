<?php

namespace App\Providers;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        // bramki admina to ta sama decyzja co wejście do panelu (ResolveAccess)
        $admin = fn (User $user) => app(ResolveAccess::class)->handle($user, Feature::AdminPanel)->allowed;
        Gate::define('manage-settings', $admin);
        Gate::define('manage-users', $admin);
        Gate::define('manage-knowledge', $admin);

        // ważność linku aktywacyjnego z ustawień (zamiast auth.verification.expire)
        VerifyEmail::createUrlUsing(fn (User $user) => URL::temporarySignedRoute(
            'verification.verify',
            now()->addSeconds((int) app(Settings::class)->get(SettingKey::ActivationLifetime)),
            ['id' => $user->getKey(), 'hash' => hash('sha256', $user->getEmailForVerification())],
        ));
    }
}
