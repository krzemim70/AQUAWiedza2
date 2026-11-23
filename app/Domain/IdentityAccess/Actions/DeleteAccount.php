<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Notifications\Contracts\AccountData as NotificationData;
use App\Domain\Shops\Contracts\AccountData as ShopData;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class DeleteAccount
{
    public function __construct(
        private ShopData $shops,
        private ResolveAccess $access,
        private NotificationData $notifications,
    ) {}

    public function handle(User $user, #[\SensitiveParameter] string|CarbonInterface $passwordOrCutoff): bool
    {
        return DB::transaction(function () use ($user, $passwordOrCutoff) {
            $account = User::lockForUpdate()->find($user->id);
            if ($account === null) {
                return false;
            }

            $requested = is_string($passwordOrCutoff);
            if ($requested) {
                $decision = $this->access->handle($account, Feature::DeleteAccount);
                $decision->allowed || throw new AuthorizationException($decision->message);

                if (! Hash::check($passwordOrCutoff, $account->password)) {
                    throw ValidationException::withMessages(['current_password' => 'Podane hasło jest nieprawidłowe.']);
                }
            } elseif ($account->is_admin || $account->hasVerifiedEmail() || ! $account->created_at?->lt($passwordOrCutoff)) {
                return false;
            }

            $this->shops->eraseFor($account->id);
            $this->notifications->eraseFor($account->id);
            Password::broker()->deleteToken($account);
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table'))->where('user_id', $account->id)->delete();
            }

            // Klucze obce usuwają m.in. zgody, zbiorniki z zależnościami, koszyk, obserwacje i preferencje.
            $account->delete();
            activity('privacy')->causedByAnonymous()->event('account_deleted')->log(
                $requested ? 'Usunięto konto na żądanie.' : 'Usunięto niepotwierdzone konto po terminie.',
            );

            return true;
        }, 3);
    }
}
