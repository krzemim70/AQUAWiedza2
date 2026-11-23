<?php

namespace App\Domain\Shops\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $nip
 * @property ShopState $state
 * @property Carbon|null $activated_at
 */
#[Fillable(['name', 'slug', 'nip', 'state'])]
class Shop extends Model
{
    protected function casts(): array
    {
        return ['state' => ShopState::class, 'activated_at' => 'datetime'];
    }

    /** @return HasMany<Location, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    /** @return HasMany<Member, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /** @return HasMany<Offer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<ActivationException, $this> */
    public function activationExceptions(): HasMany
    {
        return $this->hasMany(ActivationException::class);
    }
}
