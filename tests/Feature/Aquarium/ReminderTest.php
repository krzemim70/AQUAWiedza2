<?php

use App\Domain\Aquarium\Actions\CancelReminder;
use App\Domain\Aquarium\Actions\CompleteReminder;
use App\Domain\Aquarium\Actions\CreateReminder;
use App\Domain\Aquarium\Actions\SaveJournalEntry;
use App\Domain\Aquarium\Actions\UpcomingReminders;
use App\Domain\Aquarium\Actions\UpdateReminder;
use App\Domain\Aquarium\Data\JournalEntryData;
use App\Domain\Aquarium\Data\ReminderData;
use App\Domain\Aquarium\Models\JournalEntry;
use App\Domain\Aquarium\Models\Reminder;
use App\Domain\Aquarium\Models\ReminderOccurrence;
use App\Livewire\Aquarium\TankJournal;
use App\Livewire\Aquarium\TankReminders;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(fn () => Carbon::setTestNow('2026-06-01 10:00:00'));
afterEach(fn () => Carbon::setTestNow());

function remind(User $user, int $tankId, string $due = '2026-06-05', ?string $interval = null): Reminder
{
    return app(CreateReminder::class)->handle($user, $tankId, new ReminderData('water_change', null, $due, $interval));
}

describe('completion', function () {
    it('closes a one-off reminder exactly once even when submitted twice', function () {
        $user = aquarist(true);
        $tank = tankFor($user);
        $reminder = remind($user, $tank->id);
        $occurrence = $reminder->occurrences()->sole();

        $first = app(CompleteReminder::class)->handle($user, $occurrence->id);
        Carbon::setTestNow('2026-06-01 11:00:00');
        $second = app(CompleteReminder::class)->handle($user, $occurrence->id);

        expect($first->state)->toBe(ReminderOccurrence::DONE)->and($second->completed_at->equalTo($first->completed_at))->toBeTrue()
            ->and($reminder->fresh()->state)->toBe(Reminder::COMPLETED)->and(ReminderOccurrence::count())->toBe(1);
    });

    it('creates exactly one next occurrence for a recurring reminder on a double submit', function () {
        $user = aquarist(true);
        $tank = tankFor($user);
        $occurrence = remind($user, $tank->id, '2026-06-05', '7')->occurrences()->sole();

        app(CompleteReminder::class)->handle($user, $occurrence->id);
        app(CompleteReminder::class)->handle($user, $occurrence->id);

        $all = ReminderOccurrence::orderBy('due_on')->get();
        expect($all)->toHaveCount(2)->and($all[0]->state)->toBe('done')->and($all[1]->state)->toBe('pending')
            ->and($all[1]->due_on->toDateString())->toBe('2026-06-12');
    });

    it('continues from the due date of the completed occurrence, not from today', function () {
        $user = aquarist(true);
        $tank = tankFor($user);
        $occurrence = remind($user, $tank->id, '2026-05-01', '10')->occurrences()->sole();

        app(CompleteReminder::class)->handle($user, $occurrence->id);

        expect(ReminderOccurrence::where('state', 'pending')->sole()->due_on->toDateString())->toBe('2026-05-11');
    });

    it('refuses to complete a cancelled reminder', function () {
        $user = aquarist(true);
        $tank = tankFor($user);
        $reminder = remind($user, $tank->id);
        app(CancelReminder::class)->handle($user, $reminder->id);

        expect(fn () => app(CompleteReminder::class)->handle($user, $reminder->occurrences()->sole()->id))->toThrow(ValidationException::class);
    });

    it('completes through the UI and drops the occurrence from the list', function () {
        $user = aquarist(true);
        $tank = tankFor($user);
        $occurrence = app(CreateReminder::class)->handle($user, $tank->id, new ReminderData('water_change', 'Duża podmiana', '2026-06-02'))->occurrences()->sole();

        Livewire::actingAs($user)->test(TankReminders::class, ['tankId' => $tank->id])
            ->assertSee('Duża podmiana')->call('complete', $occurrence->id)->call('complete', $occurrence->id)
            ->assertDontSee('Duża podmiana');
        expect(ReminderOccurrence::where('state', 'done')->count())->toBe(1);
    });
});

