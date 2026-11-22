<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

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

    public function show(string $page): View
    {
        return view('public.page', ['title' => self::TITLES[$page]]);
    }
}
