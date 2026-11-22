<?php

namespace App\Http\Controllers\Auth;

use App\Domain\IdentityAccess\Actions\ActivateAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerificationController
{
    public function notice(): View
    {
        return view('auth.verify');
    }

    /** Trasa `signed` (Laravel); użytkownik nie musi być zalogowany. */
    public function verify(string $id, string $hash, ActivateAccount $activate): RedirectResponse
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals(hash('sha256', $user->getEmailForVerification()), $hash), 403);

        $activate->handle($user);

        return redirect()->route('login')->with('status', 'Adres e-mail potwierdzony. Możesz się zalogować.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $email = $request->validate(['email' => ['required', 'email']])['email'];

        User::where('email', $email)->whereNull('email_verified_at')->first()?->sendEmailVerificationNotification();

        return back()->with('status', 'Jeśli konto czeka na potwierdzenie, wysłaliśmy nowy link.');
    }
}
