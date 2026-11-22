<?php

namespace App\Http\Controllers;

use App\Domain\IdentityAccess\Actions\AcceptConsents;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsentController
{
    public function show(Request $request, AcceptConsents $consents): View|RedirectResponse
    {
        return $consents->current($request->user()) ? redirect($request->user()->homePath()) : view('auth.consents');
    }

    public function store(Request $request, AcceptConsents $consents): RedirectResponse
    {
        $request->validate(['terms' => ['accepted'], 'privacy' => ['accepted'], 'adult' => ['accepted'], 'newsletter' => ['boolean']]);
        $consents->handle($request->user(), $request->boolean('newsletter'), $request->ip());

        return redirect()->intended($request->user()->homePath());
    }
}
