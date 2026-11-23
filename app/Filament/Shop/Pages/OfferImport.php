<?php

namespace App\Filament\Shop\Pages;

use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Access;
use App\Domain\Shops\Models\ImportBatch;
use App\Domain\Shops\Models\Shop;
use App\Filament\Shop\Support;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Locked;

class OfferImport extends Page
{
    protected static ?string $title = 'Import ofert';

    protected static ?string $slug = 'import-ofert';

    protected static ?string $navigationParentItem = 'Oferty';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|array $routeMiddleware = 'access:shop_panel';

    protected string $view = 'filament.shop.pages.offer-import';

    #[Locked]
    public ?int $batchId = null;

    #[Locked]
    public bool $waiting = false;

    public static function canAccess(): bool
    {
        $actor = Filament::auth()->user();
        $shop = Filament::getTenant();

        return $actor instanceof User && $shop instanceof Shop && app(Access::class)->isMember($actor, $shop->id);
    }

    public function mount(): void
    {
        $id = request()->query('import');
        if ($id === null) {
            return;
        }
        abort_unless(is_string($id) && ctype_digit($id), 404);
        $this->batchId = (int) $id;
        $state = $this->batch()?->state;
        $this->waiting = $state === 'queued';
        if (session('offer_import_started')) {
            $this->notifyResult($state);
        }
    }

    public function refreshImport(): void
    {
        if ($this->waiting && ($state = $this->batch()?->state) !== 'queued') {
            $this->waiting = false;
            $this->notifyResult($state);
        }
    }

    protected function getViewData(): array
    {
        return [
            'shop' => Support::shop(),
            'batch' => $this->batch(),
            'limits' => app(Settings::class)->get(SettingKey::ShopCsvImport),
        ];
    }

    private function batch(): ?ImportBatch
    {
        if ($this->batchId === null) {
            return null;
        }
        $actor = Support::user();
        $shop = Support::shop();
        app(Access::class)->authorizeMember($actor, $shop->id);

        return ImportBatch::forActor($actor, $shop)->findOrFail($this->batchId, ['id', 'state', 'last_line', 'counts', 'report']);
    }

    private function notifyResult(?string $state): void
    {
        match ($state) {
            'applied' => Notification::make()->title('Import zakończony')->success()->send(),
            'failed' => Notification::make()->title('Import przerwany')->warning()->send(),
            default => null,
        };
    }
}
