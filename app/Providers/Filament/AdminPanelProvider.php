<?php

namespace App\Providers\Filament;

use App\Filament\Auth\AdminLogin;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\RequireAdminTwoFactor;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $requiresTwoFactor = (bool) config('auth.admin_requires_2fa');

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Aikwarium — panel administratora')
            ->navigationGroups([
                'Konta i sklepy',
                'Baza wiedzy',
                'System',
            ])
            ->authGuard('admin')
            ->login(AdminLogin::class)
            ->multiFactorAuthentication($requiresTwoFactor ? AppAuthentication::make()->recoverable() : [], isRequired: $requiresTwoFactor)
            ->multiFactorAuthenticationRequiredMiddlewareName(RequireAdminTwoFactor::class)
            ->persistentMiddleware([RequireAdminTwoFactor::class])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticateAdmin::class,
                AuthenticateSession::class,
            ], isPersistent: true);
    }
}
