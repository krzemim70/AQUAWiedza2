<?php

namespace App\Http\Middleware;

use App\Domain\IdentityAccess\Actions\AcceptConsents;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Konto bez zgody na aktualną wersję regulaminu i polityki trafia na stronę akceptacji. */
class RequireConsents
{
    public function __construct(private AcceptConsents $consents) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        return $user && ! $this->consents->current($user)
            ? redirect()->guest(route('consents'))
            : $next($request);
    }
}
