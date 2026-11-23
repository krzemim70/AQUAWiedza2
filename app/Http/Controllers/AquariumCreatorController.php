<?php

namespace App\Http\Controllers;

use App\Domain\Advisor\CreatorBundle;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Domain\Aquarium\Actions\ConvertPlanToTank;
use App\Domain\Aquarium\Actions\ListDraftPlans;
use App\Domain\Aquarium\Actions\SaveCreatorPlan;
use App\Domain\Aquarium\Actions\SplitShoppingList;
use App\Domain\Aquarium\Data\CreatorLine;
use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\Aquarium\Data\PlanData;
use App\Domain\Aquarium\Data\ShoppingLocationGroup;
use App\Domain\Aquarium\Data\ShoppingOfferLine;
use App\Domain\Aquarium\Models\TankType;
use App\Domain\IdentityAccess\Actions\AcceptConsents;
use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Actions\AddLocationOffersToCart;
use App\Domain\Shops\Actions\ListRecordWatches;
use App\Domain\Shops\Actions\WatchRecord;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/** Publiczny kreator akwarium w przeglądarce oraz punkty końcowe Kroku 6 (oferty, zapis planu, koszyk, obserwacje, konwersja). */
class AquariumCreatorController
{
    public function show(
        Request $request,
        CreatorBundle $bundle,
        ResolveAccess $access,
        AcceptConsents $consents,
        ListDraftPlans $draftPlans,
    ): View|RedirectResponse {
        /** @var User|null $user */
        $user = $request->user();
        $action = $request->query('akcja') === 'zapisz' ? 'zapisz' : null;

        if ($user !== null && $action === 'zapisz' && ! $consents->current($user)) {
            return redirect()->guest(route('consents'));
        }

        $built = $bundle->ensureBuilt();
        $hasCatalog = $built['version'] !== 'empty';
        $guestOffersDecision = $access->handle(null, Feature::CreatorOffers);
        $userOffersDecision = $access->handle($user, Feature::CreatorOffers);

        return view('creator.show', [
            'bundleUrl' => $built['url'],
            'bundleVersion' => $built['version'],
            'hasCatalog' => $hasCatalog,
            'config' => [
                'bundleUrl' => $built['url'],
                'bundleVersion' => $built['version'],
                'hasCatalog' => $hasCatalog,
                'csrfToken' => csrf_token(),
                'isAuthenticated' => $user !== null,
                'needsConsents' => $user !== null && ! $consents->current($user),
                'userCity' => $user?->city,
                'cities' => [],
                'citiesUrl' => route('creator.cities'),
                'guestOffersAllowed' => $guestOffersDecision->allowed,
                'canOffers' => $userOffersDecision->allowed,
                'offersDeniedMessage' => $userOffersDecision->message,
                'canPlan' => $access->handle($user, Feature::TankPlan)->allowed,
                'canCart' => $access->handle($user, Feature::ShopCart)->allowed,
                'canWatch' => $access->handle($user, Feature::RecordWatch)->allowed,
                'existingDraftPlans' => $user !== null ? $draftPlans->handle($user) : [],
                'initialAction' => $action,
                'loginUrl' => route('creator.auth'),
                'registerUrl' => route('creator.auth', ['cel' => 'rejestracja']),
                'offersUrl' => route('creator.offers'),
                'savePlanUrl' => route('creator.save-plan'),
                'cartAddUrl' => route('creator.cart'),
                'watchUrl' => route('creator.watch'),
                'convertUrlTemplate' => route('creator.convert', ['plan' => '__PLAN__']),
                'plansUrl' => route('plans.index'),
                'cartUrl' => route('cart.show'),
                'watchesUrl' => route('watches.index'),
                'accountUrl' => route('account'),
                'consentsUrl' => route('creator.auth'),
                'catalogProductUrlTemplate' => route('catalog.show', ['area' => '__AREA__', 'id' => '__ID__']),
            ],
        ]);
    }

    public function cities(PublicOffers $publicOffers): JsonResponse
    {
        /** @var list<string> $cities */
        $cities = Cache::remember('creator.cities', 300, fn (): array => $publicOffers->activeCities());

        return response()->json(['cities' => $cities]);
    }

    public function redirectToAuth(Request $request): RedirectResponse
    {
        $intended = route('creator.show', ['akcja' => 'zapisz']);
        $request->session()->put('url.intended', $intended);

        if ($request->user() !== null) {
            return redirect($intended);
        }

        $target = $request->query('cel') === 'rejestracja'
            ? route('register.create', ['type' => 'akwarysta'])
            : route('login');

        return redirect($target);
    }

