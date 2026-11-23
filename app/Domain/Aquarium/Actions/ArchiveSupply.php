<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Models\TankSupply;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Archiwizuje pozycję zapasu, zwalniając miejsce w limicie zbiornika bez usuwania historii zdarzeń. */
class ArchiveSupply
{
    public function __construct(private Access $access) {}

    public function handle(User $actor, int $supplyId): TankSupply
    {
        return DB::transaction(function () use ($actor, $supplyId): TankSupply {
            $supply = $this->access->supply($actor, $supplyId, forUpdate: true);
            if ($supply->archived_at === null) {
                $supply->archived_at = now()->utc();
                $supply->save();
            }

            return $supply;
        }, attempts: 3);
    }
}
