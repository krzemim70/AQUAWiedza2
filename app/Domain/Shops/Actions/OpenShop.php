<?php

namespace App\Domain\Shops\Actions;

use App\Domain\IdentityAccess\Nip;
use App\Domain\Shops\Models\Member;
use App\Domain\Shops\Models\MemberRole;
use App\Domain\Shops\Models\Shop;
use App\Domain\Shops\Models\ShopState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Otwiera sklep po pozytywnej weryfikacji firmy: stan „w przygotowaniu” i właściciel. Jeden sklep na NIP. */
class OpenShop
{
    public function handle(User $owner, string $name, string $nip): Shop
    {
        $nip = Nip::normalize($nip);

        return DB::transaction(function () use ($owner, $name, $nip) {
            if (Shop::where('nip', $nip)->exists()) {
                throw ValidationException::withMessages(['nip' => 'Sklep z tym numerem NIP już istnieje.']);
            }

            $shop = Shop::create(['name' => $name, 'slug' => Str::slug($name).'-'.$nip, 'nip' => $nip, 'state' => ShopState::Preparation]);
            Member::create(['shop_id' => $shop->id, 'user_id' => $owner->id, 'role' => MemberRole::Owner]);
            activity()->performedOn($shop)->causedBy($owner)->log('shop opened');

            return $shop;
        });
    }
}
