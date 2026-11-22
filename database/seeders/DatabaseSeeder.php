<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Lokalny admin deweloperski z DEV_ADMIN_EMAIL / DEV_ADMIN_PASSWORD (.env); tylko APP_ENV=local. */
    public function run(): void
    {
        $email = config('app.dev_admin_email');
        $password = config('app.dev_admin_password');

        if (! app()->isLocal() || ! is_string($email) || ! is_string($password) || $password === '') {
            return;
        }

        $admin = User::updateOrCreate(['email' => $email], ['name' => 'Admin (lokalny)', 'password' => $password]);
        $admin->forceFill(['is_admin' => true, 'email_verified_at' => $admin->email_verified_at ?? now()])->save();
    }
}
