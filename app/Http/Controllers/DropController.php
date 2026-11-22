<?php

namespace App\Http\Controllers;

use App\Domain\Advisor\Actions\RunDrop;
use App\Domain\Advisor\DropAllowance;
use App\Domain\Advisor\Engine\FishBand;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\TankProfile;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/** Strona główna: krótki dobór gościa (Drop). Limit bezpłatnych doborów w podpisanym cookie, opcjonalnie licznik IP. */
class DropController
{
    private const COOKIE = 'drop_allowance';

    public function show(): Response
    {
        return $this->page();
    }

    public function run(Request $request, ResolveAccess $access, RunDrop $drop, Settings $settings): Response|RedirectResponse
    {
        if (! $access->handle($request->user(), Feature::DropSelection)->allowed) {
            return redirect()->route('advisor.tank');
        }

        $ipHash = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
        $rate = $settings->get(SettingKey::DropAttemptRate);
        $tooMany = RateLimiter::tooManyAttempts('drop:'.$ipHash, $rate['max']);
        RateLimiter::hit('drop:'.$ipHash, $rate['window_seconds']);
        if ($tooMany) {
            return $this->page(['state' => 'blocked'])->setStatusCode(429);
        }

        $v = $request->validate([
            'size' => ['required', Rule::enum(TankBand::class)],
            'fish' => ['nullable', Rule::enum(FishBand::class)],
            'temperature' => ['nullable', Rule::enum(TemperatureBand::class)],
            'ph' => ['nullable', Rule::enum(PhBand::class)],
            'request_key' => ['nullable', 'string', 'max:64'],
        ], [
            'size.required' => 'Wybierz wielkość akwarium.',
            'enum' => 'Wybierz jedną z dostępnych opcji.',
        ]);
        $values = array_map(fn ($value) => $value ?: null, $request->only(['size', 'fish', 'temperature', 'ph']));
        $profile = new TankProfile(
            TankBand::from($v['size']),
            FishBand::tryFrom($v['fish'] ?? ''),
            TemperatureBand::tryFrom($v['temperature'] ?? ''),
            PhBand::tryFrom($v['ph'] ?? ''),
        );

        // Zużycie dotyczy klucza żądania powiązanego z profilem, więc ten sam klucz nie odsłoni innego wyniku.
        $requestKey = $v['request_key'] ?? bin2hex(random_bytes(8));
        $usageKey = hash('xxh3', $requestKey.json_encode($values));
        $limit = (int) $settings->get(SettingKey::DropFreeSelections);
        $window = (int) $settings->get(SettingKey::DropCookieDays) * 86400;
        $allowance = DropAllowance::fromCookie($request->cookie(self::COOKIE), time(), $window);

        $ipEnabled = (bool) $settings->get(SettingKey::DropIpEnabled);
        $ipCache = 'drop-ip:'.$ipHash;
        $ipKeys = $ipEnabled ? Cache::get($ipCache, []) : [];
        $permitted = DropAllowance::permits($allowance->keys, $usageKey, $limit)
            && (! $ipEnabled || DropAllowance::permits($ipKeys, $usageKey, (int) $settings->get(SettingKey::DropIpLimit)));
        if (! $permitted) {
            return $this->page(['state' => 'limit']);
        }

        $ranking = $drop->handle($profile);
        $data = ['ranking' => $ranking, 'values' => $values, 'requestKey' => $requestKey];
        if ($ranking->top() === null) {
            return $this->page($data + ['state' => 'none']);
        }
        if ($ranking->topCount > 1 && ! $request->boolean('reveal')) {
            return $this->page($data + ['state' => 'tie']);
        }

        // Dobór zużywa się dopiero przy odsłonięciu karty.
        $allowance = $allowance->with($usageKey);
        if ($ipEnabled) {
            Cache::put($ipCache, array_values(array_unique([...$ipKeys, $usageKey])), now()->addDays((int) $settings->get(SettingKey::DropIpDays)));
        }
        $minutes = (int) ceil(($allowance->startedAt + $window - time()) / 60);

        return $this->page($data + ['state' => 'card'])
            ->withCookie(cookie(self::COOKIE, $allowance->toCookie(), $minutes));
    }

    /** @param array<string, mixed> $data */
    private function page(array $data = []): Response
    {
        return response()->view('public.home', $data)->header('Cache-Control', 'private, no-store');
    }
}
