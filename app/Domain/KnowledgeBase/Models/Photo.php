<?php

namespace App\Domain\KnowledgeBase\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Metadane zdjęcia (bez plików). Publicznie czytać wolno tylko `verified`.
 *
 * @property int $id
 * @property int $record_id
 * @property string|null $url
 * @property string|null $file_path
 * @property string|null $source_url
 * @property string|null $author
 * @property string|null $licence
 * @property bool $verified
 * @property Carbon|null $verified_at
 * @property-read Record $record
 */
#[Table('catalog_photos')]
#[Fillable(['record_id', 'url', 'file_path', 'source_url', 'author', 'licence', 'verified', 'verified_at'])]
class Photo extends Model
{
    protected function casts(): array
    {
        return ['verified' => 'boolean', 'verified_at' => 'datetime'];
    }

    /** @return BelongsTo<Record, $this> */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }
}
