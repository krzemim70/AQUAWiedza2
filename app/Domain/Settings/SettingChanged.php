<?php

namespace App\Domain\Settings;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class SettingChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly SettingVersion $version) {}
}
