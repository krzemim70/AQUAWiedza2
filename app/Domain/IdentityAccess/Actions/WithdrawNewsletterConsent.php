<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\IdentityAccess\Models\Consent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class WithdrawNewsletterConsent
{
    public function handle(User $user, ?string $ip): void
    {
        DB::transaction(function () use ($user, $ip) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $latest = Consent::forUser($user)->where('document', 'newsletter')->latest('id')->first();

            if ($latest?->granted) {
                Consent::create([
                    'user_id' => $user->id, 'document' => 'newsletter', 'document_version' => $latest->document_version,
                    'granted' => false, 'ip' => $ip, 'created_at' => now(),
                ]);
            }
        });
    }
}
