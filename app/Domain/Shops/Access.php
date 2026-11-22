<?php

namespace App\Domain\Shops;

use App\Domain\Shops\Models\Member;
use App\Domain\Shops\Models\MemberRole;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/** Bramka modułu: członkostwo w sklepie (każda akcja sprawdza je na serwerze, niezależnie od widoczności w panelu). */
class Access
{
    public function isMember(User $actor, int $shopId): bool
    {
        return ! $actor->isSuspended() && Member::where('shop_id', $shopId)->where('user_id', $actor->id)->exists();
    }

    public function isOwner(User $actor, int $shopId): bool
    {
        return ! $actor->isSuspended()
            && Member::where('shop_id', $shopId)->where('user_id', $actor->id)->where('role', MemberRole::Owner->value)->exists();
    }

    public function authorizeMember(User $actor, int $shopId): void
    {
        $this->isMember($actor, $shopId) || throw new AuthorizationException('Brak dostępu do tego sklepu.');
    }

    public function authorizeOwner(User $actor, int $shopId): void
    {
        $this->isOwner($actor, $shopId) || throw new AuthorizationException('Ta czynność wymaga właściciela sklepu.');
    }
}
