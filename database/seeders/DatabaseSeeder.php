<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production-safe seeder: creates ONE admin account and neutral settings.
 * It does NOT create violation categories or penalties (the school configures those).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SEED_ADMIN_EMAIL', 'admin@svms.test');

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'System Administrator',
                'password' => env('SEED_ADMIN_PASSWORD', 'ChangeMe!12345'),
                'role' => User::ROLE_ADMIN,
                'position' => 'Administrator',
                'is_active' => true,
                'must_change_password' => true, // forced to choose a new password on first login
            ]
        );

        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
