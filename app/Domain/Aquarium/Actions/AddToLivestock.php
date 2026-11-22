<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddToLivestock
{
    public function __construct(private Access $access, private Catalog $catalog) {}

    public function handle(User $actor, int $tankId, LivestockData $data, ?string $requestKey = null): void
    {
        DB::transaction(function () use ($actor, $tankId, $data, $requestKey) {
            $tank = $this->access->tank($actor, $tankId, forUpdate: true);

            if ($requestKey !== null && DB::table('tank_livestock_additions')->insertOrIgnore([
                'user_id' => $actor->id,
                'request_key' => strtolower($requestKey),
            ]) === 0) {
                return;
            }

            if (! in_array($data->area, [Area::Fauna, Area::Flora], true)
                || $this->catalog->find($data->area, $data->catalogId) === null) {
                throw ValidationException::withMessages(['species' => 'Wybierz gatunek z katalogu.']);
            }

            $key = ['tank_id' => $tank->id, 'area' => $data->area->value, 'catalog_id' => $data->catalogId];
            $stock = Livestock::where($key);
            $current = $stock->lockForUpdate()->first();

            if ($data->quantity < 1 || $data->quantity > LivestockData::MAX_QUANTITY - ($current->quantity ?? 0)) {
                throw ValidationException::withMessages(['quantity' => 'Łączna ilość w zbiorniku musi mieścić się w przedziale 1–'.LivestockData::MAX_QUANTITY.'.']);
            }

            if ($current === null) {
                Livestock::create($key + ['quantity' => $data->quantity]);
            } else {
                $current->increment('quantity', $data->quantity);
            }
        }, attempts: 3);
    }
}
