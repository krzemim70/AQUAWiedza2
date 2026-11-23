<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\Aquarium\Contracts\AccountData as AquariumData;
use App\Domain\IdentityAccess\Models\Consent;
use App\Domain\Notifications\Contracts\AccountData as NotificationData;
use App\Domain\Shops\Contracts\AccountData as ShopData;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExportAccount
{
    public function __construct(private AquariumData $aquarium, private ShopData $shops, private NotificationData $notifications) {}

    /** @return array<string, mixed> */
    public function handle(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $user = User::findOrFail($user->id);

            return [
                'exported_at' => now()->toISOString(),
                'account' => $user->only([
                    'id', 'name', 'email', 'city', 'type', 'is_admin', 'email_verified_at', 'adult_declared_at',
                    'fishroom_ends_at', 'suspended_at', 'created_at', 'updated_at',
                ]),
                'shop_application' => $user->shopApplication?->only([
                    'company_name', 'nip', 'address', 'phone', 'status', 'decided_at', 'created_at', 'updated_at',
                ]),
                'consents' => Consent::forUser($user)->orderBy('id')->get(['document', 'document_version', 'granted', 'ip', 'created_at'])->toArray(),
                'tanks' => $this->aquarium->tanksFor($user),
                'notification_preferences' => $this->notifications->exportFor($user->id),
                ...$this->shops->exportFor($user->id),
            ];
        });
    }
}
