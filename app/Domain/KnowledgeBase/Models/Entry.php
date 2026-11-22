<?php

namespace App\Domain\KnowledgeBase\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Treść rekordu w jednej partii importu.
 * `fields` to surowe wartości kolumn, `tokens` to znormalizowane wartości (wielowartościowe po `;`).
 *
 * @property int $id
 * @property int $batch_id
 * @property int $record_id
 * @property int $position
 * @property string|null $name_pl
 * @property string|null $name_latin
 * @property string|null $product_name
 * @property array<string, string> $fields
 * @property array<string, list<string>> $tokens
 * @property string $search_text
 * @property-read Record $record
 */
#[Table('catalog_entries')]
#[Fillable(['batch_id', 'record_id', 'position', 'name_pl', 'name_latin', 'product_name', 'fields', 'tokens', 'search_text'])]
class Entry extends Model
{
    protected function casts(): array
    {
        return ['fields' => 'array', 'tokens' => 'array'];
    }

    /** @param Builder<Entry> $query */
    public function scopeInActiveBatch(Builder $query): void
    {
        $query->whereIn('batch_id', Batch::query()->active()->select('id'));
    }

    /**
     * Wskazana partia (null = aktywna), żeby wynik dał się odtworzyć po publikacji nowej bazy.
     *
     * @param  Builder<Entry>  $query
     */
    public function scopeInBatch(Builder $query, ?int $batch): void
    {
        $batch === null ? $query->inActiveBatch() : $query->where('batch_id', $batch);
    }

    /** @return BelongsTo<Batch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /** @return BelongsTo<Record, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    /** @return HasOne<Description, $this> */
    public function description(): HasOne
    {
        return $this->hasOne(Description::class, 'record_id', 'record_id');
    }

    /** @return HasMany<Photo, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'record_id', 'record_id');
    }

    public function label(): string
    {
        return $this->name_pl ?: $this->product_name ?: (string) $this->name_latin;
    }
}
