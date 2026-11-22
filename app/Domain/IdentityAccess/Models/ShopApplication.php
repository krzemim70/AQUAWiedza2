<?php

namespace App\Domain\IdentityAccess\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'company_name', 'nip', 'address', 'phone'])]
class ShopApplication extends Model
{
    public const PENDING = 'pending_verification';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
