<?php

namespace App\Domain\KnowledgeBase\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Rejestr identyfikatorów (nigdy nie usuwany, ID nie wraca do puli).
 *
 * @property int $id
 * @property Area $area
 * @property string $legacy_id
 * @property string $status
 * @property string|null $source_status
 * @property string|null $name
 */
#[Table('catalog_id_registry')]
#[Fillable(['area', 'legacy_id', 'status', 'source_status', 'name'])]
class RegistryEntry extends Model
{
    public const ACTIVE = 'active';

    public const FUTURE = 'future';

    public const RETIRED = 'retired';

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Identyfikator z rejestru nie może zostać usunięty.'));
    }

    protected function casts(): array
    {
        return ['area' => Area::class];
    }
}
