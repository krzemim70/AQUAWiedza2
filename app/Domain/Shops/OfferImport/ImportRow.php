<?php

namespace App\Domain\Shops\OfferImport;

use App\Domain\Shops\Data\OfferData;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Str;

final class ImportRow
{
    public const STALE = 'oferta zmieniła się od podglądu';

    public static function variantKey(string $variant): string
    {
        return Str::lower(Str::squish($variant));
    }

    public static function changesTerms(Offer $offer, OfferData $data): bool
    {
        return $offer->variant !== $data->variant || $offer->price_grosze !== $data->priceGrosze
            || ! $offer->valid_from->equalTo($data->validFrom) || ! $offer->valid_to->equalTo($data->validTo);
    }

    public static function status(?Offer $offer, OfferData $data, bool $publish): string
    {
        if ($offer === null) {
            return 'added';
        }

        return self::changesTerms($offer, $data) || $data->freeQty !== $offer->free_qty
            || ($publish && $offer->state !== OfferState::Published) ? 'updated' : 'unchanged';
    }

    public static function price(string $value): ?int
    {
        if (! preg_match('/\A([0-9]{1,8})(?:[,.]([0-9]{1,2}))?\z/', $value, $parts)) {
            return null;
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public static function date(string $value, string $timezone, bool $endOfDay = false): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone($timezone));
        if ($date === false || $date->format('Y-m-d') !== $value || $value < '1000-01-02' || $value > '9999-12-30') {
            return null;
        }

        return ($endOfDay ? $date->setTime(23, 59, 59) : $date)->setTimezone(new DateTimeZone('UTC'));
    }
}
