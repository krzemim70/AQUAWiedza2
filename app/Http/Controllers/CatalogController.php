<?php

namespace App\Http\Controllers;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Http\AdvisorReturn;
use App\View\CatalogDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CatalogController
{
    public function __invoke(Request $request, Area $area, string $id, Catalog $catalog, ResolveAccess $access): View
    {
        $entry = $catalog->find($area, $id);
        abort_if($entry === null, 404);

        return view('public.catalog-product', [
            'entry' => $entry,
            'parameters' => CatalogDetails::parameters($entry, $access->handle($request->user(), Feature::TankHitDescription)->allowed),
            'photo' => collect($entry->photos)->first(fn ($photo) => filled($photo['url'])),
            'metaDescription' => Str::limit(CatalogDetails::preview($entry) ?: $entry->name.' — parametry i oferty sklepów akwarystycznych.', 160, '…', preserveWords: true),
            'returnTo' => AdvisorReturn::results($request->query->all()['wroc'] ?? null),
        ]);
    }
}
