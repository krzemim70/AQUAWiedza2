<?php

namespace App\Domain\Shops\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/** Odstępstwo pilota: w zakresie czasu zastępuje próg ofert. */
#[Table('shop_activation_exceptions')]
class ActivationException extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['effective_from' => 'datetime', 'effective_to' => 'datetime'];
    }
}
