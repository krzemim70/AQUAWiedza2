<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Models\RecordWatch;

class UnwatchRecord
{
    public function handle(int $watchId, int $ownerId): bool
    {
        return RecordWatch::whereKey($watchId)->where('user_id', $ownerId)->whereNull('removed_at')
            ->update(['removed_at' => now()]) > 0;
    }

    public function viaSignedLink(int $watchId, string $activation): bool
    {
        return RecordWatch::whereKey($watchId)->where('activation', $activation)->whereNull('removed_at')
            ->update(['removed_at' => now()]) > 0;
    }
}
