<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Contracts\LowStockNotifier;
use App\Domain\Aquarium\Models\Reminder;
use App\Domain\Aquarium\Models\ReminderOccurrence;
use App\Domain\Aquarium\Notifications\ReminderDueNotice;
use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Contracts\Accounts;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Notifications\Contracts\Preferences;
use App\Domain\Notifications\Contracts\PushSubscriptions;
use App\Domain\Notifications\Topic;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dzienna wysyłka powiadomień push o przypomnieniach z terminem na dziś (po godzinie z ustawień, idempotentnie
 * przez `reminder_notices`) oraz wywołanie punktu rozszerzenia dla kończących się zapasów.
 */
class SendDueReminders
{
    public function __construct(
        private Settings $settings,
        private ResolveAccess $access,
        private Accounts $accounts,
        private Preferences $preferences,
        private PushSubscriptions $pushSubscriptions,
        private LowStockNotifier $lowStockNotifier,
    ) {}

    public function handle(?CarbonInterface $now = null): int
    {
        $config = (array) $this->settings->get(SettingKey::PushNotifications);
        if (! ($config['enabled'] ?? false)) {
            return 0;
        }

        $moment = Carbon::instance($now ?? now());
        $local = $moment->copy()->timezone((string) config('app.display_timezone', 'Europe/Warsaw'));
        if ($local->hour < (int) ($config['reminder_hour'] ?? 8)) {
            return 0;
        }

        $today = $local->toDateString();
        $sentAt = $moment->copy()->utc();

        $occurrences = ReminderOccurrence::query()
            ->where('state', ReminderOccurrence::PENDING)
            ->whereDate('due_on', $today)
            ->whereDoesntHave('notice')
            ->whereHas('reminder', fn ($query) => $query->where('state', Reminder::ACTIVE))
            ->with('reminder.tank')
            ->orderBy('id')
            ->get();

        /** @var array<int, list<ReminderOccurrence>> $byUser */
        $byUser = [];
        foreach ($occurrences as $occurrence) {
            $tank = $occurrence->reminder->tank;
            if ($tank === null) {
                continue;
            }
            $byUser[$tank->user_id][] = $occurrence;
        }

        $sent = 0;
        if ($byUser !== []) {
            $userIds = array_keys($byUser);
            $subscribedIds = array_flip($this->pushSubscriptions->userIdsWithSubscriptions($userIds));
            $users = $this->accounts->forIds($userIds);

            foreach ($byUser as $userId => $userOccurrences) {
                if (! isset($subscribedIds[$userId])) {
                    continue;
                }

                $user = $users->get($userId);
                if ($user === null
                    || ! $this->access->handle($user, Feature::PushNotifications)->allowed
                    || ! $this->access->handle($user, Feature::MeasurementHistory)->allowed
                    || ! $this->preferences->allowsPush($user->id, Topic::Reminders)) {
                    continue;
                }

                /** @var list<array{tank_id: int, tank_name: string, title: string}> $items */
                $items = DB::transaction(function () use ($userOccurrences, $sentAt): array {
                    $claimed = [];
                    foreach ($userOccurrences as $occurrence) {
                        $tank = $occurrence->reminder->tank;
                        if ($tank === null) {
                            continue;
                        }
                        $inserted = DB::table('reminder_notices')->insertOrIgnore([
                            'occurrence_id' => $occurrence->id,
                            'sent_at' => $sentAt,
                        ]);
                        if ($inserted === 1) {
                            $claimed[] = [
                                'tank_id' => $tank->id,
                                'tank_name' => $tank->name,
                                'title' => $occurrence->reminder->title(),
                            ];
                        }
                    }

                    return $claimed;
                });

                if ($items !== []) {
                    $user->notify(new ReminderDueNotice($items));
                    $sent += count($items);
                }
            }
        }

        return $sent + $this->lowStockNotifier->handle($local);
    }
}
