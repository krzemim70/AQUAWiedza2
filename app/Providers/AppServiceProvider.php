<?php

namespace App\Providers;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\LegacyAwareHasher;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Settings\SettingChanged;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Actions\EvaluateShop;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    public function boot(): void
    {
        // hasła zaimportowane z WordPressa działają do pierwszego logowania, potem są przepisywane na bcrypt
        Hash::extend('bcrypt', fn () => new LegacyAwareHasher(config('hashing.bcrypt', [])));

        // bramki admina to ta sama decyzja co wejście do panelu (ResolveAccess)
        $admin = fn (User $user) => app(ResolveAccess::class)->handle($user, Feature::AdminPanel)->allowed;
        Gate::define('manage-settings', $admin);
        Gate::define('manage-users', $admin);
        Gate::define('manage-knowledge', $admin);

        // zmiana progu lub limitu uruchamia ponowną ocenę sklepów w przygotowaniu
        Event::listen(SettingChanged::class, function (SettingChanged $event) {
            if ($event->version->key === SettingKey::ShopActivation->value) {
                app(Settings::class)->forget(SettingKey::ShopActivation); // zdarzenie wychodzi przed odświeżeniem pamięci w ChangeSetting
                Shop::where('state', ShopState::Preparation->value)->each(fn (Shop $shop) => app(EvaluateShop::class)->handle($shop));
            }
        });
    }
}
