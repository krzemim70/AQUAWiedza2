<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Access;
use App\Domain\Shops\Models\Member;
use App\Domain\Shops\Models\MemberRole;
use App\Domain\Shops\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Właściciel dodaje istniejące konto (po adresie e-mail) jako pracownika sklepu. */
class AddMember
{
    public function __construct(private Access $access) {}

    public function handle(User $actor, Shop $shop, string $email): Member
    {
        $this->access->authorizeOwner($actor, $shop->id);

        $user = User::where('email', trim($email))->first();
        if ($user === null || $user->isSuspended()) {
            throw ValidationException::withMessages(['email' => 'Nie znaleziono aktywnego konta o tym adresie e-mail. Osoba musi najpierw założyć konto.']);
        }

        return DB::transaction(function () use ($actor, $shop, $user) {
            if (Member::where('shop_id', $shop->id)->where('user_id', $user->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'To konto należy już do zespołu sklepu.']);
            }

            $member = Member::create(['shop_id' => $shop->id, 'user_id' => $user->id, 'role' => MemberRole::Staff]);
            activity()->performedOn($shop)->causedBy($actor)->withProperties(['user_id' => $user->id])->log('member added');

            return $member;
        });
    }
}
