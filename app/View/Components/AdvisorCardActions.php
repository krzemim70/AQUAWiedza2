<?php

namespace App\View\Components;

use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Aquarium\Contracts\OwnTanks;
use App\Domain\Aquarium\Data\EquipmentData;
use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Notifications\Contracts\Preferences;
use App\Domain\Notifications\Topic;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Domain\Shops\Contracts\RecordWatches;
use App\Domain\Shops\Data\PublicOffer;
use App\Domain\Shops\OfferAvailability;
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
        private RecordWatches $recordWatches,
        private Preferences $preferences,
        GuideArea|Area $area,
        public string $id,
        public string $name,
        public string $variant,
        private ?CatalogEntry $entry = null,
        private ?array $offers = null,
        public ?int $preferredTank = null,
        public bool $productPage = false,
        public bool $nearbyOnly = false,
    ) {
        $this->area = Area::from($area->value);
    }

    public function render(): View
    {
        $user = $this->request->user();
        $compact = $this->variant === 'compact';
        $offersOnly = $this->variant === 'offers';
        $offers = $this->access->handle($user, Feature::PublicOffers)->allowed
            ? ($this->offers ?? $this->publicOffers->forRecords($this->area, [$this->id])[$this->id] ?? [])
            : [];
        $cityKey = OfferAvailability::cityKey($user?->city);
        if ($this->nearbyOnly) {
            $offers = $cityKey === null ? [] : array_values(array_filter($offers,
                fn (PublicOffer $offer) => OfferAvailability::cityKey($offer->locationCity) === $cityKey));
        }
        $availableOffer = OfferAvailability::firstIn($offers, null);
        $canWatch = $user !== null && $this->access->handle($user, Feature::RecordWatch)->allowed;
        $canStartWatch = ($canWatch || $user === null)
            && OfferAvailability::firstIn($offers, $cityKey) === null;
        $watchPending = $canWatch && $canStartWatch
            && $this->recordWatches->isPending($user->id, $this->area, $this->id);
        $shopCount = collect($offers)->unique('shopId')->count();
        $returnTo = AdvisorReturn::card($this->request->getRequestUri())
            ?? route('catalog.show', [$this->area->value, $this->id], absolute: false);
        $living = ! $compact && ! $offersOnly && in_array($this->area, [Area::Fauna, Area::Flora], true);
        $equipment = ! $compact && ! $offersOnly && $this->productPage && $this->area === Area::Equipment;
        $canAdd = $user !== null && ($living || $equipment)
            && $this->access->handle($user, Feature::TankView)->allowed
            && ($equipment || $this->access->handle($user, Feature::TankRanking)->allowed);
        $entry = $compact || $offersOnly ? null : ($this->entry ?? $this->catalog->find($this->area, $this->id));
        $fullDescription = $this->access->handle($user, Feature::TankHitDescription)->allowed;

        return view('components.advisor-card-actions', [
            'compact' => $compact,
            'offersOnly' => $offersOnly,
            'hasCity' => $cityKey !== null,
            'guest' => $user === null,
            'offers' => $offers,
            'availableOffer' => $availableOffer,
            'canStartWatch' => $canStartWatch,
            'watchPending' => $watchPending,
            'watchEmailEnabled' => $watchPending && $this->preferences->allowsEmail($user->id, Topic::Availability),
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
            'equipment' => $equipment,
            'maxQuantity' => $equipment ? EquipmentData::MAX_QUANTITY : LivestockData::MAX_QUANTITY,
            'tanks' => collect($canAdd ? $this->ownTanks->names($user) : []),
            'canAdd' => $canAdd,
            'requestKey' => $canAdd && $living ? (string) Str::uuid() : null,
            'description' => $entry === null ? '' : ($fullDescription ? CatalogDetails::description($entry) : CatalogDetails::preview($entry)),
            'fullDescription' => $fullDescription,
        ]);
    }
}
