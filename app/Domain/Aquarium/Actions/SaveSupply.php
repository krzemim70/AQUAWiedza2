<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\SupplyData;
use App\Domain\Aquarium\Models\SupplyUnit;
use App\Domain\Aquarium\Models\TankSupply;
use App\Domain\Aquarium\Validation;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Shops\Contracts\PublicShops;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Zapisuje nowy zapas preparatu lub karmy w zbiorniku; sprawdza obszar pielęgnacji, limit na zbiornik wg planu
 * oraz aktywność wskazanego preferowanego sklepu przez kontrakt Shops.
 */
class SaveSupply
{
    public function __construct(
        private Access $access,
        private Catalog $catalog,
        private Settings $settings,
        private PublicShops $shops,
    ) {}

    public function handle(User $actor, int $tankId, SupplyData $data): TankSupply
    {
        $catalogId = trim($data->catalogId);
        $validated = Validation::validate([
            'catalog_id' => $catalogId,
            'unit' => $data->unit,
            'package_amount' => $this->normalizeDecimal($data->packageAmount),
            'remaining_amount' => $data->remainingAmount !== null && trim($data->remainingAmount) !== ''
                ? $this->normalizeDecimal($data->remainingAmount)
                : null,
            'dose_amount' => $this->normalizeDecimal($data->doseAmount),
            'dose_per_liters' => $data->dosePerLiters !== null && trim($data->dosePerLiters) !== ''
                ? $this->normalizeDecimal($data->dosePerLiters)
                : null,
            'interval_days' => $data->intervalDays,
            'preferred_shop_id' => $data->preferredShopId,
        ], [
            'catalog_id' => ['required', 'string', 'max:64'],
            'unit' => ['required', 'in:'.implode(',', array_column(SupplyUnit::cases(), 'value'))],
            'package_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999'],
            'remaining_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:999999'],
            'dose_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999'],
            'dose_per_liters' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999'],
            'interval_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'preferred_shop_id' => ['nullable', 'integer', 'min:1'],
        ], [
            'catalog_id' => 'preparat lub karma',
            'unit' => 'jednostka',
            'package_amount' => 'wielkość opakowania',
            'remaining_amount' => 'pozostała ilość',
            'dose_amount' => 'dawka / porcja',
            'dose_per_liters' => 'na ile litrów',
            'interval_days' => 'co ile dni',
            'preferred_shop_id' => 'preferowany sklep',
        ]);

        if ($this->catalog->labels(Area::Care, [$catalogId]) === []) {
            throw ValidationException::withMessages(['catalog_id' => 'Wybierz preparat lub karmę z katalogu pielęgnacji.']);
        }

        $preferredShopId = $validated['preferred_shop_id'] !== null ? (int) $validated['preferred_shop_id'] : null;
        if ($preferredShopId !== null && ! $this->shops->isActive($preferredShopId)) {
            throw ValidationException::withMessages(['preferred_shop_id' => 'Wybierz aktywny sklep.']);
        }

        $remaining = $validated['remaining_amount'] ?? $validated['package_amount'];

        return DB::transaction(function () use ($actor, $tankId, $catalogId, $validated, $remaining, $preferredShopId): TankSupply {
            $tank = $this->access->tank($actor, $tankId, Feature::TankSupplies, forUpdate: true);

            $tier = $actor->tier();
            $limit = (int) ($this->settings->get(SettingKey::Supplies)[$tier->value] ?? 0);
            if (! $actor->is_admin && $tank->supplies()->active()->count() >= $limit) {
                throw ValidationException::withMessages([
                    'supply' => "Limit aktywnych pozycji zapasów w zbiorniku na koncie {$tier->label()} wynosi {$limit}.",
                ]);
            }

            return $tank->supplies()->create([
                'catalog_id' => $catalogId,
                'unit' => (string) $validated['unit'],
                'package_amount' => number_format((float) $validated['package_amount'], 2, '.', ''),
                'remaining_amount' => number_format((float) $remaining, 2, '.', ''),
                'balance_at' => now()->utc(),
                'dose_amount' => number_format((float) $validated['dose_amount'], 2, '.', ''),
                'dose_per_liters' => $validated['dose_per_liters'] !== null
                    ? number_format((float) $validated['dose_per_liters'], 2, '.', '')
                    : null,
                'interval_days' => $validated['interval_days'] !== null ? (int) $validated['interval_days'] : null,
                'preferred_shop_id' => $preferredShopId,
            ]);
        }, attempts: 3);
    }

    private function normalizeDecimal(string $value): string
    {
        return str_replace(',', '.', trim($value));
    }
}
