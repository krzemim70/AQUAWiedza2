<?php

namespace App\Domain\Aquarium;

use App\Domain\Aquarium\Models\Reminder;
use App\Domain\Aquarium\Models\ReminderOccurrence;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Bramka modułu: własność zasobu (obcy id = 404, bez potwierdzania istnienia), potem decyzja ResolveAccess.
 * Ta sama klasa służy akcjom i widokom (widoczność zakładek).
 */
class Access
{
    public function __construct(private ResolveAccess $access) {}

    /** @param array<string, mixed> $context */
    public function allows(?User $actor, Feature $feature, array $context = []): bool
    {
        return $this->access->handle($actor, $feature, $context)->allowed;
    }

    /** @param array<string, mixed> $context */
    public function authorize(?User $actor, Feature $feature, array $context = []): void
    {
        $decision = $this->access->handle($actor, $feature, $context);
        if (! $decision->allowed) {
            throw new AuthorizationException($decision->message);
        }
    }

    /** @param array<string, mixed> $context */
    public function message(?User $actor, Feature $feature, array $context = []): string
    {
        return $this->access->handle($actor, $feature, $context)->message ?? '';
    }

    public function tank(User $actor, int $tankId, Feature $feature = Feature::TankView, bool $forUpdate = false): Tank
    {
        $tank = Tank::ownedBy($actor)->when($forUpdate, fn ($query) => $query->lockForUpdate())->findOrFail($tankId);
        $this->authorize($actor, $feature);

        return $tank;
    }

    public function reminder(User $actor, int $reminderId): Reminder
    {
        $reminder = Reminder::whereHas('tank', fn ($q) => $q->ownedBy($actor))->findOrFail($reminderId);
        $this->authorize($actor, Feature::MeasurementHistory);

        return $reminder;
    }

    public function occurrence(User $actor, int $occurrenceId): ReminderOccurrence
    {
        $occurrence = ReminderOccurrence::whereHas('reminder.tank', fn ($q) => $q->ownedBy($actor))->findOrFail($occurrenceId);
        $this->authorize($actor, Feature::MeasurementHistory);

        return $occurrence;
    }
}
