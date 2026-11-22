<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Access;
use App\Domain\Shops\Data\LocationData;
use App\Domain\Shops\Models\Location;
use App\Domain\Shops\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Placówki odbioru prowadzi właściciel. Po zmianie sklep w przygotowaniu jest oceniany ponownie. */
class SaveLocation
{
    public function __construct(private Access $access, private EvaluateShop $evaluate) {}

    public function handle(User $actor, Shop $shop, LocationData $data, ?Location $location = null): Location
    {
        $this->access->authorizeOwner($actor, $shop->id);

        Validator::make([
            'name' => $data->name, 'address' => $data->address, 'city' => $data->city, 'phone' => $data->phone,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
        ])->validate();

        return DB::transaction(function () use ($shop, $data, $location) {
            $location = $location === null ? new Location(['shop_id' => $shop->id]) : Location::where('shop_id', $shop->id)->findOrFail($location->id);
            $location->forceFill(['shop_id' => $shop->id])->fill([
                'name' => $data->name, 'address' => $data->address, 'city' => $data->city,
                'phone' => $data->phone, 'opening_hours' => $data->openingHours, 'active' => $data->active,
            ])->save();
            $this->evaluate->handle($shop);

            return $location;
        });
    }
}
