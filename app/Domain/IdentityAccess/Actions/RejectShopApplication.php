<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\IdentityAccess\Models\ShopApplication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RejectShopApplication
{
    public function handle(User $actor, ShopApplication $application, string $reason): void
    {
        Gate::forUser($actor)->authorize('manage-users');

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Podaj powód odrzucenia.']);
        }

        DB::transaction(function () use ($actor, $application, $reason) {
            $application = ShopApplication::lockForUpdate()->findOrFail($application->id);
            if ($application->status !== ShopApplication::PENDING) {
                throw ValidationException::withMessages(['status' => 'Zgłoszenie zostało już rozpatrzone.']);
            }

            $application->forceFill(['status' => ShopApplication::REJECTED, 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_reason' => $reason])->save();
        });
    }
}
