<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;

/** Potwierdzenie e-maila = konto Tank (brak danych dostępu to Tank, więc nic nie zapisujemy). Idempotentne. */
class ActivateAccount
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = User::lockForUpdate()->findOrFail($user->id);

            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }
        });
    }
}
