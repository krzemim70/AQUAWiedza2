<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Actions\SaveJournalEntry;
use App\Domain\Aquarium\Data\JournalEntryData;
use App\Domain\IdentityAccess\Models\Feature;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TankJournal extends Component
{
    #[Locked]
    public int $tankId;

    public string $entry_date = '';

    public string $content = '';

    public function mount(int $tankId): void
    {
        $this->tankId = $tankId;
        app(Access::class)->tank(auth()->user(), $tankId, Feature::MeasurementHistory);
        $this->entry_date = now()->timezone(config('app.display_timezone'))->toDateString();
    }

    public function save(SaveJournalEntry $save): void
    {
        $save->handle(auth()->user(), $this->tankId, new JournalEntryData($this->entry_date, $this->content));
        $this->reset('content');
    }

    public function render(): View
    {
        $entries = app(Access::class)->tank(auth()->user(), $this->tankId, Feature::MeasurementHistory)
            ->journalEntries()->orderByDesc('entry_date')->orderByDesc('id')->limit(100)->get();

        return view('livewire.aquarium.tank-journal', ['entries' => $entries]);
    }
}
