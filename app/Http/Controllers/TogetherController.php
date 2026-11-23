<?php

namespace App\Http\Controllers;

use App\Domain\Advisor\Actions\CompareSpeciesTogether;
use App\Domain\Advisor\Data\CompareSpeciesData;
use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Publiczna strona „Czy te ryby mogą być razem?” (GET, działa bez JS, bez zapisu w bazie). */
class TogetherController
{
    public function __invoke(
        Request $request,
        CompareSpeciesTogether $compare,
        PublicOffers $publicOffers,
        ResolveAccess $access,
    ): View {
        $ids = $this->parseIds($request->query('gatunki'));
        $rawSearch = $request->query('szukaj');
        $search = is_string($rawSearch) ? trim($rawSearch) : '';

        $result = $compare->handle(new CompareSpeciesData($ids, $search));
        /** @var User|null $user */
        $user = $request->user();
        $canOffers = $access->handle($user, Feature::PublicOffers)->allowed;
        $speciesIds = array_map(fn (CatalogEntry $entry): string => $entry->id, $result->species);
        $offersById = $result->report !== null && $canOffers && $speciesIds !== []
            ? $publicOffers->forRecords(Area::Fauna, $speciesIds)
            : [];

        $shareIds = $result->species !== []
            ? $speciesIds
            : $result->selectedIds;
        $shareUrl = $shareIds !== []
            ? route('advisor.together', ['gatunki' => implode(',', $shareIds)])
            : route('advisor.together');

        return view('advisor.together', [
            'result' => $result,
            'search' => $search,
            'offersById' => $offersById,
            'canOffers' => $canOffers,
            'nearbyOnly' => filled($user?->city),
            'userCity' => $user?->city,
            'shareUrl' => $shareUrl,
            'hasQuery' => $request->query() !== [],
        ]);
    }

    /** @return list<string> */
    private function parseIds(mixed $raw): array
    {
        if (is_string($raw)) {
            return array_values(array_filter(array_map('trim', explode(',', $raw)), fn (string $v) => $v !== ''));
        }
        if (is_array($raw)) {
            $ids = [];
            foreach ($raw as $item) {
                if (is_string($item)) {
                    foreach (explode(',', $item) as $part) {
                        $trimmed = trim($part);
                        if ($trimmed !== '') {
                            $ids[] = $trimmed;
                        }
                    }
                }
            }

            return $ids;
        }

        return [];
    }
}