describe('editing', function () {
    it('moves the pending occurrence and bumps the version, keeping completed history', function () {
        $user = aquarist(true);
        $tank = tankFor($user);
        $reminder = remind($user, $tank->id, '2026-06-05', '7');
        app(CompleteReminder::class)->handle($user, $reminder->occurrences()->sole()->id);

        $updated = app(UpdateReminder::class)->handle($user, $reminder->id, new ReminderData('filter_cleaning', 'Filtr', '2026-06-20', '14', $reminder->fresh()->version));

        $pending = ReminderOccurrence::where('state', 'pending')->sole();
        expect($pending->due_on->toDateString())->toBe('2026-06-20')->and($updated->version)->toBe(2)
            ->and($updated->fresh())->interval_days->toBe(14)->label->toBe('Filtr')
            ->and(ReminderOccurrence::where('state', 'done')->count())->toBe(1);
    });

    it('rejects a stale version and a date that collides with an existing occurrence', function () {
        $user = aquarist(true);
        $tank = tankFor($user);
        $reminder = remind($user, $tank->id, '2026-06-05', '7');
        app(CompleteReminder::class)->handle($user, $reminder->occurrences()->orderBy('id')->first()->id);

        expect(fn () => app(UpdateReminder::class)->handle($user, $reminder->id, new ReminderData('other', null, '2026-06-20', null, 99)))
            ->toThrow(ValidationException::class);
        expect(fn () => app(UpdateReminder::class)->handle($user, $reminder->id, new ReminderData('other', null, '2026-06-05', null, $reminder->fresh()->version)))
            ->toThrow(ValidationException::class);
    });

    it('works from the form through to a changed due date', function () {
        $user = aquarist(true);
        $tank = tankFor($user);
        $reminder = remind($user, $tank->id, '2026-06-05');

        Livewire::actingAs($user)->test(TankReminders::class, ['tankId' => $tank->id])
            ->call('edit', $reminder->id)->assertSet('due_on', '2026-06-05')->assertSet('editingId', $reminder->id)->assertSee('Edycja przypomnienia')
            ->set('due_on', '2026-06-09')->set('label', 'Duża podmiana')->set('interval_days', '30')->call('save')->assertHasNoErrors()
            ->assertSet('editingId', null)->assertSee('Duża podmiana')->assertSee('09.06.2026');

        expect($reminder->fresh())->label->toBe('Duża podmiana')->interval_days->toBe(30)->version->toBe(2)
            ->and($reminder->occurrences()->sole()->due_on->toDateString())->toBe('2026-06-09');
    });

    it('keeps the interval inside the configured range', function (string $interval) {
        $user = aquarist(true);
        $tank = tankFor($user);

        expect(fn () => remind($user, $tank->id, '2026-06-05', $interval))->toThrow(ValidationException::class);
    })->with(['0', '366', '-3', 'abc', '1.5']);

    it('cancels a reminder and hides it from the list', function () {
        $user = aquarist(true);
        $tank = tankFor($user);
        $reminder = remind($user, $tank->id);

        app(CancelReminder::class)->handle($user, $reminder->id);
        expect(app(UpcomingReminders::class)->handle($user))->toHaveCount(0)
            ->and(fn () => app(UpdateReminder::class)->handle($user, $reminder->id, new ReminderData('other', null, '2026-06-09', null, $reminder->fresh()->version)))
            ->toThrow(ValidationException::class);
    });
});

describe('upcoming list', function () {
    it('includes overdue and everything within the settings horizon, nothing beyond', function () {
        $user = aquarist(true);
        $tank = tankFor($user);
        remind($user, $tank->id, '2026-05-20');
        remind($user, $tank->id, '2026-07-01');
        remind($user, $tank->id, '2026-07-02');

        $dates = app(UpcomingReminders::class)->handle($user, $tank->id)->map(fn ($o) => $o->due_on->toDateString())->all();

        expect($dates)->toBe(['2026-05-20', '2026-07-01']);
    });

    it('lists reminders of all own tanks only', function () {
        $user = aquarist(true);
        remind($user, tankFor($user, 'A')->id);
        remind($user, tankFor($user, 'B')->id);
        $other = aquarist(true);
        remind($other, tankFor($other)->id);

        expect(app(UpcomingReminders::class)->handle($user))->toHaveCount(2);
    });

    it('is closed to Tank, which gets the invitation instead', function () {
        $user = aquarist();
        $tank = tankFor($user);

        expect(fn () => app(UpcomingReminders::class)->handle($user, $tank->id))->toThrow(AuthorizationException::class)
            ->and(fn () => app(CreateReminder::class)->handle($user, $tank->id, new ReminderData('other', null, '2026-06-05')))->toThrow(AuthorizationException::class);
        $this->actingAs($user)->get('/moje-akwarium')->assertDontSee('Najbliższe przypomnienia');
    });
});

describe('journal', function () {
    it('stores entries for Fishroom, newest first, and escapes the content', function () {
        $user = aquarist(true);
        $tank = tankFor($user);

        Livewire::actingAs($user)->test(TankJournal::class, ['tankId' => $tank->id])
            ->set('entry_date', '2026-05-30')->set('content', 'Pierwszy <b>wpis</b>')->call('save')->assertHasNoErrors()
            ->assertSee('Pierwszy &lt;b&gt;wpis&lt;/b&gt;', false)->assertSet('content', '')
            ->set('content', '')->call('save')->assertHasErrors('content');
        expect(JournalEntry::count())->toBe(1);
    });

    it('is refused to Tank', function () {
        $user = aquarist();
        $tank = tankFor($user);

        expect(fn () => app(SaveJournalEntry::class)->handle($user, $tank->id, new JournalEntryData('2026-06-01', 'x')))->toThrow(AuthorizationException::class);
    });
});
