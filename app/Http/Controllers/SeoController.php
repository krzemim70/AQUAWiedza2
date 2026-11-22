<?php

namespace App\Http\Controllers;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** robots.txt i sitemap.xml. Mapa jest w cache pod kluczem z ID aktywnej partii, więc nowa aktywacja ją unieważnia. */
class SeoController
{
    public function robots(): Response
    {
        $body = config('seo.indexable')
            ? "User-agent: *\nDisallow: /admin\nDisallow: /panel-sklepu\nDisallow: /moje-akwarium\nDisallow: /moje-konto\nDisallow: /koszyk\nDisallow: /zamowienia\nDisallow: /logowanie\nDisallow: /rejestracja\nDisallow: /haslo\n\nSitemap: ".route('sitemap')."\n"
            : "User-agent: *\nDisallow: /\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(Catalog $catalog): Response
    {
        $xml = Cache::remember('sitemap.'.($catalog->activeBatchId() ?? 'none'), now()->addDay(), function () use ($catalog) {
            $lastmod = $catalog->activatedAt()?->toAtomString();
            $urls = [[route('home'), null], [route('drop.show'), null], [route('advisor'), null]];
            foreach (PageController::SLUGS as $slug) {
                $urls[] = [route('page', $slug), null];
            }
            foreach (Area::cases() as $area) {
                foreach ($catalog->snapshot($area) as $entry) {
                    $urls[] = [route('catalog.show', [$area->value, $entry->id]), $lastmod];
                }
            }

            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($urls as [$loc, $mod]) {
                $out .= '<url><loc>'.e($loc).'</loc>'.($mod ? '<lastmod>'.$mod.'</lastmod>' : '').'</url>'."\n";
            }

            return $out.'</urlset>'."\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
