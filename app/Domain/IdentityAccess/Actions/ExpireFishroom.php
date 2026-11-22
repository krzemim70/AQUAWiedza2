<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\IdentityAccess\Models\AccessChange;
use App\Domain\IdentityAccess\Models\AccessChangeKind;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Codziennie: zakończony Fishroom → Tank, z wpisem w historii. Idempotentne (po zmianie data jest pusta). Odczyt dostępu i tak stosuje datę. */
class ExpireFishroom
{
    public function handle(): int
    {
        $count = 0;

        User::where('fishroom_ends_at', '<=', now())->pluck('id')->each(function (int $id) use (&$count) {
            DB::transaction(function () use ($id, &$count) {
                $user = User::lockForUpdate()->find($id);
                if ($user?->fishroom_ends_at?->lte(now())) {
                    AccessChange::create([
                        'user_id' => $id, 'kind' => AccessChangeKind::FishroomExpired, 'fishroom_ends_at' => $user->fishroom_ends_at,
                        'reason' => 'Upłynął termin Fishroom', 'created_at' => now(),
                    ]);
                    $user->forceFill(['fishroom_ends_at' => null])->save();
                    $count++;
                }
            });
        });

        return $count;
    }
}
