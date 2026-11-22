<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteTank
{
    public function __construct(private Access $access) {}

    /** Obsada, pomiary, dziennik i przypomnienia (z wystąpieniami) znikają kaskadą kluczy obcych w tej samej transakcji. */
    public function handle(User $actor, int $tankId): void
    {
        DB::transaction(fn () => $this->access->tank($actor, $tankId)->delete());
    }
}
