<?php

namespace App\Http\Controllers\Auth;

use App\Domain\IdentityAccess\Actions\RegisterAccount;
use App\Domain\IdentityAccess\Data\RegisterAccountData;
use App\Domain\IdentityAccess\Models\AccountType;
use App\Domain\IdentityAccess\Nip;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController
{
    private const TYPES = ['akwarysta' => AccountType::Aquarist, 'sklep' => AccountType::Shop];

    public function choose(): View
    {
        return view('auth.register-choose');
    }

    public function create(string $type): View
    {
        return view('auth.register', [
            'type' => $type,
            'shop' => $type === 'sklep',
            'formToken' => Crypt::encryptString((string) now()->timestamp),
        ]);
    }

    public function store(Request $request, string $type, Settings $settings, RegisterAccount $register): RedirectResponse
    {
        $accountType = self::TYPES[$type];
        $shop = $accountType === AccountType::Shop;

        // Ochrona: limit na IP (liczy każdą próbę) → honeypot → czas wypełnienia → walidacja. Komunikaty nie zdradzają, który krok odrzucił.
        $rate = $settings->get(SettingKey::RegistrationRate);
        $key = 'register:'.$request->ip();
        $tooMany = RateLimiter::tooManyAttempts($key, $rate['max']);
        RateLimiter::hit($key, $rate['window_seconds']);

        $timer = $settings->get(SettingKey::RegistrationTimer);
        if ($tooMany || $request->filled('fax') || ! $this->filledInTime($request, $timer['min'], $timer['max'])) {
            return back()->withInput()->withErrors(['form' => 'Nie udało się wysłać formularza. Spróbuj ponownie za chwilę.']);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'confirmed', Password::min((int) $settings->get(SettingKey::PasswordMinLength))],
            'terms' => ['accepted'],
            'privacy' => ['accepted'],
            'adult' => ['accepted'],
            'newsletter' => ['boolean'],
        ];
        if ($shop) {
            $rules += [
                'company_name' => ['required', 'string', 'max:255'],
                'nip' => ['required', fn ($a, $v, $fail) => Nip::isValid((string) $v) || $fail('Niepoprawny NIP.')],
                'address' => ['required', 'string', 'max:255'],
                'phone' => ['required', 'string', 'max:32'],
            ];
        }
        $v = $request->validate($rules);

        $register->handle(new RegisterAccountData(
            $accountType, $v['name'], $v['email'], $v['password'], $request->boolean('newsletter'), $request->ip(),
            $v['company_name'] ?? null, $v['nip'] ?? null, $v['address'] ?? null, $v['phone'] ?? null,
        ));

        // ta sama odpowiedź także dla zajętego adresu (brak enumeracji kont)
        return redirect()->route('register.done');
    }

    public function done(): View
    {
        return view('auth.registered');
    }

    private function filledInTime(Request $request, int $min, int $max): bool
    {
        try {
            $age = now()->timestamp - (int) Crypt::decryptString((string) $request->input('form_token'));
        } catch (DecryptException) {
            return false;
        }

        return $age >= $min && $age <= $max;
    }
}
