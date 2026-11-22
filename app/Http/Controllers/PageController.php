<?php

namespace App\Http\Controllers;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

/** Strony treści w przygotowaniu: jeden widok, tytuł po adresie. */
class PageController
{
    private const TITLES = [
        'regulamin' => 'Regulamin',
        'prywatnosc' => 'Polityka prywatności',
        'kontakt' => 'Kontakt',
        'dla-sklepow' => 'Dla sklepów',
    ];

    public const SLUGS = ['regulamin', 'prywatnosc', 'kontakt', 'dla-sklepow'];

    public function show(string $page, Settings $settings): View
    {
        $cookies = [];
        if ($page === 'prywatnosc') {
            /** @var SessionGuard $guard */
            $guard = Auth::guard('web');
            $sessionLifetime = config('session.lifetime').' min';
            $cookies = [
                [config('session.cookie'), 'Utrzymuje sesję, w tym stan zalogowania.', config('session.expire_on_close') ? 'Do zamknięcia przeglądarki' : $sessionLifetime],
                ['XSRF-TOKEN', 'Chroni formularze przed nieautoryzowanym wysłaniem z innej strony.', $sessionLifetime],
                [DropController::COOKIE, 'Zapamiętuje wykorzystanie szybkiego doboru przez gościa.', 'Do '.$settings->get(SettingKey::DropCookieDays).' dni'],
                [$guard->getRecallerName(), 'Utrzymuje logowanie po wybraniu opcji „Zapamiętaj mnie”.', 'Do 400 dni'],
            ];
        }

        return view('public.page', ['title' => self::TITLES[$page], 'cookies' => $cookies]);
    }
}
