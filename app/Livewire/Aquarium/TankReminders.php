<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Actions\CancelReminder;
use App\Domain\Aquarium\Actions\CompleteReminder;
use App\Domain\Aquarium\Actions\CreateReminder;
use App\Domain\Aquarium\Actions\UpcomingReminders;
use App\Domain\Aquarium\Actions\UpdateReminder;
use App\Domain\Aquarium\Data\ReminderData;
use App\Domain\Aquarium\Models\ReminderOccurrence;
use App\Domain\Aquarium\Models\ReminderType;
use App\Domain\IdentityAccess\Models\Feature;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Przypomnienia zbiornika: lista (zaległe i w horyzoncie), dodawanie, edycja, wykonanie, anulowanie. */
class TankReminders extends Component
{
    #[Locked]
    public int $tankId;

    #[Locked]
    public ?int $editingId = null;

    #[Locked]
    public ?int $version = null;

    public string $type = 'water_change';

    public string $label = '';

    public string $due_on = '';

    public string $interval_days = '';

    public function mount(int $tankId): void
    {
        $this->tankId = $tankId;
        app(Access::class)->tank(auth()->user(), $tankId, Feature::MeasurementHistory);
        $this->due_on = now()->timezone(config('app.display_timezone'))->toDateString();
        // Typ z odnośnika podpowiedzi; interwał zostaje pusty (podaje go użytkownik).
        $hinted = ReminderType::tryFrom((string) request()->query('typ'));
        if ($hinted !== null) {
            $this->type = $hinted->value;
        }
    }

    public function save(CreateReminder $create, UpdateReminder $update): void
    {
        $data = new ReminderData($this->type, $this->label, $this->due_on, $this->interval_days, $this->version);
        if ($this->editingId === null) {
            $create->handle(auth()->user(), $this->tankId, $data);
        } else {
            $update->handle(auth()->user(), $this->editingId, $data);
        }
        $this->cancelEdit();
    }

    public function edit(int $reminderId): void
    {
        $reminder = app(Access::class)->reminder(auth()->user(), $reminderId);
        abort_unless($reminder->tank_id === $this->tankId, 404);

        $due = $reminder->occurrences()->where('state', ReminderOccurrence::PENDING)->first()?->due_on;
        $this->fill([
            'editingId' => $reminder->id, 'version' => $reminder->version, 'type' => $reminder->type->value,
            'label' => $reminder->label ?? '', 'due_on' => ($due ?? $reminder->starts_on)->toDateString(),
            'interval_days' => (string) ($reminder->interval_days ?? ''),
        ]);
    }

    public function cancelEdit(): void
    {
        $this->resetErrorBag();
        $this->reset('editingId', 'version', 'label', 'interval_days', 'type');
        $this->due_on = now()->timezone(config('app.display_timezone'))->toDateString();
    }

    public function complete(CompleteReminder $complete, int $occurrenceId): void
    {
        $complete->handle(auth()->user(), $occurrenceId);
    }

    public function cancel(CancelReminder $cancel, int $reminderId): void
    {
        $cancel->handle(auth()->user(), $reminderId);
        if ($this->editingId === $reminderId) {
            $this->cancelEdit();
        }
    }

    public function render(UpcomingReminders $upcoming): View
    {
        return view('livewire.aquarium.tank-reminders', [
            'occurrences' => $upcoming->handle(auth()->user(), $this->tankId),
            'types' => ReminderType::cases(),
            'today' => now()->timezone(config('app.display_timezone'))->toDateString(),
        ]);
    }
}
