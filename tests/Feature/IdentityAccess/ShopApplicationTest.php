<?php

use App\Domain\IdentityAccess\Actions\ApproveShopApplication;
use App\Domain\IdentityAccess\Actions\RejectShopApplication;
use App\Domain\IdentityAccess\Models\ShopApplication;
use App\Domain\Shops\Models\Shop;
use App\Models\User;
use Illuminate\Validation\ValidationException;

it('throws validation exception when approving or rejecting already approved shop application and changes nothing', function () {
    $admin1 = User::factory()->admin()->create();
    $admin2 = User::factory()->admin()->create();
    $applicant = User::factory()->shop()->create();

    $application = ShopApplication::create([
        'user_id' => $applicant->id,
        'company_name' => 'Sklep Testowy Sp. z o.o.',
        'nip' => '5260250274',
        'address' => 'ul. Akwariowa 10, Warszawa',
        'phone' => '500600700',
    ]);

    app(ApproveShopApplication::class)->handle($admin1, $application, 'Pierwsza weryfikacja poprawna');

    $application->refresh();
    expect($application->status)->toBe(ShopApplication::APPROVED)
        ->and($application->decided_by)->toBe($admin1->id)
        ->and($application->decision_reason)->toBe('Pierwsza weryfikacja poprawna');

    $shopsCount = Shop::query()->count();
    $decidedAt = $application->decided_at;

    expect(fn () => app(ApproveShopApplication::class)->handle($admin2, $application, 'Druga próba zatwierdzenia'))
        ->toThrow(ValidationException::class, 'Zgłoszenie zostało już rozpatrzone.');

    $application->refresh();
    expect($application->status)->toBe(ShopApplication::APPROVED)
        ->and($application->decided_by)->toBe($admin1->id)
        ->and($application->decision_reason)->toBe('Pierwsza weryfikacja poprawna')
        ->and($application->decided_at?->timestamp)->toBe($decidedAt?->timestamp)
        ->and(Shop::query()->count())->toBe($shopsCount);

    expect(fn () => app(RejectShopApplication::class)->handle($admin2, $application, 'Próba odrzucenia'))
        ->toThrow(ValidationException::class, 'Zgłoszenie zostało już rozpatrzone.');

    $application->refresh();
    expect($application->status)->toBe(ShopApplication::APPROVED)
        ->and($application->decided_by)->toBe($admin1->id)
        ->and($application->decision_reason)->toBe('Pierwsza weryfikacja poprawna')
        ->and($application->decided_at?->timestamp)->toBe($decidedAt?->timestamp)
        ->and(Shop::query()->count())->toBe($shopsCount);
});

it('throws validation exception when approving or rejecting already rejected shop application and changes nothing', function () {
    $admin1 = User::factory()->admin()->create();
    $admin2 = User::factory()->admin()->create();
    $applicant = User::factory()->shop()->create();

    $application = ShopApplication::create([
        'user_id' => $applicant->id,
        'company_name' => 'Sklep Odrzucony Sp. z o.o.',
        'nip' => '5260250274',
        'address' => 'ul. Rybna 5, Kraków',
        'phone' => '600700800',
    ]);

    app(RejectShopApplication::class)->handle($admin1, $application, 'Brak wymaganych dokumentów');

    $application->refresh();
    expect($application->status)->toBe(ShopApplication::REJECTED)
        ->and($application->decided_by)->toBe($admin1->id)
        ->and($application->decision_reason)->toBe('Brak wymaganych dokumentów');

    $shopsCount = Shop::query()->count();
    $decidedAt = $application->decided_at;

    expect(fn () => app(RejectShopApplication::class)->handle($admin2, $application, 'Drugie odrzucenie'))
        ->toThrow(ValidationException::class, 'Zgłoszenie zostało już rozpatrzone.');

    $application->refresh();
    expect($application->status)->toBe(ShopApplication::REJECTED)
        ->and($application->decided_by)->toBe($admin1->id)
        ->and($application->decision_reason)->toBe('Brak wymaganych dokumentów')
        ->and($application->decided_at?->timestamp)->toBe($decidedAt?->timestamp)
        ->and(Shop::query()->count())->toBe($shopsCount);

    expect(fn () => app(ApproveShopApplication::class)->handle($admin2, $application, 'Próba zatwierdzenia po odrzuceniu'))
        ->toThrow(ValidationException::class, 'Zgłoszenie zostało już rozpatrzone.');

    $application->refresh();
    expect($application->status)->toBe(ShopApplication::REJECTED)
        ->and($application->decided_by)->toBe($admin1->id)
        ->and($application->decision_reason)->toBe('Brak wymaganych dokumentów')
        ->and($application->decided_at?->timestamp)->toBe($decidedAt?->timestamp)
        ->and(Shop::query()->count())->toBe($shopsCount);
});
