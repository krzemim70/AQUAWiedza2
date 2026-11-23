<?php

namespace App\Domain\Shops\Models;

use App\Domain\KnowledgeBase\Models\Area;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Area $area
 * @property string $legacy_id
 * @property string $activation
 * @property Carbon $created_at
 * @property Carbon|null $notified_at
 * @property Carbon|null $removed_at
 */
class RecordWatch extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['area' => Area::class, 'notified_at' => 'datetime', 'removed_at' => 'datetime'];
    }

    /** @param Builder<$this> $query */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('removed_at')->whereNull('notified_at');
    }
}
