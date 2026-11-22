<?php

use App\Domain\Advisor\Engine\FishBand;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Domain\Aquarium\Actions\CancelReminder;
use App\Domain\Aquarium\Actions\ChangeLivestock;
use App\Domain\Aquarium\Actions\CompleteReminder;
use App\Domain\Aquarium\Actions\CreateReminder;
use App\Domain\Aquarium\Actions\CreateTank;
use App\Domain\Aquarium\Actions\DeleteTank;
use App\Domain\Aquarium\Actions\LatestMeasurement;
use App\Domain\Aquarium\Actions\ListMeasurements;
use App\Domain\Aquarium\Actions\SaveJournalEntry;
use App\Domain\Aquarium\Actions\SaveMeasurement;
use App\Domain\Aquarium\Actions\UpcomingReminders;
use App\Domain\Aquarium\Actions\UpdateReminder;
use App\Domain\Aquarium\Actions\UpdateTank;
use App\Domain\Aquarium\Data\JournalEntryData;
use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\Aquarium\Data\MeasurementData;
use App\Domain\Aquarium\Data\ReminderData;
use App\Domain\Aquarium\Data\TankData;
use App\Domain\Aquarium\Models\JournalEntry;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\Aquarium\Models\Reminder;
use App\Domain\Aquarium\Models\ReminderOccurrence;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Settings\SettingVersion;
use App\Livewire\Aquarium\TankForm;
use App\Livewire\Aquarium\TankLivestock;
use App\Livewire\Aquarium\TankShow;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

describe('access and limit', function () {
    it('keeps the aquarist area behind login and shows the tank list', function () {
        $this->get('/moje-akwarium')->assertRedirect('/logowanie');

        $user = aquarist();
        tankFor($user);
        $this->actingAs($user)->get('/moje-akwarium')->assertOk()->assertSee('Salon');
    });

    it('limits Tank to one tank but leaves Fishroom unlimited', function () {
        $tank = aquarist();
        tankFor($tank);
        expect(fn () => tankFor($tank, 'Drugi'))->toThrow(AuthorizationException::class)
            ->and(Tank::ownedBy($tank)->count())->toBe(1);

        $fish = aquarist(true);
        tankFor($fish, 'A');
        tankFor($fish, 'B');
        tankFor($fish, 'C');
        expect(Tank::ownedBy($fish)->count())->toBe(3);
    });

    it('takes the limit from settings', function () {
        SettingVersion::create(['key' => SettingKey::TankLimit->value, 'value' => ['drop' => 0, 'tank' => 2, 'fishroom' => null], 'effective_from' => now()->subMinute(), 'reason' => 'test']);
        app(Settings::class)->forget(SettingKey::TankLimit);

        $user = aquarist();
        tankFor($user, 'A');
        tankFor($user, 'B');
        expect(fn () => tankFor($user, 'C'))->toThrow(AuthorizationException::class);
    });

    it('serialises creation on the account row so concurrent creates cannot exceed the limit', function () {
        $user = aquarist();
        $queries = [];
        DB::listen(function ($q) use (&$queries) {
            $queries[] = strtolower($q->sql);
        });

        tankFor($user);

        $lock = collect($queries)->search(fn ($s) => str_contains($s, 'from `users`') && str_contains($s, 'for update'));
        $count = collect($queries)->search(fn ($s) => str_contains($s, 'count(*)') && str_contains($s, 'from `tanks`'));
        $insert = collect($queries)->search(fn ($s) => str_starts_with($s, 'insert into `tanks`'));
        expect($lock)->not->toBeFalse()->and($lock)->toBeLessThan($count)->and($count)->toBeLessThan($insert);
    });

    it('keeps tanks over the limit editable', function () {
        $user = aquarist();
        foreach (['A', 'B'] as $name) {
            Tank::create(['user_id' => $user->id, 'name' => $name, 'type' => 'Roślinne', 'size_band' => TankBand::Small]);
        }
        $first = Tank::ownedBy($user)->first();

        app(UpdateTank::class)->handle($user, $first->id, tankData('Nowa nazwa'));

        expect($first->fresh()->name)->toBe('Nowa nazwa')
            ->and(fn () => tankFor($user, 'C'))->toThrow(AuthorizationException::class);
        $this->actingAs($user)->get(route('aquarium.tank', $first->id))->assertOk();
    });

    it('shows the limit message instead of the form when no more tanks are allowed', function () {
        $user = aquarist();
        tankFor($user);

        $this->actingAs($user)->get('/moje-akwarium')->assertSee('możesz mieć maksymalnie 1 zbiornik');
    });
});

