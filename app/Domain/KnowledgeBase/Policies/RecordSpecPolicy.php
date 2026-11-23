<?php

namespace App\Domain\KnowledgeBase\Policies;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Models\RecordSpec;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Dostęp do redakcji i weryfikacji pól strukturalnych rozstrzyga wyłącznie ResolveAccess (CatalogSpecEdit). */
class RecordSpecPolicy
{
    public function __construct(private ResolveAccess $access) {}

    public function viewAny(User $user): Response
    {
        return $this->check($user);
    }

    public function view(User $user, RecordSpec $spec): Response
    {
        return $this->check($user);
    }

    public function create(User $user): Response
    {
        return $this->check($user);
    }

    public function update(User $user, RecordSpec $spec): Response
    {
        return $this->check($user);
    }

    public function verify(User $user, RecordSpec $spec): Response
    {
        return $this->check($user);
    }

    public function delete(User $user, RecordSpec $spec): bool
    {
        return false;
    }

    private function check(User $user): Response
    {
        $decision = $this->access->handle($user, Feature::CatalogSpecEdit);

        return $decision->allowed
            ? Response::allow()
            : Response::deny($decision->message ?? 'Brak uprawnień do redakcji specyfikacji.');
    }
}
