<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\EquipmentData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeEquipment
{
    public function __construct(private Access $access) {}

    /** Ilość 0 usuwa pozycję. */
    public function handle(User $actor, int $tankId, int $equipmentId, int $quantity): void
    {
        DB::transaction(function () use ($actor, $tankId, $equipmentId, $quantity) {
            $tank = $this->access->tank($actor, $tankId, forUpdate: true);
            $row = $tank->equipment()->findOrFail($equipmentId);
            if ($quantity < 0 || $quantity > EquipmentData::MAX_QUANTITY) {
                throw ValidationException::withMessages(['quantity' => 'Nieprawidłowa ilość sprzętu.']);
            }
            if ($quantity === 0) {
                $row->delete();
            } else {
                $row->update(['quantity' => $quantity]);
            }
        }, attempts: 3);
    }
}
