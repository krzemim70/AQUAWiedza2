<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PurgeUnverifiedAccounts
{
    public function __construct(private Settings $settings, private DeleteAccount $deleteAccount) {}

    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->settings->get(SettingKey::PendingRetentionDays));
        $batch = (int) $this->settings->get(SettingKey::PendingCleanupBatch);
        $count = 0;

        foreach (User::whereNull('email_verified_at')->where('is_admin', false)->where('created_at', '<', $cutoff)->lazyById($batch) as $user) {
            try {
                $count += (int) $this->deleteAccount->handle($user, $cutoff);
            } catch (ValidationException $exception) {
                if (! isset($exception->errors()['deletion'])) {
                    throw $exception;
                }
            }
        }

        return $count;
    }
}
