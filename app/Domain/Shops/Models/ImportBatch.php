<?php

namespace App\Domain\Shops\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $shop_id
 * @property int|null $user_id
 * @property string $file_sha256
 * @property string|null $file_path
 * @property string $state
 * @property int $last_line
 * @property array{added: int, updated: int, unchanged: int, errors: int} $counts
 * @property array{encoding: string, separator: string, total: int, rows: array<int, array<string, mixed>>, errors: array<int, list<string>>, failure?: string} $report
 * @property array<int, array{location_id: int, area: string, record_id: string, variant: string, price: int, qty: int, from: string, to: string, publish: bool, offer_id: int|null, version: int|null}>|null $approved
 * @property Carbon $created_at
 * @property Carbon $expires_at
 */
#[Table('shop_import_batches')]
class ImportBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['counts' => 'array', 'report' => 'array', 'approved' => 'array', 'expires_at' => 'datetime'];
    }

    /** @return Builder<self> */
    public static function forActor(User $actor, Shop $shop): Builder
    {
        return self::where('shop_id', $shop->id)->where('user_id', $actor->id)->where('expires_at', '>', now());
    }

    /** @return BelongsTo<Shop, $this> */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deleteFile(): void
    {
        if ($this->file_path !== null && Storage::disk('local')->delete($this->file_path)) {
            $this->update(['file_path' => null]);
        }
    }
}
