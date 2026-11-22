<?php

use App\Domain\Aquarium\Models\Tank;
use App\Domain\IdentityAccess\Models\Tier;
use App\Domain\IdentityAccess\WordPressPassword;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/** Syntetyczne tabele WordPressa w bazie testowej (połączenie `legacy` wskazuje na tę samą bazę). */
const LEGACY_TABLES = [
    'wp_users', 'wp_usermeta', 'wp_aqs_subscriptions', 'wp_aqs_user_tanks', 'wp_aqs_user_parameters', 'wp_aqs_user_journal', 'wp_aqs_user_reminders',
    'wp_aqss_shops', 'wp_aqss_shop_members', 'wp_aqss_verifications', 'wp_aqss_offers', 'wp_posts', 'wp_postmeta',
];

function wpHash(string $password): string
{
    return '$wp'.password_hash(base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true)), PASSWORD_BCRYPT, ['cost' => 4]);
}

function legacyInsert(string $table, array $row): void
{
    DB::connection('legacy')->table($table)->insert($row);
}

function legacyUser(int $id, string $status = 'free', ?string $end = null, string $role = 'aqs_user', string $password = 'tajne-haslo'): void
{
    legacyInsert('wp_users', ['ID' => $id, 'user_login' => "u$id", 'user_pass' => wpHash($password), 'user_email' => "u$id@example.test", 'display_name' => "U $id", 'user_registered' => '2026-01-01 10:00:00']);
    legacyInsert('wp_usermeta', ['user_id' => $id, 'meta_key' => 'wp_capabilities', 'meta_value' => serialize([$role => true])]);
    if ($status !== 'none') {
        legacyInsert('wp_aqs_subscriptions', ['user_id' => $id, 'role' => 'aqs_user', 'status' => $status, 'trial_end' => $end, 'updated_at' => '2026-02-01 10:00:00']);
    }
}

beforeEach(function () {
    config(['database.connections.legacy.database' => config('database.connections.mysql.database')]);
    DB::purge('legacy');
    $schema = Schema::connection('legacy');
    foreach (array_reverse(LEGACY_TABLES) as $t) {
        $schema->dropIfExists($t);
    }
    $schema->create('wp_users', function (Blueprint $t) {
        $t->unsignedBigInteger('ID')->primary();
        $t->string('user_login');
        $t->string('user_pass');
        $t->string('user_email');
        $t->string('display_name');
        $t->dateTime('user_registered');
    });
    $schema->create('wp_usermeta', function (Blueprint $t) {
        $t->id('umeta_id');
        $t->unsignedBigInteger('user_id');
        $t->string('meta_key');
        $t->longText('meta_value')->nullable();
    });
    $schema->create('wp_aqs_subscriptions', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->string('role');
        $t->string('status');
        $t->dateTime('trial_end')->nullable();
        $t->dateTime('updated_at')->nullable();
    });
    $schema->create('wp_aqs_user_tanks', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->string('name');
        $t->unsignedSmallInteger('volume_liters')->nullable();
        $t->string('tank_type');
        $t->text('notes')->nullable();
        $t->dateTime('created_at')->nullable();
        $t->dateTime('updated_at')->nullable();
        foreach (['size_band', 'fish_size_band', 'temp_band', 'ph_band'] as $c) {
            $t->string($c)->nullable();
        }
    });
    $schema->create('wp_aqs_user_parameters', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->unsignedBigInteger('tank_id');
        $t->date('measured_at');
        $t->decimal('ph', 4, 2)->nullable();
        foreach (['temp_c', 'kh', 'gh', 'no3'] as $c) {
            $t->decimal($c, 6, 1)->nullable();
        }
        $t->decimal('po4', 6, 3)->nullable();
        $t->dateTime('created_at')->nullable();
    });
    $schema->create('wp_aqs_user_journal', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->unsignedBigInteger('tank_id');
        $t->date('entry_date');
        $t->text('content');
        $t->dateTime('created_at')->nullable();
    });
    $schema->create('wp_aqs_user_reminders', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('user_id');
        $t->unsignedBigInteger('tank_id');
        $t->string('reminder_type');
        $t->string('label')->nullable();
        $t->boolean('is_recurring')->default(false);
        $t->unsignedSmallInteger('interval_days')->nullable();
        $t->date('next_due')->nullable();
        $t->boolean('is_done')->default(false);
        $t->dateTime('created_at')->nullable();
        $t->dateTime('updated_at')->nullable();
    });
    // tabele sklepów i zdjęć muszą istnieć (import je czyta), ale w tych testach są puste
    $schema->create('wp_aqss_shops', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('owner_id');
        $t->string('name');
        $t->string('status');
        $t->string('address')->nullable();
        $t->string('hours')->nullable();
        $t->string('phone')->nullable();
        $t->dateTime('data_aktywacji')->nullable();
        $t->dateTime('created_at')->nullable();
        $t->dateTime('updated_at')->nullable();
    });
    $schema->create('wp_aqss_shop_members', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('shop_id');
        $t->unsignedBigInteger('user_id');
        $t->string('role');
    });
    $schema->create('wp_aqss_verifications', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('shop_id');
        $t->string('nip');
        $t->string('decision');
    });
    $schema->create('wp_aqss_offers', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('shop_id');
        $t->string('aqs_db');
        $t->string('aqs_record_id');
        $t->decimal('price', 10, 2);
        $t->unsignedInteger('pool_qty');
        $t->unsignedInteger('pool_reserved');
        $t->date('valid_from');
        $t->date('valid_to');
        $t->string('status');
        $t->string('variant')->nullable();
        $t->dateTime('created_at')->nullable();
        $t->dateTime('updated_at')->nullable();
    });
    $schema->create('wp_posts', function (Blueprint $t) {
        $t->unsignedBigInteger('ID')->primary();
        $t->string('guid');
    });
    $schema->create('wp_postmeta', function (Blueprint $t) {
        $t->id('meta_id');
        $t->unsignedBigInteger('post_id');
        $t->string('meta_key');
        $t->longText('meta_value')->nullable();
    });
});