    public function offers(Request $request, SplitShoppingList $split, ListRecordWatches $watches): JsonResponse
    {
        $validated = $request->validate([
            'city' => [$request->user() === null ? 'required' : 'nullable', 'string', 'max:100'],
            'items' => ['present', 'array', 'max:500'],
            'items.*.area' => ['required', Rule::enum(Area::class)],
            'items.*.id' => ['required', 'string', 'max:64'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:'.LivestockData::MAX_QUANTITY],
        ]);

        /** @var User|null $user */
        $user = $request->user();
        $lines = $this->toCreatorLines($validated['items'] ?? []);
        $city = isset($validated['city']) ? (string) $validated['city'] : null;

        $result = $split->forLines($user, $lines, $city, guestRequiresCity: $user === null);
        $watchedSet = $user !== null ? array_flip($watches->pendingKeys($user->id)) : [];
        $timezone = (string) config('app.display_timezone');

        return response()->json([
            'city' => $result->city,
            'groups' => array_map(
                fn (ShoppingLocationGroup $group): array => $this->serializeGroup($group, $timezone),
                $result->groups,
            ),
            'missing' => array_map(function (array $item) use ($watchedSet): array {
                $key = $item['area']->value.':'.$item['id'];

                return [
                    'area' => $item['area']->value,
                    'areaLabel' => $item['area']->label(),
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'wanted' => $item['wanted'],
                    'watched' => isset($watchedSet[$key]),
                ];
            }, $result->missing),
        ]);
    }

    public function savePlan(Request $request, SaveCreatorPlan $save, ListDraftPlans $drafts): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(TankType::class)],
            'size_band' => ['nullable', Rule::enum(TankBand::class)],
            'liters' => ['nullable', 'integer', 'min:1'],
            'temp_band' => ['nullable', Rule::enum(TemperatureBand::class)],
            'ph_band' => ['nullable', Rule::enum(PhBand::class)],
            'replace_plan_id' => ['nullable', 'integer', 'min:1'],
            'items' => ['present', 'array', 'max:500'],
            'items.*.area' => ['required', Rule::enum(Area::class)],
            'items.*.id' => ['required', 'string', 'max:64'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:'.LivestockData::MAX_QUANTITY],
        ]);

        /** @var User|null $user */
        $user = $request->user();
        abort_if($user === null, 401);

        $replacePlanId = isset($validated['replace_plan_id']) ? (int) $validated['replace_plan_id'] : null;
        $planData = new PlanData(
            (string) $validated['name'],
            (string) $validated['type'],
            (string) ($validated['size_band'] ?? ''),
            isset($validated['liters']) ? (string) $validated['liters'] : null,
            isset($validated['temp_band']) ? (string) $validated['temp_band'] : null,
            isset($validated['ph_band']) ? (string) $validated['ph_band'] : null,
        );
        $lines = $this->toCreatorLines($validated['items'] ?? []);

        try {
            $plan = $save->handle($user, $planData, $lines, $replacePlanId);
        } catch (AuthorizationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'plan_limit',
                'existingDraftPlans' => $drafts->handle($user),
            ], 403);
        }

        return response()->json([
            'planId' => $plan->id,
            'planName' => $plan->name,
            'planUrl' => route('plans.show', $plan->id),
            'convertUrl' => route('creator.convert', ['plan' => $plan->id]),
            'message' => $replacePlanId !== null
                ? 'Zastąpiono istniejący szkic planu.'
                : 'Plan został zapisany na Twoim koncie.',
        ]);
    }

    public function addToCart(Request $request, AddLocationOffersToCart $addOffers): JsonResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.offer_id' => ['required', 'integer', 'min:1'],
            'lines.*.wanted' => ['required', 'integer', 'min:1', 'max:'.LivestockData::MAX_QUANTITY],
        ]);

        /** @var User|null $user */
        $user = $request->user();
        abort_if($user === null, 401);

        $items = [];
        $rawLines = is_array($validated['lines'] ?? null) ? $validated['lines'] : [];
        foreach ($rawLines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $items[] = [
                'offerId' => (int) ($line['offer_id'] ?? 0),
                'wanted' => (int) ($line['wanted'] ?? 0),
            ];
        }

        $result = $addOffers->handle($user, $items);

        return response()->json([
            'addedLines' => $result->addedLines,
            'addedUnits' => $result->addedUnits,
            'shortfalls' => $result->shortfalls,
            'message' => $result->message(),
            'cartUrl' => route('cart.show'),
        ]);
    }

    public function watch(Request $request, WatchRecord $watch): JsonResponse
    {
        $validated = $request->validate([
            'area' => ['required', Rule::enum(Area::class)],
            'id' => ['required', 'string', 'max:64'],
        ]);

        /** @var User|null $user */
        $user = $request->user();
        abort_if($user === null, 401);

        $area = Area::from((string) $validated['area']);
        $id = (string) $validated['id'];

        $watch->handle($user, $area, $id);

        return response()->json([
            'watched' => true,
            'area' => $area->value,
            'id' => $id,
            'message' => 'Obserwacja została zapisana. Wyślemy powiadomienie, gdy pojawi się oferta.',
            'watchesUrl' => route('watches.index'),
        ]);
    }

    public function convert(Request $request, int $plan, ConvertPlanToTank $convert): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_if($user === null, 401);

        $tank = $convert->handle($user, $plan);

        return response()->json([
            'tankId' => $tank->id,
            'tankUrl' => route('aquarium.tank', $tank->id),
            'message' => 'Utworzono zbiornik z planu.',
        ]);
    }

    /**
     * @return list<CreatorLine>
     */
    private function toCreatorLines(mixed $rawItems): array
    {
        if (! is_array($rawItems)) {
            return [];
        }

        $lines = [];
        foreach ($rawItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $lines[] = new CreatorLine(
                Area::from((string) ($item['area'] ?? '')),
                (string) ($item['id'] ?? ''),
                (int) ($item['qty'] ?? 0),
            );
        }

        return $lines;
    }

    /** @return array<string, mixed> */
    private function serializeGroup(ShoppingLocationGroup $group, string $timezone): array
    {
        $firstOffer = ($group->lines[0] ?? null)?->offer;
        $ratingAvg = $firstOffer?->shopRatingAvg;
        $ratingCount = $firstOffer->shopRatingCount ?? 0;

        return [
            'locationId' => $group->locationId,
            'shopId' => $group->shopId,
            'shop' => $group->shop,
            'location' => $group->location,
            'address' => $group->address,
            'city' => $group->city,
            'coveredCount' => $group->coveredCount,
            'fullyCoveredCount' => $group->fullyCoveredCount,
            'totalGrosze' => $group->totalGrosze,
            'totalFormatted' => $this->formatMoney($group->totalGrosze),
            'shopRatingAvg' => $ratingAvg,
            'shopRatingCount' => $ratingCount,
            'shopRatingLabel' => $ratingAvg === null
                ? 'Za mało opinii'
                : 'Średnia ocena sklepu '.number_format($ratingAvg, 1, ',', ' ').' z 5 (liczba opinii '.$ratingCount.')',
            'shopReviewsUrl' => route('shops.reviews.index', $group->shopId),
            'lines' => array_map(
                fn (ShoppingOfferLine $line): array => $this->serializeLine($line, $timezone),
                $group->lines,
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeLine(ShoppingOfferLine $line, string $timezone): array
    {
        $offer = $line->offer;
        $promoEndsAt = $offer->promoEndsAt;
        $lowestPriorGrosze = $offer->lowestPriorGrosze;

        $promoData = [
            'isPromo' => false,
            'promoEndsAt' => null,
            'lowestPriorGrosze' => null,
            'lowestPriorFormatted' => null,
            'lowestPriorLabel' => null,
        ];

        if ($promoEndsAt !== null && $lowestPriorGrosze !== null) {
            $lowestFormatted = $this->formatMoney($lowestPriorGrosze);
            $promoData = [
                'isPromo' => true,
                'promoEndsAt' => Carbon::instance($promoEndsAt)->subSecond()->timezone($timezone)->format('d.m'),
                'lowestPriorGrosze' => $lowestPriorGrosze,
                'lowestPriorFormatted' => $lowestFormatted,
                'lowestPriorLabel' => $offer->lowestPriorSinceStart
                    ? 'Najniższa cena od rozpoczęcia sprzedaży: '.$lowestFormatted
                    : 'Najniższa cena z '.$offer->lookbackDays.' dni przed obniżką: '.$lowestFormatted,
            ];
        }

        return [
            'area' => $line->area->value,
            'areaLabel' => $line->area->label(),
            'id' => $line->id,
            'name' => $line->name,
            'wanted' => $line->wanted,
            'offerId' => $line->offerId,
            'variant' => $line->variant,
            'freeQty' => $line->freeQty,
            'takeQty' => $line->takeQty,
            'isPartial' => $line->freeQty < $line->wanted,
            'priceGrosze' => $line->priceGrosze,
            'priceFormatted' => $this->formatMoney($line->priceGrosze),
            'regularPriceGrosze' => $offer->regularPriceGrosze,
            'regularPriceFormatted' => $offer->regularPriceGrosze !== null
                ? $this->formatMoney($offer->regularPriceGrosze)
                : null,
            ...$promoData,
            'shopRatingAvg' => $offer->shopRatingAvg,
            'shopRatingCount' => $offer->shopRatingCount,
            'validTo' => Carbon::instance($offer->validTo)->timezone($timezone)->format('d.m.Y'),
        ];
    }

    private function formatMoney(int $grosze): string
    {
        return number_format($grosze / 100, 2, ',', ' ').' zł';
    }
}
