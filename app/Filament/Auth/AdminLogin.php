<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Forms\Components\Checkbox;
use Filament\Support\Components\Attributes\Exposed;

class AdminLogin extends Login
{
    public const VERIFIED_SECRET = 'admin_verified_totp';

    #[Exposed]
    public function authenticate(): ?LoginResponse
    {
        $user = $this->isSubmittingMultiFactorChallenge() ? $this->getUserUndertakingMultiFactorAuthentication() : null;
        $secret = $user instanceof User ? $user->getAppAuthenticationSecret() : null;

        $response = parent::authenticate();

        if ($response !== null) {
            session()->put(self::VERIFIED_SECRET, $secret === null ? null : hash('sha256', $secret));
        }

        return $response;
    }

    protected function getRememberFormComponent(): Checkbox
    {
        return Checkbox::make('remember')->hidden()->dehydrated(false);
    }
}
