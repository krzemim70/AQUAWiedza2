<?php

namespace App\Domain\Settings;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Odczyt ustawień; wiązana jako singleton, pamięć podręczna tylko na czas requestu. */
class Settings
{
    /** @var array<string, Collection<int, SettingVersion>> */
    private array $versions = [];

    public function get(SettingKey $key, ?CarbonInterface $at = null): mixed
    {
        $at ??= now();

        $this->versions[$key->value] ??= SettingVersion::where('key', $key->value)
            ->orderByDesc('effective_from')->get();

        $row = $this->versions[$key->value]->first(fn (SettingVersion $v) => $v->effective_from->lte($at));

        return $row ? $row->value : $key->default();
    }

    public function forget(SettingKey $key): void
    {
        unset($this->versions[$key->value]);
    }
}
