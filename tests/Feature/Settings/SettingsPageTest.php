<?php

use App\Domain\Settings\SettingKey;
use App\Filament\Pages\SettingsPage;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

// Livewire test of the page component directly: canAccessPanel() is false until WP-03.
it('renders and changes a setting for an allowed user', function () {
    $this->travelTo(now()->startOfMinute());
    Gate::define('manage-settings', fn () => true);
    $this->actingAs(User::factory()->create());

    $page = Livewire::test(SettingsPage::class)
        ->assertSee('Ustawienia')
        ->assertSee(SettingKey::FishroomPrice->label())
        ->callTableAction('change', SettingKey::FishroomPrice->value, [
            'value' => 45,
            'effective_from' => now()->timezone((string) config('app.display_timezone'))->format('Y-m-d H:i'),
            'reason' => 'test',
        ])
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseHas('setting_versions', ['key' => SettingKey::FishroomPrice->value, 'reason' => 'test']);

    $page
        ->callTableAction('history', SettingKey::FishroomPrice->value)
        ->assertSee('test');
});

it('is forbidden when the gate denies', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SettingsPage::class)->assertForbidden();
});
