<?php

namespace App\Http\Controllers;

use App\Domain\Advisor\CreatorBundle;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** Publiczny kreator akwarium w przeglądarce: powłoka widoku i konfiguracja pakietu katalogu. */
class AquariumCreatorController
{
    public function show(Request $request, CreatorBundle $bundle): View
    {
        $built = $bundle->ensureBuilt();
        $hasCatalog = $built['version'] !== 'empty';

        /** @var User|null $user */
        $user = $request->user();

        return view('creator.show', [
            'bundleUrl' => $built['url'],
            'bundleVersion' => $built['version'],
            'hasCatalog' => $hasCatalog,
            'config' => [
                'bundleUrl' => $built['url'],
                'bundleVersion' => $built['version'],
                'hasCatalog' => $hasCatalog,
                'isAuthenticated' => $user !== null,
                'userCity' => $user?->city,
                'loginUrl' => route('login'),
                'registerUrl' => route('register'),
                'catalogProductUrlTemplate' => route('catalog.show', ['area' => '__AREA__', 'id' => '__ID__']),
            ],
        ]);
    }
}
