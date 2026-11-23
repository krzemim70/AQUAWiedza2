<?php

namespace App\Http\Controllers;

use App\Domain\Advisor\CreatorBundle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/** Serwuje niezmienny pakiet katalogu kreatora (lub przekierowuje ze starej wersji na bieżącą). */
class CreatorBundleController
{
    public function __invoke(string $version, CreatorBundle $bundle): Response|RedirectResponse
    {
        $current = $bundle->currentVersion();
        if ($version !== $current) {
            return redirect()
                ->route('creator.bundle', ['version' => $current])
                ->header('Cache-Control', 'no-store');
        }

        return response($bundle->contents(), 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
