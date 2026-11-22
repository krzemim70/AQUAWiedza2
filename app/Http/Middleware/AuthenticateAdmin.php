<?php

namespace App\Http\Middleware;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Auth;

class AuthenticateAdmin extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        if (! Auth::guard('admin')->check() && ($user = Auth::guard('web')->user())) {
            $decision = app(ResolveAccess::class)->handle($user, Feature::AdminPanel);
            abort_unless($decision->allowed, 403, $decision->message);
        }

        parent::authenticate($request, $guards);
    }
}
