<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const CSP = "default-src 'self'; base-uri 'self'; form-action 'self'; "
        ."frame-ancestors 'self'; object-src 'none'; "
        ."script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; "
        ."img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; worker-src 'self' blob:";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api', 'api/*')) {
            return $response;
        }

        $response->headers->add([
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy-Report-Only' => self::CSP,
        ]);

        // Serwis nieindeksowany (staging) albo panel: nagłówek obejmuje też zasoby i strony bez układu.
        if (! config('seo.indexable') || $request->is('admin*', 'panel-sklepu*')) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        if ($this->isPrivate($request)) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }

    private function isPrivate(Request $request): bool
    {
        if ($request->is('offline')) {
            return false;
        }

        return $request->user() !== null
            || $request->is(
                'moje-konto', 'moje-konto/*',
                'moje-akwarium', 'moje-akwarium/*',
                'koszyk', 'koszyk/*',
                'zamowienia', 'zamowienia/*',
                'zgody', 'zgody/*',
                'admin', 'admin/*',
                'panel-sklepu', 'panel-sklepu/*',
                'doradca/moj-zbiornik', 'doradca/moj-zbiornik/*',
                'doradca/kreator', 'doradca/kreator/*',
            );
    }
}
