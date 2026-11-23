<?php

namespace App\Http\Controllers\Auth;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Http\AdvisorReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class SessionController
{
    public function create(Request $request): View
    {
        return view('auth.login', ['returnTo' => AdvisorReturn::card($request->query->all()['wroc'] ?? null)]);
    }

    public function store(Request $request, ResolveAccess $access): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email', 'max:254'], 'password' => ['required', 'string']]);

        $key = 'login:'.strtolower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Zbyt wiele prób logowania. Spróbuj za chwilę.']);
        }
        RateLimiter::hit($key, 60);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Nieprawidłowy e-mail lub hasło.']);
        }

        $user = $request->user();
        if (! $user || ! $user->hasVerifiedEmail()) {
            Auth::logout();

            return back()->withInput($request->only('email'))->withErrors(['email' => 'Potwierdź adres e-mail linkiem z wiadomości, aby się zalogować.']);
        }

        $decision = $access->handle($user, Feature::Login);
        if (! $decision->allowed) {
            Auth::logout();

            return back()->withInput($request->only('email'))->withErrors(['email' => $decision->message]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $returnTo = AdvisorReturn::card($request->input('wroc'));

        return $returnTo !== null ? redirect($returnTo) : redirect()->intended($user->homePath());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
