<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\IdentityAccess\Data\ChangeAccessData;
use App\Domain\IdentityAccess\Models\AccessChange;
use App\Domain\IdentityAccess\Models\AccessChangeKind as Kind;
use App\Domain\IdentityAccess\Models\Tier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Ręczne zmiany dostępu przez admina: nadanie/cofnięcie Fishroom, zawieszenie/odwieszenie. Zmiana + historia w jednej transakcji. */
class ChangeAccess
{
    public function handle(ChangeAccessData $data): void
    {
        Gate::forUser($data->actor)->authorize('manage-users');

        if (trim($data->reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Podaj powód zmiany.']);
        }

        DB::transaction(function () use ($data) {
            $user = User::lockForUpdate()->findOrFail($data->user->id);
            $fail = fn (string $field, string $msg) => throw ValidationException::withMessages([$field => $msg]);

            match ($data->kind) {
                Kind::FishroomGranted => $data->fishroomEndsAt?->isFuture()
                    ? $user->forceFill(['fishroom_ends_at' => $data->fishroomEndsAt])
                    : $fail('fishroom_ends_at', 'Podaj datę końca Fishroom w przyszłości.'),
                Kind::FishroomRevoked => $user->tier() === Tier::Fishroom
                    ? $user->forceFill(['fishroom_ends_at' => null])
                    : $fail('kind', 'Konto nie ma aktywnego Fishroom.'),
                Kind::Suspended => match (true) {
                    $user->is($data->actor) => $fail('kind', 'Nie możesz zawiesić własnego konta.'),
                    $user->isSuspended() => $fail('kind', 'Konto jest już zawieszone.'),
                    default => $user->forceFill(['suspended_at' => now()]),
                },
                Kind::Unsuspended => $user->isSuspended()
                    ? $user->forceFill(['suspended_at' => null])
                    : $fail('kind', 'Konto nie jest zawieszone.'),
                Kind::Activated, Kind::FishroomExpired, Kind::Imported => $fail('kind', 'Wygaśnięcie jest automatyczne.'),
            };
            $user->save();

            AccessChange::create([
                'user_id' => $user->id, 'actor_id' => $data->actor->id, 'kind' => $data->kind,
                'fishroom_ends_at' => $user->fishroom_ends_at, 'reason' => $data->reason, 'created_at' => now(),
            ]);
        });
    }
}
