<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\IdentityAccess\Data\AccessDecision;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\IdentityAccess\Models\Tier;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;

/**
 * Jedyne miejsce decyzji o dostępie i komunikatów odmowy. Odczyt, bez zapisów.
 * Kontekst: `tank_count` (TankAdd).
 */
class ResolveAccess
{
    private const RANK = ['drop' => 0, 'tank' => 1, 'fishroom' => 2];

    public function __construct(private Settings $settings) {}

    /** @param array<string, mixed> $context */
    public function handle(?User $actor, Feature $feature, array $context = []): AccessDecision
    {
        $tier = $actor?->tier() ?? Tier::Drop;
        $deny = fn (string $reason, string $message) => new AccessDecision(false, $tier, $reason, $message);
        $allow = fn (string $reason) => new AccessDecision(true, $tier, $reason);

        if ($feature === Feature::DeleteAccount && $actor?->is_admin) {
            return $deny('admin_account', 'Konto administratora może usunąć tylko operator serwisu.');
        }
        if ($actor?->isSuspended()) {
            return $deny('suspended', 'Konto jest zawieszone. Skontaktuj się z administratorem.');
        }
        if ($actor?->is_admin) {
            return $allow('admin');
        }

        $login = 'Ta funkcja wymaga zalogowania. Załóż bezpłatne konto Tank.';

        switch ($feature) {
            case Feature::AdminPanel:
            case Feature::CatalogSpecEdit:
                return $deny('not_admin', 'Brak uprawnień administratora.');
            case Feature::ShopPanel:
                return $actor?->isShopMember() ? $allow('shop_member')
                    : $deny('not_shop', $actor ? 'Panel sklepu jest dostępny po zatwierdzeniu zgłoszenia sklepu lub dodaniu do zespołu sklepu.' : $login);
            case Feature::DropSelection:
                return $actor ? $deny('has_account', 'Masz konto. Skorzystaj z doboru dla swojego zbiornika.') : $allow('guest');
            default:
        }

        $min = $feature->minTier() ?? Tier::Tank;
        if ($feature === Feature::GuidedAdvisor && $this->settings->get(SettingKey::GuidedAdvisorForTank)) {
            $min = Tier::Tank;
        }
        if ($feature === Feature::CreatorOffers && $this->settings->get(SettingKey::CreatorGuestOffers)) {
            $min = Tier::Drop;
        }
        if (self::RANK[$tier->value] < self::RANK[$min->value]) {
            return $actor
                ? $deny('fishroom_required', 'Ta funkcja jest dostępna w planie Fishroom.')
                : $deny('login_required', $login);
        }

        if ($feature === Feature::TankAdd) {
            $limit = $this->settings->get(SettingKey::TankLimit)[$tier->value] ?? null;
            if ($limit !== null && (int) ($context['tank_count'] ?? 0) >= $limit) {
                return $deny('tank_limit', "Na koncie {$tier->label()} możesz mieć maksymalnie $limit zbiornik(i). Fishroom nie ma limitu.");
            }
        }

        return $allow($actor ? $tier->value : 'guest');
    }
}
