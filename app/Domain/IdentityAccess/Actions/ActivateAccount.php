<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\IdentityAccess\Models\AccessChange;
use App\Domain\IdentityAccess\Models\AccessChangeKind;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Potwierdzenie e-maila = konto Tank (brak danych dostępu to Tank, więc nic nie zapisujemy). Idempotentne. */
class ActivateAccount
{
    public function handle(User $user, ?User $actor = null, ?string $reason = null): void
    {
        if ($actor) {
            Gate::forUser($actor)->authorize('manage-users');
        }

        DB::transaction(function () use ($user, $actor, $reason) {
            $user = User::lockForUpdate()->findOrFail($user->id);

            if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
                if ($actor) {
                    AccessChange::create([
                        'user_id' => $user->id, 'actor_id' => $actor->id, 'kind' => AccessChangeKind::Activated,
                        'fishroom_ends_at' => $user->fishroom_ends_at, 'reason' => $reason ?? '', 'created_at' => now(),
                    ]);
                }

                event(new Verified($user));
            }
        });
    }
}
