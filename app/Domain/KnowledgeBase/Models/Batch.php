<?php

namespace App\Domain\KnowledgeBase\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Partia importu. Stany: pending (zapisana), active (najwyżej jedna), superseded (wcześniej aktywna, do przywrócenia), failed.
 *
 * @property int $id
 * @property string $state
 * @property int|null $active_slot
 * @property string|null $checksum
 * @property array<string, array{sha256: string, rows: int|null}> $files
 * @property array<string, int> $counts
 * @property list<string> $errors
 * @property int|null $created_by
 * @property Carbon|null $activated_at
 * @property Carbon $created_at
 */
#[Table('catalog_import_batches')]
#[Fillable(['state', 'active_slot', 'checksum', 'files', 'counts', 'errors', 'created_by', 'activated_at'])]
class Batch extends Model
{
    public const PENDING = 'pending';

    public const ACTIVE = 'active';

    public const SUPERSEDED = 'superseded';

    public const FAILED = 'failed';

    protected function casts(): array
    {
        return ['files' => 'array', 'counts' => 'array', 'errors' => 'array', 'activated_at' => 'datetime'];
    }

    /** @param Builder<Batch> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active_slot', 1);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
