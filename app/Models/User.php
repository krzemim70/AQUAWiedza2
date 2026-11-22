<?php

namespace App\Models;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\AccessChange;
use App\Domain\IdentityAccess\Models\AccountType;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\IdentityAccess\Models\ShopApplication;
use App\Domain\IdentityAccess\Models\Tier;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property AccountType $type
 * @property bool|null $is_admin
 * @property Carbon|null $fishroom_ends_at
 * @property Carbon|null $suspended_at
 */
#[Fillable(['name', 'email', 'password', 'type'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        $feature = match ($panel->getId()) {
            'admin' => Feature::AdminPanel,
            'shop' => Feature::ShopPanel,
            default => null,
        };

        return $feature !== null && app(ResolveAccess::class)->handle($this, $feature)->allowed;
    }

    /** Brak danych dostępu = Tank. Data końca Fishroom obowiązuje od razu, nawet gdy zadanie wygaszania się spóźni. */
    public function tier(): Tier
    {
        return $this->fishroom_ends_at?->isFuture() ? Tier::Fishroom : Tier::Tank;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function isApprovedShop(): bool
    {
        return $this->type === AccountType::Shop && $this->shopApplication?->status === ShopApplication::APPROVED;
    }

    /** Dokąd trafia użytkownik po zalogowaniu. */
    public function homePath(): string
    {
        $access = app(ResolveAccess::class);

        return match (true) {
            $access->handle($this, Feature::AdminPanel)->allowed => '/admin',
            $access->handle($this, Feature::ShopPanel)->allowed => '/panel-sklepu',
            default => '/moje-akwarium',
        };
    }

    /** @return HasMany<AccessChange, $this> */
    public function accessChanges(): HasMany
    {
        return $this->hasMany(AccessChange::class)->latest('id');
    }

    /** @return HasOne<ShopApplication, $this> */
    public function shopApplication(): HasOne
    {
        return $this->hasOne(ShopApplication::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'type' => AccountType::class,
            'is_admin' => 'boolean',
            'fishroom_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }
}
