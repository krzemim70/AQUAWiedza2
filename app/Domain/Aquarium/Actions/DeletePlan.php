<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeletePlan
{
    public function __construct(private Access $access) {}

    /** Pozycje znikają kaskadą; zbiornik utworzony z planu zostaje. */
    public function handle(User $actor, int $planId): void
    {
        DB::transaction(fn () => $this->access->plan($actor, $planId)->delete());
    }
}
