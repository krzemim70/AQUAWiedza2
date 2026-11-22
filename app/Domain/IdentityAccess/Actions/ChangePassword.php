<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Models\User;
use Illuminate\Support\Str;
use SensitiveParameter;

class ChangePassword
{
    public function handle(User $user, #[SensitiveParameter] string $password): void
    {
        // Hash w sesji i token „Zapamiętaj mnie” przestają pasować na pozostałych urządzeniach.
        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
    }
}
