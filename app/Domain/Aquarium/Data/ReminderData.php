<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\Aquarium\Models\ReminderType;
use App\Domain\Aquarium\Validation;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use Illuminate\Validation\Rule;

/** Surowe wejście formularza; `version` tylko przy edycji (kontrola równoczesnej zmiany). */
final readonly class ReminderData
{
    public function __construct(
        public string $type,
        public ?string $label,
        public string $dueOn,
        public ?string $intervalDays = null,
        public ?int $version = null,
    ) {}

    /**
     * Walidacja (zakres interwału z ustawień) i kolumny: type, label, due_on, interval_days.
     *
     * @return array{type: string, label: string|null, due_on: string, interval_days: int|null}
     */
    public function attributes(): array
    {
        $range = app(Settings::class)->get(SettingKey::ReminderInterval);
        $label = $this->label === null || trim($this->label) === '' ? null : trim($this->label);
        $interval = $this->intervalDays === null || trim($this->intervalDays) === '' ? null : trim($this->intervalDays);

        $v = Validation::validate(
            ['type' => $this->type, 'label' => $label, 'due_on' => $this->dueOn, 'interval_days' => $interval],
            [
                'type' => ['required', Rule::enum(ReminderType::class)],
                'label' => ['nullable', 'string', 'max:100'],
                'due_on' => ['required', 'date_format:Y-m-d'],
                'interval_days' => ['nullable', 'integer', "between:{$range['min']},{$range['max']}"],
            ],
            ['type' => 'Rodzaj', 'label' => 'Nazwa', 'due_on' => 'Termin', 'interval_days' => 'Powtarzaj co (dni)'],
        );

        return [
            'type' => $v['type'], 'label' => $v['label'] ?? null, 'due_on' => $v['due_on'],
            'interval_days' => isset($v['interval_days']) ? (int) $v['interval_days'] : null,
        ];
    }
}
