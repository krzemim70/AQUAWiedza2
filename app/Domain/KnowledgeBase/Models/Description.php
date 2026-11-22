<?php

namespace App\Domain\KnowledgeBase\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Opis rekordu. Zredagowany ręcznie (`edited_at`) nie jest nadpisywany przez import.
 *
 * @property int $id
 * @property int $record_id
 * @property string $body
 * @property string|null $source_ref
 * @property int|null $edited_by
 * @property Carbon|null $edited_at
 */
#[Table('catalog_descriptions')]
#[Fillable(['record_id', 'body', 'source_ref', 'edited_by', 'edited_at'])]
class Description extends Model
{
    protected function casts(): array
    {
        return ['edited_at' => 'datetime'];
    }
}
