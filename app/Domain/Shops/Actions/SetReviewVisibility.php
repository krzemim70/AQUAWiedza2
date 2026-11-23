<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SetReviewVisibility
{
    public function handle(User $admin, Review $review, bool $visible, ?string $reason = null): void
    {
        Gate::forUser($admin)->authorize('manage-users');

        $reason = Str::trim($reason ?? '');
        if (! $visible && ($reason === '' || mb_strlen($reason) > 255)) {
            throw ValidationException::withMessages(['reason' => 'Podaj powód ukrycia (do 255 znaków).']);
        }

        DB::transaction(function () use ($admin, $review, $visible, $reason): void {
            $review = Review::lockForUpdate()->findOrFail($review->id);
            if ($visible === ($review->hidden_reason === null)) {
                return;
            }

            $review->update(['hidden_reason' => $visible ? null : $reason]);
            activity('shop_reviews')->performedOn($review)->causedBy($admin)
                ->event($visible ? 'restored' : 'hidden')
                ->withProperties($visible ? [] : ['reason' => $reason])
                ->log($visible ? 'Przywrócono opinię o sklepie.' : 'Ukryto opinię o sklepie.');
        }, 3);
    }
}
