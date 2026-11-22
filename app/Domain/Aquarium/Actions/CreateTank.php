<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\TankData;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\IdentityAccess\Models\Feature;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTank
{
    public function __construct(private Access $access) {}

    /** Blokada wiersza konta serializuje równoległe tworzenie; limit liczony pod blokadą. */
    public function handle(User $actor, TankData $data): Tank
    {
        $attributes = $data->attributes();

        return DB::transaction(function () use ($actor, $attributes) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $this->access->authorize($actor, Feature::TankAdd, ['tank_count' => Tank::ownedBy($actor)->count()]);

            return Tank::create($attributes + ['user_id' => $actor->id]);
        });
    }
}
