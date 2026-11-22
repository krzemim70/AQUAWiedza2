<?php

namespace App\Http\Controllers;

use App\Domain\IdentityAccess\Actions\ChangePassword;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Strona „Moje konto”: dane konta, poziom dostępu, sklepy; zmiana nazwy i hasła. */
class AccountController
{
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('account', ['user' => $user, 'tier' => $user->tier(), 'shops' => $user->shops]);
    }

    public function updateName(Request $request): RedirectResponse
    {
        $request->user()->update($request->validate(['name' => ['required', 'string', 'max:255']]));

        return back()->with('status', 'Zapisano nazwę.');
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
