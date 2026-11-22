<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\IdentityAccess\Models\Consent;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Zgody na aktualną wersję dokumentów (regulamin, polityka prywatności, pełnoletność); newsletter jest opcjonalny. */
class AcceptConsents
{
    private const REQUIRED = ['terms', 'privacy', 'adult'];

    public function current(User $user): bool
    {
        return Consent::forUser($user)->where('granted', true)->whereIn('document', self::REQUIRED)
            ->where('document_version', $this->version())->distinct()->count('document') === count(self::REQUIRED);
    }

    public function handle(User $user, bool $newsletter, ?string $ip): void
    {
        $version = $this->version();
        DB::transaction(function () use ($user, $newsletter, $ip, $version) {
            $user = User::lockForUpdate()->findOrFail($user->id);
            foreach ([...self::REQUIRED, 'newsletter'] as $document) {
                Consent::create([
                    'user_id' => $user->id, 'document' => $document, 'document_version' => $version,
                    'granted' => $document === 'newsletter' ? $newsletter : true, 'ip' => $ip, 'created_at' => now(),
                ]);
            }
            $user->forceFill(['adult_declared_at' => $user->adult_declared_at ?? now()])->save();
        });
    }

    private function version(): string
    {
        return (string) app(Settings::class)->get(SettingKey::ConsentVersion);
    }
}
