<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Builds users; by default an approved tenant, so tests can log in and rent straight away.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nik' => fake()->unique()->numerify(str_repeat('#', User::NIK_LENGTH)),
            'name' => fake()->name(),
            'phone' => fake()->numerify('08##########'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Tenant,
            'status' => UserStatus::Approved,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * An admin account.
     */
    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    /**
     * A tenant still waiting for verification.
     */
    public function pending(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Pending]);
    }

    /**
     * A tenant whose registration was rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Rejected]);
    }
}
