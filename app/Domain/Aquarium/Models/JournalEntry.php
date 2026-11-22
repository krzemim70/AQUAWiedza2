<?php

namespace App\Domain\Aquarium\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tank_id
 * @property Carbon $entry_date
 * @property string $content
 */
#[Fillable(['tank_id', 'entry_date', 'content'])]
class JournalEntry extends Model
{
    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }
}
