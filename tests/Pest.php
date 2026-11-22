<?php

use App\Domain\Aquarium\Actions\CreateTank;
use App\Domain\Aquarium\Actions\SaveMeasurement;
use App\Domain\Aquarium\Data\MeasurementData;
use App\Domain\Aquarium\Data\TankData;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\Aquarium\Models\Tank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/** Kopia paczki plików bazy wiedzy z fixtures w świeżym katalogu (do modyfikowania w testach). */
function kbPackage(): string
{
    $dir = storage_path('framework/testing/kb-'.bin2hex(random_bytes(6)));
    mkdir($dir, 0777, true);
    foreach (glob(base_path('tests/Fixtures/KnowledgeBase/*.php')) as $file) {
        copy($file, $dir.DIRECTORY_SEPARATOR.basename($file));
    }

    return $dir;
}

function aquarist(bool $fishroom = false): User
{
    return User::factory()->create($fishroom ? ['fishroom_ends_at' => now()->addDay()] : []);
}

function tankData(string $name = 'Salon', string $size = 'Średnie (101L - 250L)'): TankData
{
    return new TankData($name, 'Ogólne / Towarzyskie', $size);
}

function tankFor(User $user, string $name = 'Salon'): Tank
{
    return app(CreateTank::class)->handle($user, tankData($name));
}

/** @param array<string, string|null> $values */
function measure(User $user, int $tankId, array $values, ?Carbon\Carbon $at = null): Measurement
{
    return app(SaveMeasurement::class)->handle($user, $tankId, new MeasurementData($at ?? now(), $values));
}
