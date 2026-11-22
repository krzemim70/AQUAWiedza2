<?php

namespace App\Domain\Settings;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Niemutowalny wiersz: wycofanie zmiany = nowy wiersz.
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 * @property Carbon $effective_from
 * @property string $reason
 * @property-read User|null $author
 */
#[Fillable(['key', 'value', 'effective_from', 'created_by', 'reason'])]
class SettingVersion extends Model
{
    protected function casts(): array
    {
        return [
            'value' => 'json',
            'effective_from' => 'datetime:Y-m-d H:i:s.u',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
