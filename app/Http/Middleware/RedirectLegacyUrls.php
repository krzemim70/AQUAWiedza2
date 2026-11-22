<?php

namespace App\Http\Middleware;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** 301: końcowy ukośnik (jak w WordPressie) i stare adresy z `config('seo.redirects')`. Tylko GET/HEAD. */
class RedirectLegacyUrls
{
    public function __construct(private Catalog $catalog) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        $path = rtrim($request->getPathInfo(), '/');
        $target = $path === '/oferty-w-twojej-okolicy' ? $this->offer($request) : config('seo.redirects')[$path] ?? null;

        if ($target === null && $path !== '' && $path !== $request->getPathInfo()) {
            $target = $path;
            $query = $request->getQueryString();
            $target .= $query === null ? '' : '?'.$query;
        }

        return $target === null ? $next($request) : redirect($target, 301);
    }

    private function offer(Request $request): string
    {
        $area = Area::tryFrom(strtolower((string) $request->query('aqs_db')));
        $id = (string) $request->query('rid');

        return $area !== null && $id !== '' && $this->catalog->find($area, $id) !== null
            ? route('catalog.show', [$area->value, $id], false)
            : '/doradca';
    }
}
