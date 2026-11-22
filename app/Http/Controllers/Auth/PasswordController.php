<?php

namespace App\Http\Controllers\Auth;

use App\Domain\IdentityAccess\Actions\ChangePassword;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordController
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        Password::sendResetLink($request->validate(['email' => ['required', 'email']]));

        return back()->with('status', 'Jeśli konto istnieje, wysłaliśmy link do zmiany hasła.');
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request, Settings $settings, ChangePassword $changePassword): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min((int) $settings->get(SettingKey::PasswordMinLength))],
        ]);

        $status = Password::reset($data, function (User $user, string $password) use ($changePassword) {
            $changePassword->handle($user, $password);
            event(new PasswordReset($user));
        });

        return $status === Password::PasswordReset
            ? redirect()->route('login')->with('status', 'Hasło zostało zmienione.')
            : back()->withInput($request->only('email'))->withErrors(['email' => 'Link jest nieprawidłowy lub wygasł.']);
    }
}