afterEach(function () {
    foreach (array_reverse(LEGACY_TABLES) as $t) {
        Schema::connection('legacy')->dropIfExists($t);
    }
});

it('verifies phpass and WordPress 6.8 hashes', function () {
    expect(WordPressPassword::check('test12345', '$P$9IQRaTwmfeRo7ud9Fh4E2PdI0S3r.L0'))->toBeTrue()
        ->and(WordPressPassword::check('zle', '$P$9IQRaTwmfeRo7ud9Fh4E2PdI0S3r.L0'))->toBeFalse()
        ->and(WordPressPassword::check('abc', wpHash('abc')))->toBeTrue()
        ->and(WordPressPassword::check('abd', wpHash('abc')))->toBeFalse();
});

it('imports a user who can log in with the old hash and is rehashed to bcrypt', function () {
    legacyUser(1);
    Artisan::call('legacy:import');

    $user = User::where('email', 'u1@example.test')->firstOrFail();
    expect($user->password)->toStartWith('$wp$')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(DB::table('access_changes')->where('user_id', $user->id)->where('kind', 'imported')->count())->toBe(1);

    expect(auth()->attempt(['email' => 'u1@example.test', 'password' => 'zle']))->toBeFalse();
    expect(auth()->attempt(['email' => 'u1@example.test', 'password' => 'tajne-haslo']))->toBeTrue();
    expect($user->refresh()->password)->toStartWith('$2y$')
        ->and(Hash::check('tajne-haslo', $user->password))->toBeTrue();
});

it('maps subscriptions to tiers and roles to admin', function () {
    legacyUser(1, 'free');
    legacyUser(2, 'none');
    legacyUser(3, 'active', now()->addMonth()->toDateTimeString());
    legacyUser(4, 'suspended');
    legacyUser(5, 'trial');
    legacyUser(6, 'free', role: 'administrator');
    Artisan::call('legacy:import');

    $user = fn (int $n) => User::where('email', "u$n@example.test")->firstOrFail();
    expect($user(1)->tier())->toBe(Tier::Tank)
        ->and($user(2)->tier())->toBe(Tier::Tank)
        ->and($user(3)->tier())->toBe(Tier::Fishroom)
        ->and($user(4)->isSuspended())->toBeTrue()
        ->and($user(5)->tier())->toBe(Tier::Tank)
        ->and($user(6)->is_admin)->toBeTrue()
        ->and($user(1)->is_admin)->toBeFalse();
});

