<?php

namespace App\Domain\IdentityAccess\Contracts;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class Accounts
{
    /**
     * @param  list<int>  $ids
     * @return Collection<int, User>
     */
    public function forIds(array $ids): Collection
    {
        return User::whereKey($ids)->get()->keyBy('id');
    }

    /** Wywołać w transakcji. Serializuje zapisy zależne od konta. */
    public function lock(int $id): ?User
    {
        return User::lockForUpdate()->find($id);
    }
}
