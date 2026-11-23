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
 * Pola strukturalne przypięte do trwałego `catalog_records.id` (przetrwają import nowej partii).
 *
 * @property int $id
 * @property int $record_id
 * @property RecordSpecStatus $status
 * @property SpecSourceKind $source_kind
 * @property string|null $source_ref
 * @property string $evidence_quote
 * @property string|null $ean
 * @property array<string, mixed> $attributes
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property string|null $disabled_reason
 * @property Carbon|null $disabled_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Record $record
 * @property-read User|null $verifier
 */
#[Table('catalog_record_specs')]
#[Fillable([
    'record_id',
    'status',
    'source_kind',
    'source_ref',
    'evidence_quote',
    'ean',
    'attributes',
    'verified_by',
    'verified_at',
    'disabled_reason',
    'disabled_at',
])]
class RecordSpec extends Model
{
    protected function casts(): array
    {
        return [
            'status' => RecordSpecStatus::class,
            'source_kind' => SpecSourceKind::class,
            'attributes' => 'array',
            'verified_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /** @param Builder<RecordSpec> $query */
    public function scopeVerified(Builder $query): void
    {
        $query->where('status', RecordSpecStatus::Verified->value);
    }

    /** @return BelongsTo<Record, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
