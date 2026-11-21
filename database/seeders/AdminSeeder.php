<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates (or refreshes) the admin account from the ADMIN_* values in .env.
 */
class AdminSeeder extends Seeder
{
    /** Values the admin account cannot be created without. */
    private const REQUIRED_KEYS = ['email', 'password', 'nik', 'phone'];

    /**
     * Create the admin, or update it when one with that email already exists.
     */
    public function run(): void
    {
        $missingKeys = array_filter(self::REQUIRED_KEYS, fn (string $key) => blank(config("admin.{$key}")));

        if ($missingKeys !== []) {
            $envNames = array_map(fn (string $key) => 'ADMIN_'.strtoupper($key), $missingKeys);
            $this->command?->warn('Admin account skipped; set in .env: '.implode(', ', $envNames));

            return;
        }

        User::updateOrCreate(['email' => config('admin.email')], [
            'name' => config('admin.name'),
            'nik' => config('admin.nik'),
            'phone' => config('admin.phone'),
            'password' => config('admin.password'),
            'role' => UserRole::Admin,
            'status' => UserStatus::Approved,
        ]);
    }
}
