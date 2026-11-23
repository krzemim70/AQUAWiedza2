<?php

namespace App\Http\Controllers;

use App\Domain\IdentityAccess\Actions\ChangePassword;
use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Notifications\Contracts\Preferences;
use App\Domain\Notifications\Contracts\PushSubscriptions;
use App\Domain\Notifications\Topic;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Strona „Moje konto”: dane konta, poziom dostępu, sklepy; zmiana nazwy i hasła. */
class AccountController
{
    public function show(
        Request $request,
        ResolveAccess $access,
        Settings $settings,
        Preferences $preferences,
        PushSubscriptions $pushSubscriptions,
    ): View {
        $user = $request->user();
        $pushSettings = (array) $settings->get(SettingKey::PushNotifications);

        return view('account', [
            'user' => $user,
            'tier' => $user->tier(),
            'shops' => $user->shops,
            'canWatch' => $access->handle($user, Feature::RecordWatch)->allowed,
            'canPush' => $access->handle($user, Feature::PushNotifications)->allowed,
            'pushGlobalEnabled' => (bool) ($pushSettings['enabled'] ?? false),
            'pushEnabled' => $preferences->allowsPush($user->id, Topic::Reminders),
            'pushSubscriptionCount' => $pushSubscriptions->countFor($user->id),
            'vapidPublicKey' => trim((string) config('services.webpush.public_key', '')),
        ]);
    }

    public function updateName(Request $request): RedirectResponse
    {
        $request->user()->update($request->validate(['name' => ['required', 'string', 'max:255']]));

        return back()->with('status', 'Zapisano nazwę.');
    }

    /** Miasto jest opcjonalne: puste pole czyści wartość. */
    public function updateCity(Request $request): RedirectResponse
    {
        $data = $request->validate(['city' => ['nullable', 'string', 'max:100']]);
        $request->user()->update(['city' => $data['city'] ?? null]);

        return back()->with('status', 'Zapisano miasto.');
    }

    public function updatePassword(Request $request, Settings $settings, ChangePassword $changePassword): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min((int) $settings->get(SettingKey::PasswordMinLength))],
        ]);
        $changePassword->handle($request->user(), $data['password']);
        $request->session()->regenerate(true);

        return back()->with('status', 'Zmieniono hasło.');
    }
}
