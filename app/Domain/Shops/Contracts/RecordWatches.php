<?php

namespace App\Domain\Shops\Contracts;

use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Models\RecordWatch;

class RecordWatches
{
    public function isPending(int $userId, Area $area, string $legacyId): bool
    {
        return RecordWatch::pending()->where('user_id', $userId)->where('area', $area)->where('legacy_id', $legacyId)->exists();
    }

    public function isCurrent(int $id, int $userId, string $activation): bool
    {
        return RecordWatch::whereKey($id)->where('user_id', $userId)->where('activation', $activation)->whereNull('removed_at')->exists();
    }
}
