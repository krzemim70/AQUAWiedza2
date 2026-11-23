<?php

namespace App\Domain\Shops\Actions;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Contracts\Accounts;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Contracts\PublicOffers;
use App\Domain\Shops\Models\RecordWatch;
use App\Domain\Shops\OfferAvailability;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WatchRecord
{
    public function __construct(
        private Accounts $accounts,
        private ResolveAccess $access,
        private Settings $settings,
        private Catalog $catalog,
        private PublicOffers $offers,
    ) {}

    public function handle(User $actor, Area $area, string $legacyId): void
    {
        DB::transaction(function () use ($actor, $area, $legacyId): void {
            $user = $this->accounts->lock($actor->id) ?? throw new AuthorizationException;
            $decision = $this->access->handle($user, Feature::RecordWatch);
            $decision->allowed || throw new AuthorizationException($decision->message);

            if (! isset($this->catalog->labels($area, [$legacyId])[$legacyId])) {
                throw ValidationException::withMessages(['watch' => 'Brak rekordu w katalogu.']);
            }

            $owned = RecordWatch::where('user_id', $user->id);
            $existing = (clone $owned)->where('area', $area)->where('legacy_id', $legacyId)->lockForUpdate()->first();
            if ($existing !== null && $existing->removed_at === null && $existing->notified_at === null) {
                return;
            }

            $cityKey = OfferAvailability::cityKey($user->city);
            $offers = $this->offers->forRecords($area, [$legacyId])[$legacyId] ?? [];
            if (OfferAvailability::firstIn($offers, $cityKey) !== null) {
                throw ValidationException::withMessages(['watch' => 'Ten rekord ma już dostępną ofertę w Twoim zakresie.']);
            }

            $limit = (int) $this->settings->get(SettingKey::ShopWatch)[$decision->tier->value];
            if ($owned->pending()->count() >= $limit) {
                throw ValidationException::withMessages(['watch' => "Limit aktywnych obserwacji na Twoim koncie wynosi {$limit}."]);
            }

            $watch = $existing ?? new RecordWatch(['user_id' => $user->id, 'area' => $area, 'legacy_id' => $legacyId]);
            $watch->fill([
                'activation' => (string) Str::uuid(),
                'created_at' => now(),
                'notified_at' => null,
                'removed_at' => null,
            ])->save();
        }, 3);
    }
}
