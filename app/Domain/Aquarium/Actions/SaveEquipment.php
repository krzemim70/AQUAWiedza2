<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\EquipmentData;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveEquipment
{
    public function __construct(private Access $access, private Catalog $catalog, private Settings $settings) {}

    public function handle(User $actor, int $tankId, EquipmentData $data): void
    {
        DB::transaction(function () use ($actor, $tankId, $data) {
            $tank = $this->access->tank($actor, $tankId, forUpdate: true);
            if ($data->quantity < 1 || $data->quantity > EquipmentData::MAX_QUANTITY) {
                throw ValidationException::withMessages(['quantity' => 'Ilość musi mieścić się w przedziale 1–'.EquipmentData::MAX_QUANTITY.'.']);
            }
            if ($this->catalog->labels(Area::Equipment, [$data->catalogId]) === []) {
                throw ValidationException::withMessages(['catalog_id' => 'Wybierz sprzęt z katalogu.']);
            }

            $key = ['area' => Area::Equipment->value, 'catalog_id' => $data->catalogId];
            if ($tank->equipment()->where($key)->exists()) {
                throw ValidationException::withMessages(['catalog_id' => 'Ten sprzęt jest już w wyposażeniu. Ilość możesz zmienić przy zapisanej pozycji.']);
            }
            $this->ensureLimit($tank->equipment()->count() + 1);
            $tank->equipment()->create($key + ['quantity' => $data->quantity]);
        }, attempts: 3);
    }

    public function ensureLimit(int $positions): void
    {
        $limit = (int) $this->settings->get(SettingKey::EquipmentLimit);
        if ($positions > $limit) {
            throw ValidationException::withMessages(['equipment' => "Limit wyposażenia w zbiorniku wynosi $limit."]);
        }
    }
}
