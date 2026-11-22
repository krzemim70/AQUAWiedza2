<?php

namespace App\Domain\IdentityAccess\Actions;

use App\Domain\IdentityAccess\Data\RegisterAccountData;
use App\Domain\IdentityAccess\Models\AccountType;
use App\Domain\IdentityAccess\Models\Consent;
use App\Domain\IdentityAccess\Models\ShopApplication;
use App\Domain\IdentityAccess\Nip;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class RegisterAccount
{
    /** Zwraca null, gdy e-mail jest już zajęty (wołający nie ujawnia tego użytkownikowi). */
    public function handle(RegisterAccountData $data): ?User
    {
        if (User::where('email', $data->email)->exists()) {
            return null;
        }

        try {
            $user = DB::transaction(function () use ($data) {
                $user = User::create([
                    'name' => $data->name, 'email' => $data->email, 'password' => $data->password, 'type' => $data->type,
                ]);
                $user->forceFill(['adult_declared_at' => now()])->save();

                $version = (string) app(Settings::class)->get(SettingKey::ConsentVersion);
                foreach (['terms' => true, 'privacy' => true, 'adult' => true, 'newsletter' => $data->newsletter] as $document => $granted) {
                    Consent::create([
                        'user_id' => $user->id, 'document' => $document, 'document_version' => $version,
                        'granted' => $granted, 'ip' => $data->ip, 'created_at' => now(),
                    ]);
                }

                if ($data->type === AccountType::Shop) {
                    ShopApplication::create([
                        'user_id' => $user->id, 'company_name' => (string) $data->companyName, 'nip' => Nip::normalize((string) $data->nip),
                        'address' => (string) $data->address, 'phone' => (string) $data->phone,
                    ]);
                }

                if (! app(Settings::class)->get(SettingKey::EmailVerificationRequired)) {
                    app(ActivateAccount::class)->handle($user);
                    $user->refresh();
                }

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        DB::afterCommit(fn () => event(new Registered($user)));

        return $user;
    }
}
