<?php

namespace App\Domain\Shops\Actions;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Access;
use App\Domain\Shops\Data\OfferData;
use App\Domain\Shops\Models\ImportBatch;
use App\Domain\Shops\Models\OfferState;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Notifications\OfferImportNotice;
use App\Domain\Shops\OfferImport\ImportRow;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ApplyOfferImport
{
    public function __construct(
        private Access $access,
        private Catalog $catalog,
        private Settings $settings,
        private SaveOffer $save,
        private SetAvailableQuantity $quantity,
        private PublishOffer $publish,
    ) {}

    /** Zwraca true, gdy pozostały wiersze dla kolejnego zadania. */
    public function handle(int $id, int $chunkSize): bool
    {
        $batch = ImportBatch::where('state', 'queued')->find($id);
        if ($batch === null) {
            return false;
        }
        $actor = $batch->actor;
        if ($actor === null || ! $this->access->isMember($actor, $batch->shop_id)) {
            $this->fail($id, 'Brak dostępu do tego sklepu.');

            return false;
        }
        if (! $batch->expires_at->isFuture()) {
            $this->fail($id, 'Upłynął czas na zapis tego importu.');

            return false;
        }
        foreach ([SettingKey::ShopOfferBounds, SettingKey::ShopMinPrice, SettingKey::ShopActivation] as $key) {
            $this->settings->forget($key);
        }

        $remaining = array_filter($batch->approved ?? [], fn (int $line) => $line > $batch->last_line, ARRAY_FILTER_USE_KEY);
        // Obiekt JSON z numerami wierszy może wrócić z bazy w innej kolejności.
        ksort($remaining, SORT_NUMERIC);
        try {
            foreach (array_slice($remaining, 0, $chunkSize, preserve_keys: true) as $line => $row) {
                DB::transaction(function () use ($batch, $actor, $line, $row) {
                    $current = ImportBatch::where('state', 'queued')
                        ->select(['id', 'last_line', 'counts', 'expires_at'])->lockForUpdate()->find($batch->id);
                    if ($current === null || $current->last_line >= $line) {
                        return;
                    }
                    if (! $current->expires_at->isFuture()) {
                        return;
                    }
                    $shop = Shop::lockForUpdate()->findOrFail($batch->shop_id);
                    $this->access->authorizeMember($actor, $shop->id);
                    $counts = $current->counts;
                    try {
                        // Osobny punkt zapisu cofa warunki, ilość i historię, jeśli publikacja wiersza się nie powiedzie.
                        $status = DB::transaction(fn () => $this->writeRow($actor, $shop, $row));
                    } catch (ValidationException $e) {
                        $status = 'errors';
                        $report = ImportBatch::findOrFail($batch->id, ['id', 'report'])->report;
                        $report['errors'][$line] = array_merge(...array_values($e->errors()));
                        $current->report = $report;
                    }
                    $counts[$status]++;
                    $current->update(['last_line' => $line, 'counts' => $counts]);
                }, attempts: 3);
            }
        } catch (AuthorizationException) {
            $this->fail($id, 'Brak dostępu do tego sklepu.');

            return false;
        }

        if (count($remaining) > $chunkSize) {
            return true;
        }

        $this->finish($id);

        return false;
    }

    public function fail(int $id, string $reason): void
    {
        $this->finish($id, $reason);
    }

    /**
     * @param  array{location_id: int, area: string, record_id: string, variant: string, price: int, qty: int, from: string, to: string, publish: bool, offer_id: int|null, version: int|null}  $row
     */
    private function writeRow(User $actor, Shop $shop, array $row): string
    {
        $key = ImportRow::variantKey($row['variant']);
        $offer = $shop->offers()->where([
            'location_id' => $row['location_id'], 'area' => $row['area'], 'legacy_id' => $row['record_id'], 'variant_key' => $key,
        ])->lockForUpdate()->first();

        if ($offer?->id !== $row['offer_id'] || $offer?->version !== $row['version'] || ($offer !== null && $offer->variant_key !== $key)) {
            throw ValidationException::withMessages(['offer' => ImportRow::STALE]);
        }
        if ($shop->locations()->whereKey($row['location_id'])->where('active', true)->lockForUpdate()->first(['id']) === null) {
            throw ValidationException::withMessages(['location' => 'Placówka tej oferty jest nieaktywna.']);
        }

        $area = Area::from($row['area']);
        if ($this->catalog->labels($area, [$row['record_id']]) === []) {
            throw ValidationException::withMessages(['record' => 'Pozycja nie jest już dostępna w katalogu.']);
        }

        $data = new OfferData(
            $row['location_id'], $area, $row['record_id'], $row['variant'], $row['price'],
            Carbon::parse($row['from'], 'UTC'), Carbon::parse($row['to'], 'UTC'), $row['qty'],
        );
        $status = ImportRow::status($offer, $data, $row['publish']);
        $now = now();
        if ($row['publish'] && $data->validTo <= $now) {
            throw ValidationException::withMessages(['offer' => 'Termin ważności oferty już minął.']);
        }
        if ($status === 'unchanged') {
            return $status;
        }
        if ($offer?->state === OfferState::Published && $offer->valid_to <= $now && $data->validTo > $now) {
            $limit = (int) $this->settings->get(SettingKey::ShopActivation)['offer_limit'];
            if ($shop->offers()->live()->count() >= $limit) {
                throw ValidationException::withMessages(['offer' => "Osiągnięto limit $limit opublikowanych ofert."]);
            }
        }

        if ($offer === null || ImportRow::changesTerms($offer, $data)) {
            $offer = $this->save->handle($actor, $shop, $data, $offer);
        }
        if ($offer->free_qty !== $data->freeQty) {
            $offer = $this->quantity->handle($actor, $offer, $offer->version, $data->freeQty, true, 'Import CSV');
        }
        if ($row['publish'] && $offer->state !== OfferState::Published) {
            $this->publish->handle($actor, $offer);
        }

        return $status;
    }

    private function finish(int $id, ?string $reason = null): void
    {
        $batch = DB::transaction(function () use ($id, $reason) {
            $batch = ImportBatch::where('state', 'queued')->lockForUpdate()->find($id);
            if ($batch === null) {
                return null;
            }

            $report = $batch->report;
            $reason ??= array_sum($batch->counts) < $report['total'] ? 'Upłynął czas na zapis tego importu.' : null;
            if ($reason !== null) {
                $report['failure'] = $reason;
            }
            $batch->update(['state' => $reason === null ? 'applied' : 'failed', 'approved' => null, 'report' => $report]);

            return $batch;
        }, attempts: 3);

        if ($batch === null) {
            return;
        }
        $batch->deleteFile();
        $actor = $batch->actor;
        if (($batch->counts['errors'] > 0 || $batch->state === 'failed') && $actor !== null && $this->access->isMember($actor, $batch->shop_id)) {
            try {
                $actor->notify(new OfferImportNotice($batch));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
