<?php

namespace App\Http\Middleware;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** `access:tank_view` — bramka trasy przez ResolveAccess (jedyne źródło decyzji i komunikatów). */
class RequireAccess
{
    public function __construct(private ResolveAccess $access) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $decision = $this->access->handle($request->user(), Feature::from($feature));

        if ($decision->allowed) {
            return $next($request);
        }

        return $request->user()
            ? abort(403, $decision->message)
            : redirect()->guest(route('login'))->with('status', $decision->message);
    }
}
