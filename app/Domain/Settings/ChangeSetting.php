<?php

namespace App\Domain\Settings;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ChangeSetting
{
    public function __construct(private Settings $settings) {}

    public function handle(ChangeSettingData $data): SettingVersion
    {
        Gate::forUser($data->actor)->authorize('manage-settings');

        $effectiveFrom = $data->effectiveFrom ?? now();

        Validator::make(
            ['value' => $data->value, 'reason' => $data->reason, 'effective_from' => $effectiveFrom],
            ['reason' => ['required', 'string', 'max:255'], ...$data->key->rules()],
        )->validate();

        if ($effectiveFrom->lt(now()->subMinute())) {
            throw ValidationException::withMessages(['effective_from' => 'Data wejścia w życie nie może być w przeszłości.']);
        }

        $old = $this->settings->get($data->key, $effectiveFrom);

        $version = DB::transaction(function () use ($data, $effectiveFrom, $old) {
            $version = SettingVersion::create([
                'key' => $data->key->value,
                'value' => $data->value,
                'effective_from' => $effectiveFrom,
                'created_by' => $data->actor->id,
                'reason' => $data->reason,
            ]);

            activity()->causedBy($data->actor)->performedOn($version)
                ->withProperties(['key' => $data->key->value, 'old' => $old, 'new' => $data->value])
                ->log('setting changed');

            SettingChanged::dispatch($version);

            return $version;
        });

        $this->settings->forget($data->key);

        return $version;
    }
}
