<?php

namespace App\Domain\Shops\Actions;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Access;
use App\Domain\Shops\Data\OfferData;
use App\Domain\Shops\Models\ImportBatch;
use App\Domain\Shops\Models\Location;
use App\Domain\Shops\Models\OfferState;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;
use App\Domain\Shops\OfferImport\Csv;
use App\Domain\Shops\OfferImport\ImportRow;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class PreviewOfferImport
{
    public const DISPLAY_ROWS = 50;

    public function __construct(private Access $access, private Settings $settings, private Catalog $catalog, private Csv $csv) {}

    public function handle(User $actor, Shop $shop, string $bytes): ImportBatch
    {
        $this->access->authorizeMember($actor, $shop->id);
        $limits = $this->settings->get(SettingKey::ShopCsvImport) + SettingKey::ShopCsvImport->default();

        try {
            $file = $this->csv->read($bytes, (int) $limits['max_bytes'], (int) $limits['max_rows']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        [$ids, $names] = $this->catalogIndex();
        $locations = $shop->locations()->where('active', true)->get();
        $bounds = $this->settings->get(SettingKey::ShopOfferBounds);
        $minPrice = max(1, (int) $this->settings->get(SettingKey::ShopMinPrice));
        $offerLimit = (int) $this->settings->get(SettingKey::ShopActivation)['offer_limit'];
        $live = $shop->offers()->live()->count();
        $now = now()->startOfSecond();
        $timezone = config('app.display_timezone');
        $counts = ['added' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => 0];
        $report = ['encoding' => $file['encoding'], 'separator' => $file['separator'], 'total' => count($file['rows']), 'rows' => [], 'errors' => []];
        $rows = $duplicates = $approved = [];

        foreach ($file['rows'] as $line => $fields) {
            if ($fields === null) {
                $rows[$line] = [[], ['Sprawdź liczbę kolumn i cudzysłowy.'], null, null, false, null];

                continue;
            }

            $errors = [];
            $record = $this->record($fields['id_rekordu'], $ids, $names, $errors);
            $place = $fields['placowka'] ?? '';
            $matching = $place === '' ? $locations : $locations->filter(fn (Location $loc) => $loc->name === $place);
            $location = $matching->count() === 1 ? $matching->first() : null;
            if ($location === null) {
                $errors[] = $matching->count() > 1
                    ? 'Podaj jednoznaczną nazwę czynnej placówki.'
                    : 'Nie znaleziono czynnej placówki o tej nazwie w tym sklepie.';
            }

            $variant = Str::squish($fields['wariant'] ?? '');
            $key = ImportRow::variantKey($variant);
            $variantValid = mb_strlen($variant) <= 120 && mb_strlen($key) <= 120;
            if (! $variantValid) {
                $errors[] = 'Wariant może mieć najwyżej 120 znaków.';
            }

            $price = ImportRow::price($fields['cena']);
            if ($price === null || $price < $minPrice || $price > $bounds['max_price_grosze']) {
                $errors[] = sprintf('Podaj cenę z dokładnością do grosza w zakresie %.2f–%.2f zł.', $minPrice / 100, $bounds['max_price_grosze'] / 100);
            }
            $quantity = ltrim($fields['ilosc'], '0');
            $qty = ctype_digit($fields['ilosc']) && strlen($quantity) <= 7 ? (int) $quantity : null;
            if ($qty === null || $qty > $bounds['max_qty']) {
                $errors[] = "Podaj całkowitą dostępną ilość w zakresie 0–{$bounds['max_qty']}.";
            }

            $offer = $identity = null;
            if ($record !== null && $location !== null && $variantValid) {
                $identity = serialize([$location->id, $record->area->value, $record->id, $key]);
                $duplicates[$identity] = ($duplicates[$identity] ?? 0) + 1;
                $offer = $shop->offers()->where([
                    'location_id' => $location->id, 'area' => $record->area->value, 'legacy_id' => $record->id, 'variant_key' => $key,
                ])->first();
                if ($offer !== null && $offer->variant_key !== $key) {
                    $errors[] = 'Wariant koliduje z istniejącym wariantem tej placówki.';
                }
                if ($qty !== null && $offer !== null && $qty !== $offer->free_qty && $qty + $offer->reserved_qty > $bounds['max_qty']) {
                    $errors[] = "Dostępna i zarezerwowana ilość razem przekraczają limit {$bounds['max_qty']}.";
                }
            }

            $fromField = $fields['wazna_od'] ?? '';
            $toField = $fields['wazna_do'] ?? '';
            $from = $fromField === '' ? ($offer->valid_from ?? $now) : ImportRow::date($fromField, $timezone);
            $to = $toField === '' ? ($offer->valid_to ?? $this->defaultEnd($from ?? $now, $timezone, (int) $limits['validity_days']))
                : ImportRow::date($toField, $timezone, endOfDay: true);
            if ($from === null || $to === null || (int) $to->format('Y') > 9999) {
                $errors[] = 'Podaj poprawne daty w formacie RRRR-MM-DD.';
            } elseif ($to <= $from) {
                $errors[] = 'Data końca ważności musi przypadać po rozpoczęciu.';
            }

            $publication = Str::lower($fields['publikuj'] ?? '');
            $publish = $publication !== 'nie';
            if (! in_array($publication, ['', 'tak', 'nie'], true)) {
                $errors[] = 'W kolumnie publikuj wpisz tak albo nie.';
            }
            $data = $errors === [] && $location !== null && $record !== null && $price !== null && $qty !== null && $from !== null && $to !== null
                ? new OfferData($location->id, $record->area, $record->id, $variant, $price, $from, $to, $qty) : null;

            $rows[$line] = [[
                'record' => $record === null ? Str::limit($fields['id_rekordu'], 160) : "{$record->name} ({$record->id})",
                'location' => $location->name ?? Str::limit($place, 160),
                'variant' => Str::limit($variant, 120),
                'price' => $price,
                'qty' => $qty,
                'from' => $from === null ? null : $this->displayDate($from, $timezone),
                'to' => $to === null ? null : $this->displayDate($to, $timezone),
                'previous_price' => $offer?->price_grosze,
                'previous_qty' => $offer?->free_qty,
                'publish' => $publish,
            ], $errors, $data, $offer, $publish, $identity];
        }

        foreach ($rows as $line => [$display, $errors, $data, $offer, $publish, $identity]) {
            if ($identity !== null && $duplicates[$identity] > 1) {
                $errors[] = 'Ta sama oferta występuje w pliku więcej niż raz.';
            }
            if ($errors === [] && $data !== null) {
                $wasLive = $offer !== null && $offer->state === OfferState::Published && $offer->valid_to > $now;
                $willPublish = $publish && $offer?->state !== OfferState::Published;
                $willBeLive = ($willPublish || $offer?->state === OfferState::Published) && $data->validTo > $now;
                if ($willPublish && ! in_array($shop->state, [ShopState::Preparation, ShopState::Active], true)) {
                    $errors[] = 'Sklep nie może teraz publikować ofert.';
                } elseif ($publish && $data->validTo <= $now) {
                    $errors[] = 'Termin ważności oferty już minął.';
                } elseif ($willBeLive && ! $wasLive && $live >= $offerLimit) {
                    $errors[] = "Osiągnięto limit $offerLimit opublikowanych ofert.";
                } else {
                    $live += (int) $willBeLive - (int) $wasLive;
                    $approved[$line] = [
                        'location_id' => $data->locationId, 'area' => $data->area->value, 'record_id' => $data->legacyId,
                        'variant' => $data->variant, 'price' => $data->priceGrosze, 'qty' => $data->freeQty,
                        'from' => $data->validFrom->format('Y-m-d H:i:s'), 'to' => $data->validTo->format('Y-m-d H:i:s'),
                        'publish' => $publish, 'offer_id' => $offer?->id, 'version' => $offer?->version,
                    ];
                }
            }

            $status = $errors === [] && $data !== null ? ImportRow::status($offer, $data, $publish) : 'errors';
            $counts[$status]++;
            if ($errors !== []) {
                $report['errors'][$line] = $errors;
            }
            if (count($report['rows']) < self::DISPLAY_ROWS) {
                $report['rows'][$line] = $display + ['status' => $status];
            }
        }

        $path = 'shop-imports/'.Str::uuid().'.csv';
        $disk = Storage::disk('local');
        if (! $disk->put($path, $bytes)) {
            throw new RuntimeException('Nie udało się zapisać pliku importu.');
        }

        try {
            return ImportBatch::create([
                'shop_id' => $shop->id, 'user_id' => $actor->id, 'file_sha256' => hash('sha256', $bytes), 'file_path' => $path,
                'state' => 'preview', 'counts' => $counts, 'report' => $report, 'approved' => $approved,
                'expires_at' => $now->copy()->addHours((int) $limits['retention_hours']),
            ]);
        } catch (Throwable $e) {
            $disk->delete($path);

            throw $e;
        }
    }

    /** @return array{array<string, CatalogEntry>, array<string, array<string, CatalogEntry>>} */
    private function catalogIndex(): array
    {
        $ids = $names = [];
        $batch = $this->catalog->activeBatchId();
        if ($batch === null) {
            return [$ids, $names];
        }
        foreach (Area::cases() as $area) {
            foreach ($this->catalog->snapshot($area, $batch) as $entry) {
                $id = Str::lower($entry->id);
                $ids[$id] = $entry;
                foreach ([$entry->name, $entry->nameLatin] as $name) {
                    if ($name !== null && $name !== '') {
                        $names[ImportRow::variantKey($name)][$area->value.':'.$id] = $entry;
                    }
                }
            }
        }

        return [$ids, $names];
    }

    /**
     * @param  array<string, CatalogEntry>  $ids
     * @param  array<string, array<string, CatalogEntry>>  $names
     * @param  list<string>  $errors
     */
    private function record(string $value, array $ids, array $names, array &$errors): ?CatalogEntry
    {
        $key = ImportRow::variantKey($value);
        if (isset($ids[$key])) {
            return $ids[$key];
        }
        $matches = $names[$key] ?? [];
        if (count($matches) === 1) {
            return reset($matches);
        }
        $errors[] = $matches === [] ? 'Brak takiego ID lub dokładnej nazwy w aktywnym katalogu.'
            : 'Nazwa pasuje do kilku rekordów. Użyj ID rekordu.';

        return null;
    }

    private function defaultEnd(\DateTimeInterface $from, string $timezone, int $days): Carbon
    {
        return Carbon::instance($from)->setTimezone($timezone)->addDays($days)->setTimezone('UTC');
    }

    private function displayDate(\DateTimeInterface $date, string $timezone): string
    {
        return Carbon::instance($date)->setTimezone($timezone)->format('d.m.Y H:i');
    }
}
