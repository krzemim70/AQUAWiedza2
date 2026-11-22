<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Models\Reminder;
use App\Domain\Aquarium\Models\ReminderOccurrence;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UpcomingReminders
{
    public function __construct(private Access $access, private Settings $settings) {}

    /**
     * Zaległe i najbliższe wystąpienia w horyzoncie z ustawień (daty z precyzją dnia, doba w strefie wyświetlania).
     *
     * @return Collection<int, ReminderOccurrence>
     */
    public function handle(User $actor, ?int $tankId = null): Collection
    {
        $tankId === null ? $this->access->authorize($actor, Feature::MeasurementHistory) : $this->access->tank($actor, $tankId, Feature::MeasurementHistory);

        $until = now()->timezone(config('app.display_timezone'))->addDays((int) $this->settings->get(SettingKey::ReminderHorizon))->toDateString();

        return ReminderOccurrence::query()
            ->where('state', ReminderOccurrence::PENDING)->whereDate('due_on', '<=', $until)
            ->whereHas('reminder', fn ($r) => $r->where('state', Reminder::ACTIVE)
                ->whereHas('tank', fn ($t) => $t->ownedBy($actor)->when($tankId, fn ($q) => $q->whereKey($tankId))))
            ->with('reminder.tank')->orderBy('due_on')->orderBy('id')->get();
    }
}
