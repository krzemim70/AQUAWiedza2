<?php

namespace App\View\Components;

use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Aquarium\Contracts\OwnTanks;
use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Domain\Shops\Data\PublicOffer;
use App\Http\AdvisorReturn;
use App\View\CatalogDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

class AdvisorCardActions extends Component
{
    public Area $area;

    /** @param list<PublicOffer>|null $offers */
    public function __construct(
        private Request $request,
        private Catalog $catalog,
        private PublicOffers $publicOffers,
        private OwnTanks $ownTanks,
        private ResolveAccess $access,
        GuideArea|Area $area,
        public string $id,
        public string $name,
        public string $variant,
        private ?CatalogEntry $entry = null,
        private ?array $offers = null,
        public ?int $preferredTank = null,
        public bool $productPage = false,
    ) {
        $this->area = Area::from($area->value);
    }

    public function render(): View
    {
        $user = $this->request->user();
        $compact = $this->variant === 'compact';
        $offers = $this->access->handle($user, Feature::PublicOffers)->allowed
            ? ($this->offers ?? $this->publicOffers->forRecords($this->area, [$this->id])[$this->id] ?? [])
            : [];
        $availableOffer = collect($offers)->first(fn (PublicOffer $offer) => $offer->freeQty > 0);
        $shopCount = collect($offers)->unique('shopId')->count();
        $returnTo = AdvisorReturn::card($this->request->getRequestUri())
            ?? route('catalog.show', [$this->area->value, $this->id], absolute: false);
        $living = ! $compact && in_array($this->area, [Area::Fauna, Area::Flora], true);
        $canAdd = $user !== null && $living
            && $this->access->handle($user, Feature::TankView)->allowed
            && $this->access->handle($user, Feature::TankRanking)->allowed;
        $entry = $compact ? null : ($this->entry ?? $this->catalog->find($this->area, $this->id));
        $fullDescription = $this->access->handle($user, Feature::TankHitDescription)->allowed;

        return view('components.advisor-card-actions', [
            'compact' => $compact,
            'guest' => $user === null,
            'offers' => $offers,
            'availableOffer' => $availableOffer,
            'shopCount' => $shopCount,
            'shopLabel' => match (true) {
                $shopCount === 1 => 'sklep',
                in_array($shopCount % 10, [2, 3, 4], true) && ! in_array($shopCount % 100, [12, 13, 14], true) => 'sklepy',
                default => 'sklepów',
            },
            'canCart' => $this->access->handle($user, Feature::ShopCart)->allowed,
            'returnTo' => $returnTo,
            'loginUrl' => route('login', ['wroc' => $returnTo]),
            'living' => $living,
            'tanks' => collect($canAdd ? $this->ownTanks->names($user) : []),
            'canAdd' => $canAdd,
            'requestKey' => $canAdd ? (string) Str::uuid() : null,
            'description' => $entry === null ? '' : ($fullDescription ? CatalogDetails::description($entry) : CatalogDetails::preview($entry)),
            'fullDescription' => $fullDescription,
        ]);
    }
}
