<?php

use App\Domain\Settings\ChangeSetting;
use App\Domain\Settings\ChangeSettingData;
use App\Domain\Settings\SettingChanged;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Settings\SettingVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

function version(SettingKey $key, mixed $value, string $from): void
{
    SettingVersion::create(['key' => $key->value, 'value' => $value, 'effective_from' => $from, 'reason' => 'test']);
}

function change(mixed $value, SettingKey $key = SettingKey::FishroomPrice, ?Carbon $from = null, string $reason = 'powód'): SettingVersion
{
    return app(ChangeSetting::class)->handle(new ChangeSettingData(User::factory()->create(), $key, $value, $reason, $from));
}

describe('Settings::get', function () {
    beforeEach(fn () => Carbon::setTestNow('2026-06-01 12:00:00'));
    afterEach(fn () => Carbon::setTestNow());

    it('returns default without rows', function () {
        expect(app(Settings::class)->get(SettingKey::FishroomPrice))->toBe(1900);
    });

    it('returns the latest effective row and ignores future ones', function () {
        version(SettingKey::FishroomPrice, 60, '2026-01-01 00:00:00');
        version(SettingKey::FishroomPrice, 45, '2026-05-01 00:00:00');
        version(SettingKey::FishroomPrice, 10, '2026-07-01 00:00:00');

        expect(app(Settings::class)->get(SettingKey::FishroomPrice))->toBe(45);
    });

    it('returns the historic value with $at', function () {
        version(SettingKey::FishroomPrice, 60, '2026-01-01 00:00:00');
        version(SettingKey::FishroomPrice, 45, '2026-05-01 00:00:00');
        $s = app(Settings::class);

        expect($s->get(SettingKey::FishroomPrice, Carbon::parse('2026-03-01')))->toBe(60)
            ->and($s->get(SettingKey::FishroomPrice, Carbon::parse('2025-01-01')))->toBe(1900);
    });

    it('returns expected defaults for stage Teraz keys', function (SettingKey $key, mixed $expected) {
        expect(app(Settings::class)->get($key))->toBe($expected);
    })->with([
        'supplies' => [SettingKey::Supplies, ['tank' => 5, 'fishroom' => 50, 'low_stock_lead_days' => 14]],
        'push' => [SettingKey::PushNotifications, ['enabled' => true, 'reminder_hour' => 8, 'low_stock_default' => false]],
        'trends' => [SettingKey::MeasurementTrends, ['min_points' => 3, 'windows_days' => [7, 30, 90]]],
        'together' => [SettingKey::TogetherSpeciesLimits, ['min' => 2, 'max' => 8]],
        'catalog_sources' => [SettingKey::CatalogSpecSources, ['catalog_quote', 'manufacturer_label', 'literature']],
    ]);
});

it('every default passes its own rules', function (SettingKey $key) {
    expect(validator(['value' => $key->default()], $key->rules())->errors()->all())->toBe([]);
})->with(SettingKey::cases());

describe('ChangeSetting', function () {
    it('is forbidden without the gate', function () {
        change(1);
    })->throws(AuthorizationException::class);

    describe('with the gate', function () {
        beforeEach(fn () => Gate::define('manage-settings', fn () => true));

        it('rejects wrong type and range', function () {
            expect(fn () => change('abc'))->toThrow(ValidationException::class)
                ->and(fn () => change(0))->toThrow(ValidationException::class)
                ->and(fn () => change(['min' => 10, 'max' => 5], SettingKey::TankVolume))->toThrow(ValidationException::class);
        });

        it('requires four strictly increasing positive Redfield zone edges', function () {
            $edges = fn ($v) => fn () => change($v, SettingKey::RedfieldZoneEdges);

            expect($edges([1, 2, 3, 4]))->not->toThrow(ValidationException::class)
                ->and($edges([1, 2, 3]))->toThrow(ValidationException::class)
                ->and($edges([1, 2, 3, 4, 5]))->toThrow(ValidationException::class)
                ->and($edges([5, 5, 10, 20]))->toThrow(ValidationException::class)
                ->and($edges([5, 4, 10, 20]))->toThrow(ValidationException::class)
                ->and($edges([0, 1, 2, 3]))->toThrow(ValidationException::class);
        });

        it('requires a reason and rejects the past', function () {
            expect(fn () => change(30, reason: ''))->toThrow(ValidationException::class)
                ->and(fn () => change(30, from: now()->subMinutes(5)))->toThrow(ValidationException::class);
        });

        it('writes the row and the activity log', function () {
            $v = change(30);

            expect(SettingVersion::count())->toBe(1)
                ->and(app(Settings::class)->get(SettingKey::FishroomPrice))->toBe(30);
            $log = Activity::first();
            expect($log->subject_id)->toBe($v->id)
                ->and($log->properties['old'])->toBe(1900)
                ->and($log->properties['new'])->toBe(30);
        });

        it('dispatches SettingChanged only after commit', function () {
            $count = 0;
            Event::listen(SettingChanged::class, function () use (&$count) {
                $count++;
            });

            try {
                DB::transaction(function () {
                    change(30);
                    throw new RuntimeException('rollback');
                });
            } catch (RuntimeException) {
            }
            expect($count)->toBe(0);

            change(31);
            expect($count)->toBe(1);
        });
    });
});
