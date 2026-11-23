<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Actions\SuggestReminders;
use App\Domain\IdentityAccess\Models\Feature;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Zakładka zbiornika: podpowiedzi rodzajów przypomnień z rekordów bazy wiedzy; dodanie otwiera formularz przypomnień. */
class ReminderSuggestions extends Component
{
    #[Locked]
    public int $tankId;

    public function mount(int $tankId): void
    {
        $this->tankId = $tankId;
        app(Access::class)->tank(auth()->user(), $tankId, Feature::ReminderSuggestions);
    }

    public function render(SuggestReminders $suggest): View
    {
        return view('livewire.aquarium.reminder-suggestions', ['hints' => $suggest->handle(auth()->user(), $this->tankId)]);
    }
}