describe('bands', function () {
    it('round-trips every size band including angle brackets', function (TankBand $size) {
        $user = aquarist();

        Livewire::actingAs($user)->test(TankForm::class)
            ->set('name', 'Test')->set('type', 'Roślinne')->set('size_band', $size->value)
            ->call('save')->assertHasNoErrors();

        $tank = Tank::ownedBy($user)->sole();
        expect($tank->size_band)->toBe($size)
            ->and(DB::table('tanks')->value('size_band'))->toBe($size->value)
            ->and($tank->volumeLiters())->toBe($size->volume());

        Livewire::actingAs($user)->test(TankForm::class, ['tankId' => $tank->id])->assertSet('size_band', $size->value);
    })->with(TankBand::cases());

    it('stores the stable band code, shows the Polish label and uses the representative volume', function () {
        $user = aquarist();
        $tank = app(CreateTank::class)->handle($user, tankData('Nano', 'nano'));

        expect($tank->fresh()->size_band)->toBe(TankBand::Nano)->and(DB::table('tanks')->value('size_band'))->toBe('nano')->and($tank->volumeLiters())->toBe(30);
        Livewire::actingAs($user)->test(TankForm::class)->assertSeeHtml('value="nano"')->assertSeeHtml('Nano (&lt; 40L)');
    });

    it('stores optional preferences and rejects unknown values', function () {
        $user = aquarist(true);
        $tank = app(CreateTank::class)->handle($user, new TankData('Z', 'Roślinne', 'large', 'huge', 'warm', 'acidic', ' notatka '));

        expect($tank->fresh())->fish_size_band->toBe(FishBand::Huge)->temp_band->toBe(TemperatureBand::Warm)
            ->ph_band->toBe(PhBand::Acidic)->notes->toBe('notatka');
        expect(fn () => app(CreateTank::class)->handle($user, tankData('X', 'Nano (< 40L)')))->toThrow(ValidationException::class);
        expect(fn () => app(CreateTank::class)->handle($user, tankData('')))->toThrow(ValidationException::class);
    });

    it('shows validation messages in the form', function () {
        Livewire::actingAs(aquarist())->test(TankForm::class)->call('save')
            ->assertHasErrors(['name', 'type', 'size_band'])->assertSee('jest wymagane');
    });
});

