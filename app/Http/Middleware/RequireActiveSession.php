<?php

namespace App\Http\Middleware;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveSession
{
    public function __construct(private ResolveAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user) {
            $decision = $this->access->handle($user, Feature::Login);
            if (! $decision->allowed) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                abort(403, $decision->message);
            }
        }

        return $next($request);
    }
}
