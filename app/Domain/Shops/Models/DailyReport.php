<?php

namespace App\Domain\Shops\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Raport dobowy sklepu (jeden na sklep i dzień lokalny).
 *
 * @property int $shop_id
 * @property Carbon $business_date
 * @property array<string, mixed> $data
 */
#[Table('shop_daily_reports')]
class DailyReport extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['business_date' => 'date', 'period_from' => 'datetime', 'period_to' => 'datetime', 'generated_at' => 'datetime', 'data' => 'array'];
    }
}
