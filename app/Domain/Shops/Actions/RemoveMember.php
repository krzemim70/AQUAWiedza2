<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Access;
use App\Domain\Shops\Models\Member;
use App\Domain\Shops\Models\MemberRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveMember
{
    public function __construct(private Access $access) {}

    public function handle(User $actor, Member $member): void
    {
        $this->access->authorizeOwner($actor, $member->shop_id);

        DB::transaction(function () use ($actor, $member) {
            $member = Member::lockForUpdate()->findOrFail($member->id);
            if ($member->role === MemberRole::Owner) {
                throw ValidationException::withMessages(['member' => 'Właściciela nie można usunąć z zespołu.']);
            }

            $member->delete();
            activity()->performedOn($member->shop)->causedBy($actor)->withProperties(['user_id' => $member->user_id])->log('member removed');
        });
    }
}