it('maps tank bands and keeps tanks without a size band', function () {
    legacyUser(1);
    legacyInsert('wp_aqs_user_tanks', [
        'user_id' => 1, 'name' => 'Salon', 'tank_type' => 'community', 'volume_liters' => 120,
        'size_band' => 'Średnie (101L - 250L)', 'fish_size_band' => 'Średnia (5-10 cm)', 'temp_band' => 'Grzałka często (22-25,5)', 'ph_band' => 'Woda obojętna (6.8 - 7.5)',
    ]);
    legacyInsert('wp_aqs_user_tanks', ['user_id' => 1, 'name' => 'Nano', 'tank_type' => 'nano', 'volume_liters' => 30]);
    legacyInsert('wp_aqs_user_parameters', ['user_id' => 1, 'tank_id' => 1, 'measured_at' => '2026-03-01', 'ph' => 7.2, 'created_at' => '2026-03-01 08:00:00']);
    legacyInsert('wp_aqs_user_parameters', ['user_id' => 1, 'tank_id' => 1, 'measured_at' => '2026-03-02']);
    Artisan::call('legacy:import');

    $salon = Tank::where('name', 'Salon')->firstOrFail();
    expect($salon->size_band?->value)->toBe('medium')
        ->and($salon->fish_size_band?->value)->toBe('medium')
        ->and($salon->temp_band?->value)->toBe('warm')
        ->and($salon->ph_band?->value)->toBe('neutral')
        ->and($salon->measurements()->count())->toBe(1);

    $nano = Tank::where('name', 'Nano')->firstOrFail();
    expect($nano->size_band)->toBeNull()
        ->and(DB::table('tanks')->where('id', $nano->id)->value('legacy_volume_liters'))->toBe(30);
});

it('skips orphaned rows and children of foreign tanks', function () {
    legacyUser(1);
    legacyInsert('wp_aqs_subscriptions', ['user_id' => 99, 'role' => 'aqs_user', 'status' => 'active', 'trial_end' => now()->addDay()->toDateTimeString()]);
    legacyInsert('wp_aqs_user_tanks', ['user_id' => 99, 'name' => 'Sierota', 'tank_type' => 'nano']);
    legacyInsert('wp_aqs_user_tanks', ['user_id' => 1, 'name' => 'Moje', 'tank_type' => 'nano']);
    legacyInsert('wp_aqs_user_journal', ['user_id' => 1, 'tank_id' => 1, 'entry_date' => '2026-03-01', 'content' => 'cudzy zbiornik']);
    legacyInsert('wp_aqs_user_journal', ['user_id' => 1, 'tank_id' => 2, 'entry_date' => '2026-03-01', 'content' => 'wpis']);
    legacyInsert('wp_aqs_user_journal', ['user_id' => 1, 'tank_id' => 77, 'entry_date' => '2026-03-01', 'content' => 'brak zbiornika']);
    Artisan::call('legacy:import');

    expect(User::count())->toBe(1)
        ->and(Tank::count())->toBe(1)
        ->and(DB::table('journal_entries')->count())->toBe(1);
});

it('imports reminders with done state', function () {
    legacyUser(1);
    legacyInsert('wp_aqs_user_tanks', ['user_id' => 1, 'name' => 'Moje', 'tank_type' => 'nano']);
    legacyInsert('wp_aqs_user_reminders', ['user_id' => 1, 'tank_id' => 1, 'reminder_type' => 'water_change', 'is_recurring' => 1, 'interval_days' => 7, 'next_due' => '2026-07-13']);
    legacyInsert('wp_aqs_user_reminders', ['user_id' => 1, 'tank_id' => 1, 'reminder_type' => 'other', 'label' => 'raz', 'next_due' => '2026-07-06', 'is_done' => 1, 'updated_at' => '2026-07-06 12:00:00']);
    Artisan::call('legacy:import');

    expect(DB::table('reminders')->where('state', 'active')->count())->toBe(1)
        ->and(DB::table('reminders')->where('state', 'completed')->count())->toBe(1)
        ->and(DB::table('reminder_occurrences')->where('state', 'done')->count())->toBe(1)
        ->and(DB::table('reminder_occurrences')->where('state', 'pending')->count())->toBe(1);
});

it('is idempotent on re-run', function () {
    legacyUser(1, 'active', now()->addMonth()->toDateTimeString());
    legacyInsert('wp_aqs_user_tanks', ['user_id' => 1, 'name' => 'Moje', 'tank_type' => 'nano']);
    legacyInsert('wp_aqs_user_journal', ['user_id' => 1, 'tank_id' => 1, 'entry_date' => '2026-03-01', 'content' => 'wpis']);

    Artisan::call('legacy:import');
    $counts = fn () => [User::count(), Tank::count(), DB::table('journal_entries')->count(), DB::table('access_changes')->count(), DB::table('legacy_id_map')->count()];
    $first = $counts();
    Artisan::call('legacy:import');

    expect($counts())->toBe($first);
});

it('writes nothing on dry run', function () {
    legacyUser(1);
    legacyInsert('wp_aqs_user_tanks', ['user_id' => 1, 'name' => 'Moje', 'tank_type' => 'nano']);
    Artisan::call('legacy:import', ['--dry-run' => true]);

    expect(User::count())->toBe(0)
        ->and(Tank::count())->toBe(0)
        ->and(DB::table('legacy_id_map')->count())->toBe(0)
        ->and(Artisan::output())->toContain('zaimportowano');
});
