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
use App\Domain\Shops\Notifications\RecordAvailableNotice;
use App\Domain\Shops\OfferAvailability;
use Illuminate\Support\Facades\DB;

class NotifyRecordWatchers
{
    public function __construct(
        private Accounts $accounts,
        private ResolveAccess $access,
        private Catalog $catalog,
        private PublicOffers $offers,
        private Settings $settings,
    ) {}

    public function handle(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => $this->sendPending());
        } else {
            $this->sendPending();
        }
    }

    private function sendPending(): void
    {
        $retention = (int) $this->settings->get(SettingKey::ShopWatch)['retention_days'];
        RecordWatch::whereNull('removed_at')->where('notified_at', '<=', now()->subDays($retention))
            ->update(['removed_at' => now()]);

        foreach (Area::cases() as $area) {
            foreach (RecordWatch::pending()->where('area', $area)->lazyById(500)->chunk(500) as $batch) {
                $ids = $batch->pluck('legacy_id')->unique()->values()->all();
                $offers = $this->offers->forRecords($area, $ids);
                if ($offers === []) {
                    continue;
                }

                $labels = $this->catalog->labels($area, array_keys($offers));
                $users = $this->accounts->forIds($batch->pluck('user_id')->unique()->values()->all());
                foreach ($batch as $watch) {
                    $user = $users->get($watch->user_id);
                    $label = $labels[$watch->legacy_id] ?? null;
                    $offer = OfferAvailability::firstIn($offers[$watch->legacy_id] ?? [], OfferAvailability::cityKey($user?->city));
                    if ($offer === null || $label === null || $user === null
                        || ! $this->access->handle($user, Feature::RecordWatch)->allowed) {
                        continue;
                    }

                    $notice = new RecordAvailableNotice($watch->id, $watch->activation, $label, $offer);
                    if (! $notice->queueFor($user)) {
                        continue;
                    }

                    // Kolejka przyjęła wiadomość. Awaria przed zapisem może powtórzyć przekazanie.
                    RecordWatch::pending()->whereKey($watch->id)->where('activation', $watch->activation)
                        ->update(['notified_at' => now()]);
                }
            }
        }
    }
}
