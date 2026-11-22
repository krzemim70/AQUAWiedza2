<?php

namespace App\View;

use App\Domain\Advisor\Engine\Ranking;
use App\Domain\Advisor\Engine\ScoredRecord;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Contracts\PublicOffers;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TankResultCards
{
    public function __construct(private Catalog $catalog, private PublicOffers $offers, private Request $request) {}

    public function compose(View $view): void
    {
        $data = $view->getData();
        $ranking = $data['ranking'] ?? null;
        if (! $ranking instanceof Ranking || $ranking->matches === []) {
            return;
        }

        $winner = $ranking->topCount === 1 ? $ranking->top()?->record->id : null;
        $alternatives = $winner === null ? $ranking->matches : array_slice($ranking->matches, 1);
        $ids = array_map(fn (ScoredRecord $match) => $match->record->id, $alternatives);
        $offersById = $this->offers->forRecords(Area::Fauna, $ids);
        if ($winner !== null) {
            $offersById[$winner] = $data['offers'];
        }

        $view->with([
            'offersById' => $offersById,
            'thumbnails' => $this->catalog->thumbnails(Area::Fauna, $ids),
            'catalogBackTo' => $this->request->getRequestUri(),
        ]);
    }
}
