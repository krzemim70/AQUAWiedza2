<?php

namespace App\Domain\Shops\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Migawka kontaktu klienta widoczna dla sklepu (nick, e-mail, telefon).
 *
 * @property int $order_id
 * @property string $nick
 * @property string $email
 * @property string $phone
 */
#[Table('shop_order_contacts')]
class OrderContact extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'order_id';

    protected $guarded = [];
}
