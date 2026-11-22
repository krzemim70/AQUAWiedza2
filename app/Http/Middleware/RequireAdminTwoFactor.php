<?php

namespace App\Http\Middleware;

use App\Filament\Auth\AdminLogin;
use App\Models\User;
use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequireAdminTwoFactor extends EnsureMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        return parent::handle($request, function (Request $request) use ($next) {
            $guard = Auth::guard('admin');
            $user = $guard->user();
            $secret = $user instanceof User ? $user->getAppAuthenticationSecret() : null;

            // Sesja rozpoczęta przed konfiguracją służy wyłącznie do ustawienia 2FA.
            if ($secret === null || $request->session()->get(AdminLogin::VERIFIED_SECRET) !== hash('sha256', $secret)) {
                $guard->logoutCurrentDevice();
                $request->session()->regenerate(true);

                return redirect()->guest(route('filament.admin.auth.login'));
            }

            return $next($request);
        });
    }
}