describe('ownership', function () {
    it('answers 404 for a foreign tank in every entry point', function () {
        $owner = aquarist(true);
        $tank = tankFor($owner);
        $other = aquarist(true);
        $this->actingAs($other)->get(route('aquarium.tank', $tank->id))->assertNotFound();

        $reminder = app(CreateReminder::class)->handle($owner, $tank->id, new ReminderData('other', null, '2026-12-01'));
        $occurrence = $reminder->occurrences()->sole();
        $now = now();

        $calls = [
            fn () => app(UpdateTank::class)->handle($other, $tank->id, tankData()),
            fn () => app(DeleteTank::class)->handle($other, $tank->id),
            fn () => app(SaveMeasurement::class)->handle($other, $tank->id, new MeasurementData($now, ['ph' => '7'])),
            fn () => app(LatestMeasurement::class)->handle($other, $tank->id),
            fn () => app(ListMeasurements::class)->handle($other, $tank->id),
            fn () => app(SaveJournalEntry::class)->handle($other, $tank->id, new JournalEntryData('2026-12-01', 'x')),
            fn () => app(ChangeLivestock::class)->handle($other, $tank->id, new LivestockData(Area::Fauna, 'FN0001', 1)),
            fn () => app(CreateReminder::class)->handle($other, $tank->id, new ReminderData('other', null, '2026-12-01')),
            fn () => app(UpcomingReminders::class)->handle($other, $tank->id),
            fn () => app(UpdateReminder::class)->handle($other, $reminder->id, new ReminderData('other', null, '2026-12-02', null, 1)),
            fn () => app(CompleteReminder::class)->handle($other, $occurrence->id),
            fn () => app(CancelReminder::class)->handle($other, $reminder->id),
        ];
        foreach ($calls as $call) {
            expect($call)->toThrow(ModelNotFoundException::class);
        }
        Livewire::actingAs($other)->test(TankLivestock::class, ['tankId' => $tank->id])->assertNotFound();
        Livewire::actingAs($other)->test(TankForm::class, ['tankId' => $tank->id])->assertNotFound();
        Livewire::actingAs($other)->test(TankShow::class, ['tank' => $tank->id])->assertNotFound();
        expect(Tank::find($tank->id))->not->toBeNull()->and($tank->fresh()->name)->toBe('Salon');
    });

    it('deletes a tank with all its children in one go and leaves other tanks alone', function () {
        $user = aquarist(true);
        $tank = tankFor($user);
        $other = tankFor($user, 'Inny');
        foreach ([$tank, $other] as $t) {
            Livestock::create(['tank_id' => $t->id, 'area' => Area::Fauna, 'catalog_id' => 'FN0001', 'quantity' => 3]);
            Measurement::create(['tank_id' => $t->id, 'measured_at' => now(), 'ph' => '7.0']);
            JournalEntry::create(['tank_id' => $t->id, 'entry_date' => '2026-12-01', 'content' => 'x']);
            $r = Reminder::create(['tank_id' => $t->id, 'type' => 'other', 'starts_on' => '2026-12-01']);
            ReminderOccurrence::create(['reminder_id' => $r->id, 'due_on' => '2026-12-01']);
        }

        app(DeleteTank::class)->handle($user, $tank->id);

        expect(Tank::count())->toBe(1);
        foreach (['tank_livestock', 'water_measurements', 'journal_entries', 'reminders', 'reminder_occurrences'] as $table) {
            expect(DB::table($table)->count())->toBe(1);
        }
    });
});

describe('livestock', function () {
    beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

    it('adds species from the catalogue for Tank and Fishroom, changes and removes them', function (bool $fishroom) {
        $user = aquarist($fishroom);
        $tank = tankFor($user);

        $component = Livewire::actingAs($user)->test(TankLivestock::class, ['tankId' => $tank->id])
            ->set('phrase', 'gupik')->assertSee('Gupik Pawie Oczko')
            ->set('quantity', '4')->call('add', 'fauna', 'FN0001')->assertHasNoErrors()->assertSee('Gupik Pawie Oczko');
        $row = Livestock::sole();
        expect($row->quantity)->toBe(4);

        $component->set("quantities.$row->id", '6')->call('changeQuantity', $row->id);
        expect($row->fresh()->quantity)->toBe(6);

        $component->call('remove', $row->id);
        expect(Livestock::count())->toBe(0);
    })->with([false, true]);

    it('rejects unknown species and equipment keys', function () {
        $user = aquarist();
        $tank = tankFor($user);

        Livewire::actingAs($user)->test(TankLivestock::class, ['tankId' => $tank->id])
            ->call('add', 'fauna', 'FN9999')->assertHasErrors('species')
            ->call('add', 'equipment', 'SP0001')->assertHasErrors('species');
        expect(Livestock::count())->toBe(0);
    });
});
