<?php

namespace App\Http\Controllers;

use App\Domain\IdentityAccess\Actions\DeleteAccount;
use App\Domain\IdentityAccess\Actions\ExportAccount;
use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Actions\WithdrawNewsletterConsent;
use App\Domain\IdentityAccess\Models\Consent;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AccountPrivacyController
{
    public function show(Request $request, ResolveAccess $access, Settings $settings): View
    {
        $user = $request->user();
        $consents = Consent::forUser($user)->latest('id')->get();
        $newsletter = $consents->where('document', 'newsletter')->values();
        $withdrawn = $newsletter->filter(fn (Consent $consent, int $position) => ! $consent->granted && $newsletter->get($position + 1)?->granted);

        return view('account-privacy', [
            'consents' => $consents,
            'newsletterGranted' => $newsletter->first()?->granted,
            'withdrawn' => $withdrawn->keyBy('id'),
            'deletion' => $access->handle($user, Feature::DeleteAccount),
            'exportInterval' => $settings->get(SettingKey::ExportIntervalMinutes),
        ]);
    }

    public function withdrawNewsletter(Request $request, WithdrawNewsletterConsent $withdraw): RedirectResponse
    {
        $withdraw->handle($request->user(), $request->ip());

        return to_route('account.privacy')->with('status', 'Zgoda na newsletter jest wycofana.');
    }

    public function export(Request $request, ExportAccount $export, Settings $settings): JsonResponse
    {
        $user = $request->user();
        $minutes = (int) $settings->get(SettingKey::ExportIntervalMinutes);
        abort_unless(Cache::add('account-export:'.$user->id, true, $minutes * 60), 429, 'Limit pobrań danych został wykorzystany. Spróbuj ponownie później.');

        return response()->json($export->handle($user), 200, [
            'Content-Disposition' => 'attachment; filename="aikwarium-moje-dane.json"',
            'Cache-Control' => 'private, no-store',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function destroy(Request $request, DeleteAccount $delete): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'confirmation' => ['required', 'string', 'in:USUŃ'],
        ], [
            'current_password.required' => 'Podaj obecne hasło.',
            'confirmation.required' => 'Wpisz słowo USUŃ, aby potwierdzić.',
            'confirmation.in' => 'Wpisz słowo USUŃ wielkimi literami.',
        ]);
        $delete->handle($request->user(), $data['current_password']);

        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', 'Konto zostało usunięte.');
    }
}
